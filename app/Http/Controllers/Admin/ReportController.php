<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DeliveryPartner;
use App\Models\PaymentGateway;
use App\Services\Reports\ReportExporter;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Admin Reports module — Sales / Orders / Payments / Shipping / P&L.
 *
 * Each report has:
 *   - a paginated Inertia view (KPIs + chart series + table)
 *   - an export endpoint (?format=csv|xlsx|pdf) reusing the same filters
 *
 * Legacy CSV routes (`orders.csv`, `payments.csv`) are preserved as aliases
 * onto the new exporter so nothing in the wild breaks.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly ReportExporter $exporter,
    ) {}

    /* --------------------- Page actions --------------------- */

    public function sales(Request $request): Response
    {
        $filters = ReportFilters::fromRequest($request);
        $prev = $filters->previousPeriod();

        return Inertia::render('Admin/Reports/Sales', [
            'filters' => $filters->toArray(),
            'filterOptions' => $this->sharedFilterOptions(),
            'summary' => $this->reports->salesSummary($filters),
            'previousSummary' => $this->reports->salesSummary($prev),
            'trend' => $this->reports->salesTrend($filters),
            'topProducts' => $this->reports->topProducts($filters, 10),
            'topCategories' => $this->reports->topCategories($filters, 10),
            'topCustomers' => $this->reports->topCustomers($filters, 10),
        ]);
    }

    public function orders(Request $request): Response|SymfonyResponse
    {
        // Backwards-compat: the old `admin.reports.orders` route was a direct
        // CSV. If the request explicitly asks for a format, honour it (works
        // for both the new Inertia page's export buttons AND legacy links).
        if ($this->requestedFormat($request)) {
            return $this->exportOrders($request);
        }

        $filters = ReportFilters::fromRequest($request);
        $prev = $filters->previousPeriod();
        $perPage = (int) $request->integer('per_page', 25);
        $perPage = max(10, min(100, $perPage));
        $sort = $request->string('sort', 'created_at')->toString();
        $dir = $request->string('dir', 'desc')->toString();

        return Inertia::render('Admin/Reports/Orders', [
            'filters' => $filters->toArray(),
            'filterOptions' => $this->sharedFilterOptions(),
            'summary' => $this->reports->orderSummary($filters),
            'previousSummary' => $this->reports->orderSummary($prev),
            'trend' => $this->reports->salesTrend($filters),
            'rows' => $this->reports->ordersPaginated($filters, $perPage, $sort, $dir),
            'sort' => ['field' => $sort, 'dir' => $dir],
            'perPage' => $perPage,
        ]);
    }

    public function payments(Request $request): Response|SymfonyResponse
    {
        if ($this->requestedFormat($request)) {
            return $this->exportPayments($request);
        }

        $filters = ReportFilters::fromRequest($request);
        $prev = $filters->previousPeriod();
        $perPage = (int) $request->integer('per_page', 25);
        $perPage = max(10, min(100, $perPage));
        $sort = $request->string('sort', 'created_at')->toString();
        $dir = $request->string('dir', 'desc')->toString();

        return Inertia::render('Admin/Reports/Payments', [
            'filters' => $filters->toArray(),
            'filterOptions' => $this->sharedFilterOptions(),
            'summary' => $this->reports->paymentSummary($filters),
            'previousSummary' => $this->reports->paymentSummary($prev),
            'trend' => $this->reports->paymentTrend($filters),
            'rows' => $this->reports->paymentsPaginated($filters, $perPage, $sort, $dir),
            'sort' => ['field' => $sort, 'dir' => $dir],
            'perPage' => $perPage,
        ]);
    }

    public function shipping(Request $request): Response|SymfonyResponse
    {
        if ($this->requestedFormat($request)) {
            return $this->exportShipments($request);
        }

        $filters = ReportFilters::fromRequest($request);
        $prev = $filters->previousPeriod();
        $perPage = (int) $request->integer('per_page', 25);
        $perPage = max(10, min(100, $perPage));
        $sort = $request->string('sort', 'created_at')->toString();
        $dir = $request->string('dir', 'desc')->toString();

        return Inertia::render('Admin/Reports/Shipping', [
            'filters' => $filters->toArray(),
            'filterOptions' => $this->sharedFilterOptions(),
            'summary' => $this->reports->shippingSummary($filters),
            'previousSummary' => $this->reports->shippingSummary($prev),
            'byCourier' => $this->reports->shipmentsByCourier($filters),
            'trend' => $this->reports->shipmentTrend($filters),
            'rows' => $this->reports->shipmentsPaginated($filters, $perPage, $sort, $dir),
            'sort' => ['field' => $sort, 'dir' => $dir],
            'perPage' => $perPage,
        ]);
    }

    public function pnl(Request $request): Response|SymfonyResponse
    {
        if ($this->requestedFormat($request)) {
            return $this->exportPnl($request);
        }

        $filters = ReportFilters::fromRequest($request);
        $prev = $filters->previousPeriod();

        return Inertia::render('Admin/Reports/Pnl', [
            'filters' => $filters->toArray(),
            'filterOptions' => $this->sharedFilterOptions(),
            'pnl' => $this->reports->pnl($filters),
            'previousPnl' => $this->reports->pnl($prev),
            'trend' => $this->reports->pnlTrend($filters),
        ]);
    }

    /* --------------------- Exports --------------------- */

    public function exportOrders(Request $request): SymfonyResponse
    {
        $filters = ReportFilters::fromRequest($request);
        $format = $this->requestedFormat($request) ?? 'csv';
        $headers = [
            'Order No', 'Placed', 'Customer', 'Email', 'Phone',
            'City', 'State', 'Pincode', 'Items',
            'Subtotal', 'Shipping', 'Discount', 'Tax', 'Total',
            'Status', 'Payment Type', 'Payment Status', 'Refunded',
            'Courier', 'AWB', 'Shipment Status',
        ];
        $mapper = fn ($o) => [
            $o->order_no,
            optional($o->created_at)->format('Y-m-d H:i'),
            $o->shipping_name,
            $o->shipping_email,
            $o->shipping_phone,
            $o->shipping_city,
            $o->shipping_state,
            $o->shipping_pincode,
            $o->order_items_count,
            $this->money($o->sub_total),
            $this->money($o->shipping),
            $this->money($o->discount),
            $this->money($o->tax_amount ?? 0),
            $this->money($o->total),
            $o->status,
            optional($o->payment)->type,
            optional($o->payment)->status,
            $this->money(optional($o->payment)->refunded_amount ?? 0),
            optional($o->latestShipment)->courier_name,
            optional($o->latestShipment)->awb_code,
            optional($o->latestShipment)->status,
        ];
        $query = $this->reports->ordersForExport($filters);

        if ($format === 'xlsx') return $this->exporter->streamXlsx('orders', $headers, $query, $mapper);

        if ($format === 'pdf' || $format === 'print') {
            // 21 columns don't fit A4 landscape at any readable size. Collapse
            // related fields into stacked cells (name+email+phone → Customer,
            // city+state+pincode → Address, etc.) so everything is visible.
            $pdfHeaders = [
                'Order No', 'Date', 'Customer', 'Address', 'Items',
                'Subtotal', 'Discount', 'Total', 'Status', 'Payment', 'Refunded', 'Shipment',
            ];
            $pdfMapper = function ($o) {
                $addressLine = trim(($o->shipping_city ?? '') . ', ' . ($o->shipping_state ?? ''), ', ');
                $customer  = implode("\n", array_filter([$o->shipping_name, $o->shipping_email, $o->shipping_phone]));
                $address   = implode("\n", array_filter([$addressLine, $o->shipping_pincode]));
                $payment   = implode("\n", array_filter([optional($o->payment)->type, optional($o->payment)->status]));
                $shipment  = implode("\n", array_filter([
                    optional($o->latestShipment)->courier_name,
                    optional($o->latestShipment)->awb_code,
                    optional($o->latestShipment)->status,
                ]));
                return [
                    $o->order_no,
                    optional($o->created_at)->format('Y-m-d H:i'),
                    $customer,
                    $address,
                    $o->order_items_count,
                    $this->money($o->sub_total),
                    $this->money($o->discount),
                    $this->money($o->total),
                    $o->status,
                    $payment,
                    $this->money(optional($o->payment)->refunded_amount ?? 0),
                    $shipment,
                ];
            };
            return $this->renderReportPdfOrPrint(
                $format, 'orders', 'Orders Report', $filters->toArray(), $pdfHeaders,
                $query->limit(2000)->get()->map($pdfMapper)->all(),
            );
        }
        return $this->exporter->streamCsv('orders', $headers, $query, $mapper);
    }

    public function exportPayments(Request $request): SymfonyResponse
    {
        $filters = ReportFilters::fromRequest($request);
        $format = $this->requestedFormat($request) ?? 'csv';
        $headers = [
            'Payment ID', 'Order No', 'Customer', 'Type', 'Status',
            'Amount', 'Fee', 'Refunded', 'Refund ID', 'Refunded At',
            'Failure Reason', 'Created',
        ];
        $mapper = fn ($p) => [
            $p->payment_id,
            optional($p->order)->order_no,
            optional($p->order)->shipping_name,
            $p->type,
            $p->status,
            $this->money($p->amount),
            $this->money($p->gateway_fee ?? 0),
            $this->money($p->refunded_amount ?? 0),
            $p->refund_id,
            optional($p->refunded_at)->format('Y-m-d H:i'),
            $p->failure_reason,
            optional($p->created_at)->format('Y-m-d H:i'),
        ];
        $query = $this->reports->paymentsForExport($filters);

        if ($format === 'xlsx') return $this->exporter->streamXlsx('payments', $headers, $query, $mapper);
        if ($format === 'pdf' || $format === 'print') {
            return $this->renderReportPdfOrPrint(
                $format, 'payments', 'Payments Report', $filters->toArray(), $headers,
                $query->limit(2000)->get()->map($mapper)->all(),
            );
        }
        return $this->exporter->streamCsv('payments', $headers, $query, $mapper);
    }

    public function exportShipments(Request $request): SymfonyResponse
    {
        $filters = ReportFilters::fromRequest($request);
        $format = $this->requestedFormat($request) ?? 'csv';
        $headers = [
            'Shipment ID', 'Order No', 'Provider', 'Courier', 'AWB', 'Status',
            'Weight', 'City', 'Pincode', 'Shipped At', 'Delivered At', 'Created',
        ];
        $mapper = fn ($s) => [
            $s->id,
            optional($s->order)->order_no,
            $s->provider,
            $s->courier_name,
            $s->awb_code,
            $s->status,
            $s->weight,
            optional($s->order)->shipping_city,
            optional($s->order)->shipping_pincode,
            optional($s->shipped_at)->format('Y-m-d H:i'),
            optional($s->delivered_at)->format('Y-m-d H:i'),
            optional($s->created_at)->format('Y-m-d H:i'),
        ];
        $query = $this->reports->shipmentsForExport($filters);

        if ($format === 'xlsx') return $this->exporter->streamXlsx('shipments', $headers, $query, $mapper);
        if ($format === 'pdf' || $format === 'print') {
            return $this->renderReportPdfOrPrint(
                $format, 'shipments', 'Shipping Report', $filters->toArray(), $headers,
                $query->limit(2000)->get()->map($mapper)->all(),
            );
        }
        return $this->exporter->streamCsv('shipments', $headers, $query, $mapper);
    }

    public function exportPnl(Request $request): SymfonyResponse
    {
        $filters = ReportFilters::fromRequest($request);
        $format = $this->requestedFormat($request) ?? 'csv';
        $pnl = $this->reports->pnl($filters);

        $headers = ['Metric', 'Amount'];
        $rows = [
            ['Revenue', $this->money($pnl['revenue'])],
            ['Product Cost (COGS)', $this->money($pnl['cogs'])],
            ['Discounts', $this->money($pnl['discounts'])],
            ['Gross Profit', $this->money($pnl['gross_profit'])],
            ['Shipping Cost', $this->money($pnl['shipping_cost'])],
            ['Other Expenses (Orders)', $this->money($pnl['other_order_expense'])],
            ['Other Expenses (Period)', $this->money($pnl['other_period_expense'])],
            ['Operating Profit', $this->money($pnl['operating_profit'])],
            ['Gateway Fees', $this->money($pnl['gateway_fees'])],
            ['Refunds', $this->money($pnl['refunds'])],
            ['Net Profit', $this->money($pnl['net_profit'])],
            ['Profit Margin (%)', number_format($pnl['margin_pct'], 2)],
            ['Taxes Collected (info)', $this->money($pnl['taxes_collected'])],
        ];

        if ($format === 'xlsx') {
            return $this->exporter->streamXlsx('pnl', $headers,
                new class ($rows) {
                    public function __construct(private array $rows) {}
                    public function chunk(int $size, \Closure $cb): void { $cb(collect($this->rows)); }
                },
                fn ($r) => $r
            );
        }
        if ($format === 'pdf' || $format === 'print') {
            return $this->renderReportPdfOrPrint(
                $format, 'pnl', 'Profit & Loss Report', $filters->toArray(), $headers, $rows,
            );
        }
        // CSV
        return $this->exporter->streamCsv('pnl', $headers,
            new class ($rows) {
                public function __construct(private array $rows) {}
                public function chunk(int $size, \Closure $cb): void { $cb(collect($this->rows)); }
            },
            fn ($r) => $r
        );
    }

    /* --------------------- Legacy aliases (BC) --------------------- */

    public function ordersCsvLegacy(Request $request): SymfonyResponse
    {
        return $this->exportOrders($request->merge(['format' => 'csv']));
    }

    public function paymentsCsvLegacy(Request $request): SymfonyResponse
    {
        return $this->exportPayments($request->merge(['format' => 'csv']));
    }

    /* --------------------- Helpers --------------------- */

    /**
     * Options fed to every report's filter bar — kept small (id + label) and
     * loaded once per request.
     */
    private function sharedFilterOptions(): array
    {
        return [
            'statuses' => ['pending', 'confirmed', 'delivered', 'canceled'],
            'paymentStatuses' => ['pending', 'paid', 'failed', 'partially_refunded', 'refunded'],
            'paymentTypes' => PaymentGateway::query()->orderBy('name')->get(['code as value', 'name as label'])->toArray(),
            'couriers' => DeliveryPartner::query()->orderBy('name')->get(['id as value', 'name as label'])->toArray(),
            'categories' => Category::query()->orderBy('name')->get(['id as value', 'name as label'])->toArray(),
            'brands' => DB::table('products')
                ->whereNotNull('brand')->where('brand', '!=', '')
                ->distinct()->orderBy('brand')
                ->pluck('brand')->map(fn ($b) => ['value' => $b, 'label' => $b])->values()->all(),
            'presets' => [
                ['value' => 'today', 'label' => 'Today'],
                ['value' => 'yesterday', 'label' => 'Yesterday'],
                ['value' => 'last_7', 'label' => 'Last 7 days'],
                ['value' => 'last_30', 'label' => 'Last 30 days'],
                ['value' => 'mtd', 'label' => 'Month to date'],
                ['value' => 'ytd', 'label' => 'Year to date'],
                ['value' => 'custom', 'label' => 'Custom range'],
            ],
            'groups' => [
                ['value' => 'day', 'label' => 'Daily'],
                ['value' => 'week', 'label' => 'Weekly'],
                ['value' => 'month', 'label' => 'Monthly'],
            ],
        ];
    }

    private function requestedFormat(Request $request): ?string
    {
        $format = strtolower((string) $request->query('format'));
        return in_array($format, ['csv', 'xlsx', 'pdf', 'print'], true) ? $format : null;
    }

    /**
     * Dispatch the shared printable payload to either a real PDF download
     * (?format=pdf) or the printable HTML view (?format=print).
     */
    private function renderReportPdfOrPrint(string $format, string $slug, string $title, array $filters, array $headers, array $rows): SymfonyResponse
    {
        $data = compact('title', 'filters', 'headers', 'rows');
        if ($format === 'pdf') {
            return $this->exporter->streamPdf('admin.reports.print', $data, $slug);
        }
        return $this->exporter->renderPrintable('admin.reports.print', $data);
    }

    private function money($n): string
    {
        return number_format((float) $n, 2, '.', '');
    }
}
