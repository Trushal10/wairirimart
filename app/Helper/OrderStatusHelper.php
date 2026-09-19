<?php

namespace App\Helper;

use App\Models\Order;
use App\Models\Setting;
use App\Models\Shipment;

/**
 * One vocabulary for "what state is this order in, and what do we call it".
 *
 * Before this, three pages each carried their own answer: the profile page
 * kept a $statusMap with inline hex colours, the confirmation page had
 * .oc-status-* CSS covering only three of the four order statuses (so a
 * cancelled order rendered an unstyled, near-invisible chip), and the tracking
 * page had a third palette plus a JS copy of it. They disagreed on wording too
 * — 'canceled' showed as "Cancelled" in one place and "Canceled" in another.
 *
 * Everything here returns a `tone` rather than a colour. The colours live in
 * client/css/order-ui.css so the storefront palette can move without a hunt
 * through Blade files.
 */
class OrderStatusHelper
{
    /** Tone → meaning: ok = done/good, info = in progress, warn = waiting on us or them, bad = ended badly. */
    public const TONE_OK = 'ok';
    public const TONE_INFO = 'info';
    public const TONE_WARN = 'warn';
    public const TONE_BAD = 'bad';
    public const TONE_MUTED = 'muted';

    /**
     * How an order's own status (Order::STATUS) should read to a customer.
     *
     * @return array{label:string, tone:string}
     */
    public static function orderBadge(?string $status): array
    {
        return match ($status) {
            Order::PENDING   => ['label' => 'Pending',   'tone' => self::TONE_WARN],
            Order::CONFIRMED => ['label' => 'Confirmed', 'tone' => self::TONE_INFO],
            Order::COMPLETED => ['label' => 'Delivered', 'tone' => self::TONE_OK],
            Order::CANCELLED => ['label' => 'Cancelled', 'tone' => self::TONE_BAD],
            default => [
                'label' => $status ? ucfirst(str_replace('_', ' ', $status)) : 'Unknown',
                'tone'  => self::TONE_MUTED,
            ],
        };
    }

    /**
     * Courier-side status. Note Shipment uses 'cancelled' while Order uses
     * 'canceled' — both spellings are accepted here rather than fixing the
     * column, which would need a migration across live rows.
     *
     * @return array{label:string, tone:string}
     */
    public static function shipmentBadge(?string $status): array
    {
        return match ($status) {
            Shipment::STATUS_DELIVERED        => ['label' => 'Delivered',        'tone' => self::TONE_OK],
            Shipment::STATUS_OUT_FOR_DELIVERY => ['label' => 'Out for delivery', 'tone' => self::TONE_INFO],
            Shipment::STATUS_IN_TRANSIT       => ['label' => 'In transit',       'tone' => self::TONE_INFO],
            Shipment::STATUS_PICKED_UP        => ['label' => 'Picked up',        'tone' => self::TONE_INFO],
            Shipment::STATUS_PICKUP_SCHEDULED => ['label' => 'Pickup scheduled', 'tone' => self::TONE_INFO],
            Shipment::STATUS_AWB_ASSIGNED     => ['label' => 'Ready to ship',    'tone' => self::TONE_INFO],
            Shipment::STATUS_ORDER_CREATED    => ['label' => 'Order placed',     'tone' => self::TONE_WARN],
            Shipment::STATUS_PENDING          => ['label' => 'Preparing',        'tone' => self::TONE_WARN],
            Shipment::STATUS_RTO              => ['label' => 'Returning',        'tone' => self::TONE_BAD],
            Shipment::STATUS_FAILED           => ['label' => 'Delivery failed',  'tone' => self::TONE_BAD],
            'cancelled', 'canceled'           => ['label' => 'Cancelled',        'tone' => self::TONE_BAD],
            default => [
                'label' => $status ? ucfirst(str_replace('_', ' ', $status)) : '—',
                'tone'  => self::TONE_MUTED,
            ],
        };
    }

    /** @return array{label:string, tone:string} */
    public static function paymentBadge(?string $status): array
    {
        return match ($status) {
            'paid'      => ['label' => 'Paid',      'tone' => self::TONE_OK],
            'pending'   => ['label' => 'Pending',   'tone' => self::TONE_WARN],
            'failed'    => ['label' => 'Failed',    'tone' => self::TONE_BAD],
            'refunded'  => ['label' => 'Refunded',  'tone' => self::TONE_INFO],
            'partially_refunded' => ['label' => 'Partly refunded', 'tone' => self::TONE_INFO],
            default => [
                'label' => $status ? ucfirst(str_replace('_', ' ', $status)) : 'Pending',
                'tone'  => self::TONE_WARN,
            ],
        };
    }

