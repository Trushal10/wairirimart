<?php

namespace App\Services\Reports;

use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only aggregation service used by the admin Reports module.
 *
 * All queries are indexed-friendly (only filter on indexed columns) and use
 * scalar aggregates (SUM/COUNT/GROUP BY) rather than loading rows into memory.
 * Table names are hard-coded to avoid the join-alias ambiguity that can arise
 * when the same table is referenced twice.
 */
class ReportService
{
    /* ==================== SALES ==================== */

    public function salesSummary(ReportFilters $f): array
    {
        $q = $this->baseOrderQuery($f);

        $agg = (clone $q)
            ->selectRaw('
                COUNT(*)               as orders_count,
                COALESCE(SUM(sub_total), 0) as subtotal_sum,
                COALESCE(SUM(shipping), 0)  as shipping_sum,
                COALESCE(SUM(discount), 0)  as discount_sum,
                COALESCE(SUM(tax_amount), 0) as tax_sum,
                COALESCE(SUM(total), 0)     as revenue,
                COALESCE(AVG(NULLIF(total,0)), 0) as aov
            ')
            ->first();

        // customers = distinct customer_id in range
        $customers = (clone $q)->distinct('customer_id')->count('customer_id');

        return [
            'orders_count' => (int) ($agg->orders_count ?? 0),
            'revenue' => (float) ($agg->revenue ?? 0),
            'subtotal' => (float) ($agg->subtotal_sum ?? 0),
            'shipping' => (float) ($agg->shipping_sum ?? 0),
            'discount' => (float) ($agg->discount_sum ?? 0),
            'tax' => (float) ($agg->tax_sum ?? 0),
            'aov' => (float) ($agg->aov ?? 0),
            'customers' => (int) $customers,
        ];
    }

    public function salesTrend(ReportFilters $f): array
    {
        $sql = $this->groupExpression($f->group ?? 'day', 'orders.created_at');
        $rows = $this->baseOrderQuery($f)
            ->selectRaw("{$sql} as bucket, COUNT(*) as orders_count, COALESCE(SUM(total), 0) as revenue")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        return $this->fillTrendGaps($rows, $f, ['orders_count' => 0, 'revenue' => 0.0]);
    }

    public function topProducts(ReportFilters $f, int $limit = 10): Collection
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->select(
                'order_items.product_id',
                DB::raw('MAX(products.name) as name'),
                DB::raw('MAX(products.sku) as sku'),
                DB::raw('SUM(order_items.quantity) as qty'),
                DB::raw('SUM(order_items.quantity * order_items.price) as revenue')
            )
            ->when($f->from, fn ($q, $d) => $q->where('orders.created_at', '>=', $d))
            ->when($f->to, fn ($q, $d) => $q->where('orders.created_at', '<=', $d))
            ->when($f->status, fn ($q, $s) => $q->where('orders.status', $s))
            ->whereNull('orders.deleted_at')
            ->groupBy('order_items.product_id')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();
    }

