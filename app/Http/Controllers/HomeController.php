<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        $data = [
            'kpis' => $this->buildKpis(),
            'revenue_series' => $this->buildRevenueSeries(30),
            'status_breakdown' => $this->buildStatusBreakdown(),
            'top_products' => $this->buildTopProducts(5),
            'recent_orders' => $this->buildRecentOrders(6),
            'low_stock' => $this->buildLowStock(),
        ];

        return Inertia::render('Dashboard', ['data' => $data]);
    }

    /* ------------------- Low-stock inventory ------------------- */

    /**
     * Products (or variants) with stock at or below the threshold.
     * Threshold defaults to 5 — overridable per project via config.
     */
    private function buildLowStock(int $limit = 8): array
    {
        $threshold = (int) config('services.inventory.low_stock_threshold', 5);

        return Product::query()
            ->where('status', true)
            ->where('stock', '>=', 0)
            ->where('stock', '<=', $threshold)
            ->orderBy('stock')
            ->limit($limit)
            ->get(['id', 'name', 'slug', 'sku', 'stock'])
            ->map(fn ($p) => [
                'id'    => $p->id,
                'name'  => $p->name,
                'slug'  => $p->slug,
                'sku'   => $p->sku,
                'stock' => (int) $p->stock,
                'level' => $p->stock === 0 ? 'out' : ($p->stock <= 2 ? 'critical' : 'low'),
            ])
            ->all();
    }

    /* -------------------------- KPIs -------------------------- */

    private function buildKpis(): array
    {
        $now = Carbon::now();
        $thirtyAgo = $now->copy()->subDays(30);
        $sixtyAgo = $now->copy()->subDays(60);

        // Revenue is drawn from confirmed / delivered orders only (i.e. not
        // cancelled and not still-pending). This matches how "gross sales"
        // is defined by most storefronts.
        $paidStatuses = [Order::CONFIRMED, Order::COMPLETED];

        $revenueLast30 = (float) Order::query()
            ->whereIn('status', $paidStatuses)
            ->where('created_at', '>=', $thirtyAgo)
            ->sum('total');

        $revenuePrev30 = (float) Order::query()
            ->whereIn('status', $paidStatuses)
            ->whereBetween('created_at', [$sixtyAgo, $thirtyAgo])
            ->sum('total');

        $ordersLast30 = (int) Order::query()
            ->where('created_at', '>=', $thirtyAgo)
            ->count();

        $ordersPrev30 = (int) Order::query()
            ->whereBetween('created_at', [$sixtyAgo, $thirtyAgo])
            ->count();

        $avgOrder = $ordersLast30 > 0 ? $revenueLast30 / $ordersLast30 : 0;

        $refundedLast30 = (float) Payment::query()
            ->where('refunded_at', '>=', $thirtyAgo)
            ->sum('refunded_amount');

        return [
            'revenue_last_30' => round($revenueLast30, 2),
            'revenue_delta_pct' => $this->deltaPct($revenueLast30, $revenuePrev30),
            'orders_last_30' => $ordersLast30,
            'orders_delta_pct' => $this->deltaPct($ordersLast30, $ordersPrev30),
            'avg_order_value' => round($avgOrder, 2),
            'refunded_last_30' => round($refundedLast30, 2),
            'total_customers' => Customer::count(),
            'total_products' => Product::count(),
            'total_orders' => Order::count(),
        ];
    }

    /* --------------------- Revenue series --------------------- */

    private function buildRevenueSeries(int $days = 30): array
    {
        $start = Carbon::now()->subDays($days - 1)->startOfDay();
        $end = Carbon::now()->endOfDay();

        $rows = Order::query()
            ->whereIn('status', [Order::CONFIRMED, Order::COMPLETED])
            ->whereBetween('created_at', [$start, $end])
            ->select(
                DB::raw('DATE(created_at) as d'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('COALESCE(SUM(total), 0) as revenue'),
            )
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->keyBy(fn ($row) => (string) $row->d);

        $series = [];
        $cursor = $start->copy();
        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d');
            $row = $rows->get($key);
            $series[] = [
                'date' => $key,
                'label' => $cursor->format('d M'),
                'orders' => (int) ($row->orders ?? 0),
                'revenue' => (float) ($row->revenue ?? 0),
            ];
            $cursor->addDay();
        }
        return $series;
    }

    /* --------------------- Status breakdown --------------------- */

    private function buildStatusBreakdown(): array
    {
        $rows = Order::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->status] = (int) $r->total;
        }
        // Ensure every status appears (even zero) so the chart legend is stable.
        foreach ([Order::PENDING, Order::CONFIRMED, Order::COMPLETED, Order::CANCELLED] as $s) {
            $out[$s] = $out[$s] ?? 0;
        }
        return $out;
    }

    /* ------------------------ Top products ------------------------ */

    private function buildTopProducts(int $limit = 5): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('orders.status', [Order::CONFIRMED, Order::COMPLETED])
            ->groupBy('products.id', 'products.name', 'products.slug')
            ->orderByDesc(DB::raw('SUM(order_items.quantity)'))
            ->limit($limit)
            ->get([
                'products.id',
                'products.name',
                'products.slug',
                DB::raw('SUM(order_items.quantity) as units_sold'),
                DB::raw('SUM(order_items.quantity * order_items.price) as revenue'),
            ])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'units_sold' => (int) $p->units_sold,
                'revenue' => (float) $p->revenue,
            ])
            ->all();
    }

    /* ----------------------- Recent orders ----------------------- */

    private function buildRecentOrders(int $limit = 6): array
    {
        return Order::query()
            ->select('id', 'order_no', 'shipping_name', 'total', 'status', 'created_at')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'order_no' => $o->order_no,
                'name' => $o->shipping_name,
                'total' => (float) $o->total,
                'status' => $o->status,
                'created_at' => $o->created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function deltaPct(float $current, float $previous): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }
}