    /**
     * The four milestones a shopper actually recognises, in order, each marked
     * done / current / upcoming.
     *
     * Marketplaces all render this same rail (Placed → Confirmed → Shipped →
     * Delivered) rather than the courier's own seven-state machine, which
     * includes steps like "AWB assigned" that mean nothing to a customer. The
     * courier detail still shows underneath, on the tracking page.
     *
     * @return array<int, array{key:string, label:string, state:string, at:?\Illuminate\Support\Carbon}>
     */
    public static function milestones(Order $order, ?Shipment $shipment = null): array
    {
        $cancelled = $order->status === Order::CANCELLED;

        // How far the courier has actually taken it. Anything from picked_up
        // onwards counts as shipped; delivered is its own milestone.
        $shipStatus = $shipment?->status;
        $shipped = in_array($shipStatus, [
            Shipment::STATUS_PICKED_UP,
            Shipment::STATUS_IN_TRANSIT,
            Shipment::STATUS_OUT_FOR_DELIVERY,
            Shipment::STATUS_DELIVERED,
        ], true);
        $delivered = $order->status === Order::COMPLETED
            || $shipStatus === Shipment::STATUS_DELIVERED;

        $confirmed = $delivered || $shipped || in_array($order->status, [
            Order::CONFIRMED,
            Order::COMPLETED,
        ], true);

        $reached = [
            'placed'    => true,
            'confirmed' => $confirmed,
            'shipped'   => $shipped || $delivered,
            'delivered' => $delivered,
        ];

        $labels = [
            'placed'    => 'Order placed',
            'confirmed' => 'Confirmed',
            'shipped'   => 'Shipped',
            'delivered' => 'Delivered',
        ];

        $times = [
            'placed'    => $order->created_at,
            'confirmed' => null,
            'shipped'   => $shipment?->shipped_at,
            'delivered' => $shipment?->delivered_at,
        ];

        // The current step is the first one not yet reached — that is the one
        // the shopper is waiting on, so it gets the pulsing marker.
        $currentAssigned = false;
        $out = [];
        foreach ($labels as $key => $label) {
            if ($reached[$key]) {
                $state = 'done';
            } elseif (! $currentAssigned && ! $cancelled) {
                $state = 'current';
                $currentAssigned = true;
            } else {
                $state = 'upcoming';
            }

            $out[] = [
                'key'   => $key,
                'label' => $label,
                'state' => $cancelled && ! $reached[$key] ? 'upcoming' : $state,
                'at'    => $times[$key],
            ];
        }

        return $out;
    }

    /**
     * Is order tracking switched on for this deployment at all?
     *
     * Two things have to be true. ORDER_TRACKING_ENABLED is the operator's
     * kill switch — set it false on a shop that fulfils by hand and the whole
     * feature disappears, links and routes alike. Beyond that there has to be
     * at least one courier integration configured, because a tracking page
     * with no courier behind it is a row of em-dashes and an auto-refresh that
     * never has anything to report.
     */
    public static function trackingEnabled(): bool
    {
        if (! config('services.tracking.enabled', true)) {
            return false;
        }

        return self::hasConfiguredCourier();
    }

    /**
     * Is order tracking offered to shoppers at all?
     *
     * The admin's storefront toggle on top of the hard switch above. Turning it
     * off removes tracking from the whole storefront — the nav, the footer, the
     * product page, the order confirmation, the customer's profile, the links in
     * order emails, and the /track routes themselves, which then 404.
     *
     * It deliberately covers a customer's own order too, not just the marketing
     * entry points. Hiding links while leaving /track routable is a half-measure:
     * the URLs are guessable, they get bookmarked, and they go out in old order
     * emails, so the page would still be reachable by everyone who had ever seen
     * it. Operators who want customers to keep self-serve tracking should leave
     * this on.
     *
     * $settings is passed by views that already have it shared; anything else
     * (middleware, notifications) lets this resolve it once per request.
     */
    public static function publicTrackingVisible(?Setting $settings = null): bool
    {
        if (! self::trackingEnabled()) {
            return false;
        }

        $settings ??= self::settings();

        return (bool) ($settings?->content('show_track_order', true) ?? true);
    }

    /**
     * The settings row, for callers that have none to hand.
     *
     * Deliberately not memoized in a static. Every storefront render already
     * passes its shared $settings in, so this only runs for the /track
     * middleware and the order mails — one small indexed read each. A static
     * would outlive the request inside a queue worker and keep mailing a link
     * to a page the admin had since switched off.
     *
     * A missing or unreadable settings table answers null, which callers read
     * as "use the default" rather than failing the request — the same treatment
     * the storefront view composer gives it.
     */
    private static function settings(): ?Setting
    {
        try {
            return Setting::query()->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * At least one courier is set up and switched on.
     *
     * Cached for the request: the footer, the header and a product page can
     * each ask this on a single render, and a storefront page should not spend
     * three queries deciding whether to print a link. Failures answer "no" —
     * during install the delivery_partners table may not exist yet, and a
     * fatal there would take down every page rather than hide one link.
     */
    protected static function hasConfiguredCourier(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        try {
            return $cached = \App\Models\DeliveryPartner::query()
                ->where('is_active', true)
                ->exists();
        } catch (\Throwable) {
            return $cached = false;
        }
    }
}
