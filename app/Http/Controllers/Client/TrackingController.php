<?php

namespace App\Http\Controllers\Client;

use App\Helper\OrderStatusHelper;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Courier\CourierManager;
use App\Services\ShipmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;

class TrackingController extends Controller
{
    public function __construct(
        protected ShipmentService $shipments,
        protected CourierManager $couriers,
    ) {
    }

    public function form()
    {
        return view('client.track.index');
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'order_no' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
        ]);

        $key = 'track:' . strtolower($request->ip() . '|' . $data['email']);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return back()
                ->withInput()
                ->withErrors(['order_no' => "Too many attempts. Try again in {$seconds}s."]);
        }
        RateLimiter::hit($key, 60);

        $order = Order::query()
            ->where('order_no', trim($data['order_no']))
            ->whereRaw('LOWER(shipping_email) = ?', [strtolower($data['email'])])
            ->first();

        if (! $order) {
            return back()
                ->withInput()
                ->withErrors(['order_no' => 'No order matched that order number and email.']);
        }

        // Consume the rate-limit success so honest customers aren't punished
        RateLimiter::clear($key);
        $this->grantAccess($order);

        return redirect()->route('client.track.show', ['orderNo' => $order->order_no]);
    }

    public function show(string $orderNo)
    {
        $order = $this->requireAuthorized($orderNo);

        $order->load([
            'orderItems.product:id,name,slug',
            'orderItems.media',
            'payment',
            'shipments' => fn ($q) => $q->latest('id'),
            'latestShipment',
            'statusHistory' => fn ($q) => $q->orderByDesc('created_at'),
        ]);

        return view('client.track.show', [
            'order' => $order,
            'shipment' => $order->latestShipment,
            'timeline' => $order->statusHistory,
        ]);
    }

    /**
     * JSON endpoint polled by the tracking page. Also triggers a
     * server-side sync if the last update is stale — that way the page
     * stays fresh without waiting for a webhook or the cron.
     */
    public function live(string $orderNo)
    {
        $order = $this->requireAuthorized($orderNo, jsonOnFail: true);
        if ($order instanceof \Illuminate\Http\JsonResponse) {
            return $order;
        }

        $shipment = $order->latestShipment;
        // If the shipment hasn't updated in > 5 min and isn't terminal,
        // ask the courier for a fresh status. Errors are swallowed silently.
        if ($shipment
            && ! $shipment->isTerminal()
            && $shipment->updated_at
            && $shipment->updated_at->diffInMinutes(now()) > 5
        ) {
            try {
                $shipment = $this->shipments->syncTracking($shipment->fresh());
            } catch (\Throwable) {
                // ignore
            }
        }

        $order->load([
            'latestShipment',
            'statusHistory' => fn ($q) => $q->orderByDesc('created_at')->limit(30),
        ]);

        // The page used to hold its own copy of the status palette and its own
        // idea of which courier step means what, which drifted from the Blade
        // that rendered the first paint. The server now hands over the labels,
        // the tone and the milestone states so there is exactly one answer.
        $badge = $order->latestShipment
            ? OrderStatusHelper::shipmentBadge($order->latestShipment->status)
            : OrderStatusHelper::orderBadge($order->status);

        return response()->json([
            'order' => [
                'order_no' => $order->order_no,
                'status' => $order->status,
                'total' => $order->total,
                'created_at' => $order->created_at?->toIso8601String(),
            ],
            'badge' => $badge,
            'milestones' => collect(OrderStatusHelper::milestones($order, $order->latestShipment))
                ->map(fn ($m) => [
                    'key'   => $m['key'],
                    'label' => $m['label'],
                    'state' => $m['state'],
                    'at'    => $m['at']?->format('d M'),
                ])
                ->all(),
            'cancelled' => $order->status === \App\Models\Order::CANCELLED,
            'shipment' => $order->latestShipment ? [
                'provider' => $order->latestShipment->provider,
                'courier_name' => $order->latestShipment->courier_name,
                'awb_code' => $order->latestShipment->awb_code,
                'status' => $order->latestShipment->status,
                'tracking_url' => $order->latestShipment->tracking_url,
                'pickup_scheduled_date' => $order->latestShipment->pickup_scheduled_date?->toIso8601String(),
                'shipped_at' => $order->latestShipment->shipped_at?->toIso8601String(),
                'delivered_at' => $order->latestShipment->delivered_at?->toIso8601String(),
                'updated_at' => $order->latestShipment->updated_at?->toIso8601String(),
            ] : null,
            // label/tone/at alongside the raw values: the page renders the
            // first paint from Blade and every refresh from here, and the two
            // have to agree on wording, colour and date format.
            'timeline' => $order->statusHistory->map(function ($h) {
                $badge = OrderStatusHelper::shipmentBadge($h->status);

                return [
                    'status' => $h->status,
                    'label' => $badge['label'],
                    'tone' => $badge['tone'],
                    'source' => $h->source,
                    'comment' => $h->comment,
                    'at' => $h->created_at?->format('d M Y · g:i A'),
                    'created_at' => $h->created_at?->toIso8601String(),
                ];
            }),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function logout(Request $request, string $orderNo)
    {
        $granted = (array) Session::get('tracking_granted', []);
        unset($granted[$orderNo]);
        Session::put('tracking_granted', $granted);
        return redirect()->route('client.track.form')->with('success', 'Signed out of tracking.');
    }

    /* -------------------- helpers -------------------- */

    protected function grantAccess(Order $order): void
    {
        $granted = (array) Session::get('tracking_granted', []);
        $granted[$order->order_no] = [
            'order_id' => $order->id,
            'granted_at' => now()->toIso8601String(),
        ];
        Session::put('tracking_granted', $granted);
    }

    protected function requireAuthorized(string $orderNo, bool $jsonOnFail = false): Order|\Illuminate\Http\JsonResponse
    {
        $granted = (array) Session::get('tracking_granted', []);
        // Also allow the authenticated customer to view their own orders directly.
        $customerId = auth('customer')->id();
        $order = Order::where('order_no', $orderNo)->first();

        if (! $order || (! isset($granted[$orderNo]) && $order->customer_id !== $customerId)) {
            if ($jsonOnFail) {
                return response()->json(['error' => 'unauthorized'], 403);
            }
            abort(redirect()->route('client.track.form')->withErrors(['order_no' => 'Please verify your order to view tracking.']));
        }
        return $order;
    }
}