    public function topCategories(ReportFilters $f, int $limit = 10): Collection
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_categories', 'product_categories.product_id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'product_categories.category_id')
            ->select(
                'categories.id',
                DB::raw('MAX(categories.name) as name'),
                DB::raw('SUM(order_items.quantity) as qty'),
                DB::raw('SUM(order_items.quantity * order_items.price) as revenue')
            )
            ->when($f->from, fn ($q, $d) => $q->where('orders.created_at', '>=', $d))
            ->when($f->to, fn ($q, $d) => $q->where('orders.created_at', '<=', $d))
            ->when($f->status, fn ($q, $s) => $q->where('orders.status', $s))
            ->whereNull('orders.deleted_at')
            ->groupBy('categories.id')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();
    }

    public function topCustomers(ReportFilters $f, int $limit = 10): Collection
    {
        return $this->baseOrderQuery($f)
            ->select(
                'orders.customer_id',
                DB::raw('MAX(orders.shipping_name) as name'),
                DB::raw('MAX(orders.shipping_email) as email'),
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('COALESCE(SUM(orders.total), 0) as revenue')
            )
            ->groupBy('orders.customer_id')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();
    }

    /* ==================== ORDERS ==================== */

    public function orderSummary(ReportFilters $f): array
    {
        $q = $this->baseOrderQuery($f);
        $total = (clone $q)->count();
        $byStatus = (clone $q)
            ->selectRaw('orders.status, COUNT(*) as c')
            ->groupBy('orders.status')
            ->pluck('c', 'status')
            ->toArray();

        return [
            'total' => (int) $total,
            'pending' => (int) ($byStatus['pending'] ?? 0),
            'confirmed' => (int) ($byStatus['confirmed'] ?? 0),
            'delivered' => (int) ($byStatus['delivered'] ?? 0),
            'canceled' => (int) ($byStatus['canceled'] ?? 0),
            'by_status' => $byStatus,
        ];
    }

    public function ordersPaginated(ReportFilters $f, int $perPage = 25, string $sort = 'created_at', string $dir = 'desc')
    {
        $allowedSort = ['created_at', 'total', 'status', 'order_no'];
        if (! in_array($sort, $allowedSort, true)) $sort = 'created_at';
        $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';

        return $this->baseOrderQuery($f)
            ->with([
                'payment:id,order_id,type,status,amount,refunded_amount',
                // latestShipment uses `latestOfMany` which introduces a join alias — a
                // column-constrained select on it triggers "ambiguous column" errors
                // on MySQL, so we load all columns (schema is small).
                'latestShipment',
            ])
            ->withCount('orderItems')
            ->orderBy("orders.$sort", $dir)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function ordersForExport(ReportFilters $f)
    {
        return $this->baseOrderQuery($f)
            ->with([
                'payment:id,order_id,type,status,amount,refunded_amount',
                'latestShipment',
            ])
            ->withCount('orderItems')
            ->orderBy('orders.id');
    }

    /* ==================== PAYMENTS ==================== */

    public function paymentSummary(ReportFilters $f): array
    {
        $q = $this->basePaymentQuery($f);

        $agg = (clone $q)
            ->selectRaw('
                COUNT(*) as tx_count,
                COALESCE(SUM(payments.amount), 0)         as gross,
                COALESCE(SUM(payments.refunded_amount), 0) as refunds,
                COALESCE(SUM(payments.gateway_fee), 0)     as fees
            ')
            ->first();

        $byStatus = (clone $q)
            ->selectRaw('payments.status, COUNT(*) as c, COALESCE(SUM(payments.amount),0) as amt')
            ->groupBy('payments.status')
            ->get()
            ->keyBy('status');

        $byType = (clone $q)
            ->selectRaw('payments.type, COUNT(*) as c, COALESCE(SUM(payments.amount),0) as amt')
            ->groupBy('payments.type')
            ->get();

        return [
            'tx_count' => (int) ($agg->tx_count ?? 0),
            'gross' => (float) ($agg->gross ?? 0),
            'refunds' => (float) ($agg->refunds ?? 0),
            'fees' => (float) ($agg->fees ?? 0),
            'net' => (float) (($agg->gross ?? 0) - ($agg->refunds ?? 0) - ($agg->fees ?? 0)),
            'paid_count' => (int) ($byStatus['paid']->c ?? 0),
            'failed_count' => (int) ($byStatus['failed']->c ?? 0),
            'pending_count' => (int) ($byStatus['pending']->c ?? 0),
            'by_type' => $byType->map(fn ($r) => [
                'type' => $r->type,
                'count' => (int) $r->c,
                'amount' => (float) $r->amt,
            ])->values()->all(),
        ];
    }

    public function paymentTrend(ReportFilters $f): array
    {
        $bucket = $this->groupExpression($f->group ?? 'day', 'payments.created_at');
        $rows = $this->basePaymentQuery($f)
            ->selectRaw("{$bucket} as bucket, COUNT(*) as c, COALESCE(SUM(payments.amount),0) as gross, COALESCE(SUM(payments.refunded_amount),0) as refunds, COALESCE(SUM(payments.gateway_fee),0) as fees")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        return $this->fillTrendGaps($rows, $f, ['c' => 0, 'gross' => 0.0, 'refunds' => 0.0, 'fees' => 0.0]);
    }

    public function paymentsPaginated(ReportFilters $f, int $perPage = 25, string $sort = 'created_at', string $dir = 'desc')
    {
        $allowed = ['created_at', 'amount', 'status', 'type'];
        if (! in_array($sort, $allowed, true)) $sort = 'created_at';
        $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';

        return $this->basePaymentQuery($f)
            ->with(['order:id,order_no,customer_id,shipping_name'])
            ->orderBy("payments.$sort", $dir)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paymentsForExport(ReportFilters $f)
    {
        return $this->basePaymentQuery($f)
            ->with(['order:id,order_no,shipping_name'])
            ->orderBy('payments.id');
    }

    /* ==================== SHIPPING ==================== */

    public function shippingSummary(ReportFilters $f): array
    {
        $q = $this->baseShipmentQuery($f);

        $agg = (clone $q)
            ->selectRaw('
                COUNT(*) as shipments_count,
                COALESCE(SUM(shipments.weight), 0) as total_weight
            ')
            ->first();

        $delivered = (clone $q)->where('shipments.status', 'delivered')->count();
        $inTransit = (clone $q)->whereIn('shipments.status', ['in_transit', 'out_for_delivery', 'picked_up', 'awb_assigned', 'pickup_scheduled'])->count();
        $pending = (clone $q)->whereIn('shipments.status', ['pending'])->count();
        $rto = (clone $q)->whereIn('shipments.status', ['rto_initiated', 'rto_delivered', 'undelivered'])->count();
        $cancelled = (clone $q)->where('shipments.status', 'cancelled')->count();

        // Shipping revenue = orders.shipping for orders that have at least one
        // shipment in the filter's scope. Not perfect for split shipments but
        // matches the storefront's single-shipment assumption.
        $shippingRevenue = (float) (clone $q)
            ->join('orders as o', 'o.id', '=', 'shipments.order_id')
            ->selectRaw('COALESCE(SUM(o.shipping), 0) as s')
            ->value('s');

        return [
            'shipments' => (int) ($agg->shipments_count ?? 0),
            'weight_kg' => (float) ($agg->total_weight ?? 0),
            'delivered' => (int) $delivered,
            'in_transit' => (int) $inTransit,
            'pending' => (int) $pending,
            'rto' => (int) $rto,
            'cancelled' => (int) $cancelled,
            'shipping_revenue' => $shippingRevenue,
        ];
    }

    public function shipmentsByCourier(ReportFilters $f): Collection
    {
        return $this->baseShipmentQuery($f)
            ->selectRaw('
                shipments.provider,
                MAX(shipments.courier_name) as courier_name,
                COUNT(*) as total,
                SUM(CASE WHEN shipments.status = "delivered" THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN shipments.status IN ("rto_initiated","rto_delivered","undelivered") THEN 1 ELSE 0 END) as rto
            ')
            ->groupBy('shipments.provider')
            ->orderByDesc('total')
            ->get();
    }

    public function shipmentTrend(ReportFilters $f): array
    {
        $bucket = $this->groupExpression($f->group ?? 'day', 'shipments.created_at');
        $rows = $this->baseShipmentQuery($f)
            ->selectRaw("{$bucket} as bucket, COUNT(*) as c, SUM(CASE WHEN shipments.status='delivered' THEN 1 ELSE 0 END) as delivered")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        return $this->fillTrendGaps($rows, $f, ['c' => 0, 'delivered' => 0]);
    }

    public function shipmentsPaginated(ReportFilters $f, int $perPage = 25, string $sort = 'created_at', string $dir = 'desc')
    {
        $allowed = ['created_at', 'status', 'shipped_at', 'delivered_at'];
        if (! in_array($sort, $allowed, true)) $sort = 'created_at';
        $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';

        return $this->baseShipmentQuery($f)
            ->with(['order:id,order_no,total,shipping_name,shipping_city,shipping_pincode'])
            ->orderBy("shipments.$sort", $dir)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function shipmentsForExport(ReportFilters $f)
    {
        return $this->baseShipmentQuery($f)
            ->with(['order:id,order_no,shipping_name,shipping_city,shipping_pincode'])
            ->orderBy('shipments.id');
    }

    /* ==================== P&L ==================== */

    /**
     * Full Profit & Loss statement for the filter's date window.
     *
     * Definitions:
     *   Revenue         = SUM(orders.total)  — already net of discount
     *                     (checkout stores total = sub_total + shipping - discount)
     *   COGS            = SUM(order_items.quantity * effective_cost_price)
     *   Shipping Cost   = SUM(orders.shipping) * services.reports.shipping_cost_ratio
     *   Gateway Fees    = SUM(payments.gateway_fee)  (missing rows fall back to
     *                     services.reports.default_gateway_fee_rate applied to amount)
     *   Discounts       = SUM(orders.discount)       — informational only; already
     *                     baked into Revenue, so NOT subtracted again below.
     *   Refunds         = SUM(payments.refunded_amount)
     *   Taxes Collected = SUM(orders.tax_amount)     (informational; pass-through)
     *   Other (order)   = SUM(orders.other_expense)
     *   Other (period)  = SUM(expenses.amount)  in date range
     *
     *   Gross Profit    = Revenue - COGS
     *   Op. Profit      = Gross Profit - Shipping Cost - Other (order) - Other (period)
     *   Net Profit      = Op. Profit - Gateway Fees - Refunds
     *   Margin (%)      = Net Profit / Revenue * 100
     */
    public function pnl(ReportFilters $f): array
    {
        $q = $this->baseOrderQuery($f);

        $orderAgg = (clone $q)
            ->selectRaw('
                COUNT(*) as orders_count,
                COALESCE(SUM(orders.total), 0)          as revenue,
                COALESCE(SUM(orders.shipping), 0)       as shipping_revenue,
                COALESCE(SUM(orders.discount), 0)       as discounts,
                COALESCE(SUM(orders.tax_amount), 0)     as taxes,
                COALESCE(SUM(orders.other_expense), 0)  as other_order
            ')
            ->first();

        // COGS: join order_items → coalesce variant.cost_price, product.cost_price
        $cogs = (float) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->when($f->from, fn ($q, $d) => $q->where('orders.created_at', '>=', $d))
            ->when($f->to, fn ($q, $d) => $q->where('orders.created_at', '<=', $d))
            ->when($f->status, fn ($q, $s) => $q->where('orders.status', $s))
            ->when($f->customerId, fn ($q, $v) => $q->where('orders.customer_id', $v))
            ->whereNull('orders.deleted_at')
            ->selectRaw('COALESCE(SUM(order_items.quantity * COALESCE(product_variants.cost_price, products.cost_price, 0)), 0) as cogs')
            ->value('cogs');

        // Payments — filter by order date-range window to align with revenue
        $payAgg = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->when($f->from, fn ($q, $d) => $q->where('orders.created_at', '>=', $d))
            ->when($f->to, fn ($q, $d) => $q->where('orders.created_at', '<=', $d))
            ->when($f->status, fn ($q, $s) => $q->where('orders.status', $s))
            ->when($f->customerId, fn ($q, $v) => $q->where('orders.customer_id', $v))
            ->whereNull('orders.deleted_at')
            ->selectRaw('
                COALESCE(SUM(payments.amount), 0)          as paid_gross,
                COALESCE(SUM(payments.refunded_amount), 0) as refunds,
                COALESCE(SUM(payments.gateway_fee), 0)     as fees_direct,
                COALESCE(SUM(CASE WHEN payments.gateway_fee = 0 AND payments.status IN ("paid","partially_refunded") THEN payments.amount ELSE 0 END), 0) as fees_fallback_base
            ')
            ->first();

        $defaultFeeRate = (float) config('services.reports.default_gateway_fee_rate', 0.02);
        $shippingCostRatio = (float) config('services.reports.shipping_cost_ratio', 1.0);

        $fees = (float) $payAgg->fees_direct + (float) $payAgg->fees_fallback_base * $defaultFeeRate;

        // Period expenses
        $periodExpenses = (float) Expense::query()
            ->inRange(optional($f->from)->toDateString(), optional($f->to)->toDateString())
            ->sum('amount');

        $revenue = (float) $orderAgg->revenue;
        $shippingCost = (float) $orderAgg->shipping_revenue * $shippingCostRatio;
        $discounts = (float) $orderAgg->discounts;
        $refunds = (float) $payAgg->refunds;
        $taxes = (float) $orderAgg->taxes;
        $otherOrder = (float) $orderAgg->other_order;

        // Revenue = SUM(orders.total) is already net of discount (checkout stores
        // total = sub_total + shipping - discount). Subtracting $discounts here
        // would double-count the discount — e.g. a ₹2700 cart with a ₹50 coupon
        // pays ₹2650, and gross should be 2650 - COGS, not 2650 - COGS - 50.
        $gross = $revenue - $cogs;
        $operating = $gross - $shippingCost - $otherOrder - $periodExpenses;
        $net = $operating - $fees - $refunds;
        $margin = $revenue > 0 ? ($net / $revenue) * 100 : 0.0;

        return [
            'orders_count' => (int) $orderAgg->orders_count,
            'revenue' => round($revenue, 2),
            'cogs' => round($cogs, 2),
            'shipping_cost' => round($shippingCost, 2),
            'gateway_fees' => round($fees, 2),
            'discounts' => round($discounts, 2),
            'refunds' => round($refunds, 2),
            'taxes_collected' => round($taxes, 2),
            'other_order_expense' => round($otherOrder, 2),
            'other_period_expense' => round($periodExpenses, 2),
            'gross_profit' => round($gross, 2),
            'operating_profit' => round($operating, 2),
            'net_profit' => round($net, 2),
            'margin_pct' => round($margin, 2),
            'assumptions' => [
                'shipping_cost_ratio' => $shippingCostRatio,
                'default_gateway_fee_rate' => $defaultFeeRate,
                'currency' => config('services.reports.currency', 'INR'),
                'currency_symbol' => config('services.reports.currency_symbol', '₹'),
            ],
        ];
    }

    public function pnlTrend(ReportFilters $f): array
    {
        $bucket = $this->groupExpression($f->group ?? 'day', 'orders.created_at');
        $rows = $this->baseOrderQuery($f)
            ->selectRaw("
                {$bucket} as bucket,
                COALESCE(SUM(orders.total), 0)     as revenue,
                COALESCE(SUM(orders.discount), 0)  as discounts,
                COALESCE(SUM(orders.shipping), 0)  as shipping_revenue
            ")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        return $this->fillTrendGaps($rows, $f, ['revenue' => 0.0, 'discounts' => 0.0, 'shipping_revenue' => 0.0]);
    }

    /* ==================== INTERNAL ==================== */

    protected function baseOrderQuery(ReportFilters $f): Builder
    {
        /** @var Builder $q */
        $q = Order::query();
        $q->from('orders');

        $q->when($f->from, fn ($q, $d) => $q->where('orders.created_at', '>=', $d));
        $q->when($f->to, fn ($q, $d) => $q->where('orders.created_at', '<=', $d));
        $q->when($f->status, fn ($q, $s) => $q->where('orders.status', $s));
        $q->when($f->customerId, fn ($q, $v) => $q->where('orders.customer_id', $v));

        if ($f->paymentType || $f->paymentStatus) {
            $q->whereHas('payment', function ($qq) use ($f) {
                if ($f->paymentType) $qq->where('type', $f->paymentType);
                if ($f->paymentStatus) $qq->where('status', $f->paymentStatus);
            });
        }

        if ($f->courierId) {
            $q->whereHas('shipments', fn ($qq) => $qq->where('delivery_partner_id', $f->courierId));
        }

        if ($f->productId || $f->categoryId || $f->brand) {
            $q->whereExists(function ($sub) use ($f) {
                $sub->select(DB::raw(1))
                    ->from('order_items')
                    ->whereColumn('order_items.order_id', 'orders.id');

                if ($f->productId) {
                    $sub->where('order_items.product_id', $f->productId);
                }

                if ($f->categoryId) {
                    $sub->join('product_categories', 'product_categories.product_id', '=', 'order_items.product_id')
                        ->where('product_categories.category_id', $f->categoryId);
                }

                if ($f->brand) {
                    $sub->join('products', 'products.id', '=', 'order_items.product_id')
                        ->where('products.brand', $f->brand);
                }
            });
        }

        if ($f->search) {
            $s = $f->search;
            $q->where(function ($qq) use ($s) {
                $qq->where('orders.order_no', 'like', "%{$s}%")
                    ->orWhere('orders.shipping_name', 'like', "%{$s}%")
                    ->orWhere('orders.shipping_email', 'like', "%{$s}%")
                    ->orWhere('orders.shipping_phone', 'like', "%{$s}%");
            });
        }

        return $q;
    }

    protected function basePaymentQuery(ReportFilters $f): Builder
    {
        /** @var Builder $q */
        $q = Payment::query();
        $q->from('payments');

        $q->when($f->from, fn ($q, $d) => $q->where('payments.created_at', '>=', $d));
        $q->when($f->to, fn ($q, $d) => $q->where('payments.created_at', '<=', $d));
        $q->when($f->paymentStatus, fn ($q, $s) => $q->where('payments.status', $s));
        $q->when($f->paymentType, fn ($q, $t) => $q->where('payments.type', $t));

        if ($f->status || $f->customerId || $f->search) {
            $q->whereHas('order', function ($qq) use ($f) {
                if ($f->status) $qq->where('status', $f->status);
                if ($f->customerId) $qq->where('customer_id', $f->customerId);
                if ($f->search) {
                    $s = $f->search;
                    $qq->where(function ($x) use ($s) {
                        $x->where('order_no', 'like', "%{$s}%")
                            ->orWhere('shipping_name', 'like', "%{$s}%")
                            ->orWhere('shipping_email', 'like', "%{$s}%");
                    });
                }
            });
        }

        return $q;
    }

    protected function baseShipmentQuery(ReportFilters $f): Builder
    {
        /** @var Builder $q */
        $q = Shipment::query();
        $q->from('shipments');

        $q->when($f->from, fn ($q, $d) => $q->where('shipments.created_at', '>=', $d));
        $q->when($f->to, fn ($q, $d) => $q->where('shipments.created_at', '<=', $d));
        $q->when($f->courierId, fn ($q, $v) => $q->where('shipments.delivery_partner_id', $v));

        if ($f->status || $f->customerId || $f->search) {
            $q->whereHas('order', function ($qq) use ($f) {
                if ($f->status) $qq->where('status', $f->status);
                if ($f->customerId) $qq->where('customer_id', $f->customerId);
                if ($f->search) {
                    $s = $f->search;
                    $qq->where(function ($x) use ($s) {
                        $x->where('order_no', 'like', "%{$s}%")
                            ->orWhere('shipping_name', 'like', "%{$s}%")
                            ->orWhere('shipping_phone', 'like', "%{$s}%");
                    });
                }
            });
        }

        return $q;
    }

    protected function groupExpression(string $group, string $column): string
    {
        return match ($group) {
            'week' => "DATE_FORMAT(MIN({$column}), '%x-W%v')",
            'month' => "DATE_FORMAT({$column}, '%Y-%m')",
            default => "DATE({$column})",
        };
    }

    /**
     * Ensure every bucket in the range appears in the trend, even if the DB
     * returned zero rows for that bucket. Keeps charts continuous.
     */
    protected function fillTrendGaps(Collection $rows, ReportFilters $f, array $defaults): array
    {
        if (! $f->from || ! $f->to) {
            return $rows->map(fn ($r) => (array) $r)->all();
        }

        $rowsByBucket = $rows->keyBy('bucket');
        $out = [];
        $cursor = $f->from->copy();
        $group = $f->group ?? 'day';

        while ($cursor->lte($f->to)) {
            $key = match ($group) {
                'week' => $cursor->format('o-\WW'),
                'month' => $cursor->format('Y-m'),
                default => $cursor->format('Y-m-d'),
            };
            $row = $rowsByBucket->get($key);
            $entry = ['bucket' => $key, 'label' => $this->formatBucketLabel($cursor, $group)];
            foreach ($defaults as $k => $def) {
                $entry[$k] = $row ? (isset($row->{$k}) ? $row->{$k} : $def) : $def;
            }
            $out[] = $entry;

            $cursor = match ($group) {
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonth(),
                default => $cursor->addDay(),
            };
        }

        return $out;
    }

    protected function formatBucketLabel(Carbon $c, string $group): string
    {
        return match ($group) {
            'week' => 'W' . $c->format('W') . ' ' . $c->format('o'),
            'month' => $c->format('M Y'),
            default => $c->format('d M'),
        };
    }
}
