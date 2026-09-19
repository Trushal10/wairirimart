@php
    // Layout tuning based on column count — the same template drives both the
    // browser-print view and the DomPDF export, so we pick a compact preset
    // when the report has many columns.
    $__pdf     = ! empty($__pdfMode);
    $__colCnt  = count($headers ?? []);
    $__dense   = $__colCnt >= 10;   // orders/payments/shipping
    $__ultra   = $__colCnt >= 15;   // orders (21 cols) — really cramped

    $__cellPad = $__ultra ? '4px 5px' : ($__dense ? '6px 8px' : '11px 14px');
    $__headPad = $__ultra ? '5px 5px' : ($__dense ? '7px 8px' : '11px 14px');
    $__fontPx  = $__ultra ? 8.5 : ($__dense ? 10 : 12.5);
    $__headPx  = $__ultra ? 8   : ($__dense ? 9  : 11);

    // Column-type detection — controllers pass raw arrays, so we sniff the
    // header labels to decide currency/numeric/status/mono styling.
    $currencyHeaders = ['Subtotal', 'Shipping', 'Discount', 'Tax', 'Total', 'Refunded', 'Amount', 'Revenue', 'Gross', 'Net', 'Cost', 'Profit', 'Loss', 'MRP', 'Sale Price', 'Price', 'Fee'];
    $numericHeaders  = ['Items', 'Qty', 'Quantity', 'Count', 'Orders', 'Customers', 'Products', 'Weight', 'Pincode'];
    $statusHeaders   = ['Status', 'Payment Status', 'Order Status', 'Shipment Status'];
    $monoHeaders     = ['Order No', 'AWB', 'SKU', 'Barcode', 'Order Id', 'Payment ID', 'Ref', 'Reference', 'Transaction Id', 'Shipment ID', 'Refund ID'];

    $colTypes = [];
    foreach ($headers as $i => $h) {
        $hStr = (string) $h;
        if (in_array($hStr, $currencyHeaders, true))       $colTypes[$i] = 'currency';
        elseif (in_array($hStr, $numericHeaders, true))    $colTypes[$i] = 'num';
        elseif (in_array($hStr, $statusHeaders, true))     $colTypes[$i] = 'status';
        elseif (in_array($hStr, $monoHeaders, true))       $colTypes[$i] = 'mono';
        else                                                $colTypes[$i] = 'text';
    }

    $statusVariant = function (string $s): string {
        $s = strtolower(trim($s));
        return match (true) {
            in_array($s, ['delivered', 'paid', 'success', 'succeeded', 'confirmed', 'captured', 'completed', 'active', 'refunded'], true) => 'success',
            in_array($s, ['pending', 'processing', 'partially_refunded', 'partially refunded', 'authorized', 'in_transit', 'in transit', 'out_for_delivery', 'out for delivery', 'picked_up', 'picked up'], true) => 'warning',
            in_array($s, ['canceled', 'cancelled', 'failed', 'error', 'declined', 'refund_failed', 'refund failed', 'rto', 'returned'], true) => 'error',
            in_array($s, ['shipped', 'created', 'placed', 'new', 'draft'], true) => 'info',
            default => 'neutral',
        };
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Report' }}</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        :root {
            color-scheme: light;
            --ink: #101828;
            --ink-2: #344054;
            --muted: #667085;
            --line: #eaecf0;
            --line-2: #f2f4f7;
            --brand: #465fff;
            --success-bg: #d1fadf;   --success-fg: #087443;
            --warning-bg: #fef0c7;   --warning-fg: #b54708;
            --error-bg: #fee4e2;     --error-fg: #b42318;
            --info-bg: #e0eaff;      --info-fg: #2d3fd4;
            --neutral-bg: #eef0f3;   --neutral-fg: #475467;
        }
        * { box-sizing: border-box; }
        body {
            font-family: {{ $__pdf ? "'DejaVu Sans', sans-serif" : "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" }};
            color: var(--ink);
            margin: 0;
            padding: {{ $__pdf ? '0' : '28px 24px' }};
            background: {{ $__pdf ? '#fff' : '#f5f7fa' }};
            font-size: {{ $__fontPx }}px;
            line-height: 1.4;
            -webkit-font-smoothing: antialiased;
        }
        .sheet {
            max-width: {{ $__pdf ? '100%' : ($__ultra ? '100%' : '1200px') }};
            margin: 0 auto;
            background: #fff;
            padding: {{ $__pdf ? '0' : ($__dense ? '20px 24px' : '36px 40px') }};
            border-radius: {{ $__pdf ? '0' : '12px' }};
            {!! $__pdf ? '' : 'box-shadow: 0 1px 3px rgba(16,24,40,.06), 0 4px 12px rgba(16,24,40,.04);' !!}
        }

        /* Header */
        header {
            display: table;
            width: 100%;
            padding-bottom: 12px;
            margin-bottom: 14px;
            border-bottom: 1px solid var(--line);
        }
        header .hleft, header .hright { display: table-cell; vertical-align: top; }
        header .hright { text-align: right; }
        header .brand {
            color: var(--brand);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .12em;
            font-weight: 700;
            margin-bottom: 4px;
        }
        header h1 {
            margin: 0;
            font-size: {{ $__ultra ? 16 : ($__dense ? 18 : 22) }}px;
            font-weight: 700;
            color: var(--ink);
            line-height: 1.2;
        }
        header .meta { font-size: 10.5px; color: var(--muted); }
        header .meta .row {
            display: inline-block;
            padding: 3px 9px;
            background: var(--line-2);
            border-radius: 999px;
            margin: 1px 0;
            font-weight: 500;
            color: var(--ink-2);
        }
        header .meta .row .k { color: var(--muted); font-weight: 400; margin-right: 3px; }

        /* Filter chips */
        .filters {
            padding: 10px 12px;
            margin-bottom: 12px;
            background: #f9fafb;
            border: 1px solid var(--line);
            border-radius: 8px;
        }
        .filters .chip {
            display: inline-block;
            padding: 3px 10px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 999px;
            font-size: 10px;
            color: var(--ink-2);
            margin: 2px 3px 2px 0;
        }
        .filters .chip .k { color: var(--muted); font-weight: 400; text-transform: capitalize; }
        .filters .chip .v { color: var(--ink); font-weight: 600; margin-left: 2px; }

        /* Table */
        .table-wrap { border: 1px solid var(--line); border-radius: 8px; overflow: hidden; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: {{ $__fontPx }}px;
            /* Fixed layout so columns share the page width evenly and
               nothing overflows the sheet in DomPDF. */
            table-layout: fixed;
        }
        thead th {
            background: #f9fafb;
            text-align: left;
            padding: {{ $__headPad }};
            font-weight: 700;
            font-size: {{ $__headPx }}px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--muted);
            border-bottom: 1px solid var(--line);
            white-space: nowrap;
            overflow: hidden;
        }
        thead th.num { text-align: right; }
        tbody td {
            padding: {{ $__cellPad }};
            border-bottom: 1px solid var(--line-2);
            vertical-align: top;
            color: var(--ink-2);
            /* overflow-wrap breaks long tokens (emails, order-nos) at the cell
               edge instead of char-by-char, which DomPDF does with word-break. */
            overflow-wrap: break-word;
            word-wrap: break-word;
        }
        tbody td.num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            color: var(--ink);
            font-weight: 500;
            white-space: nowrap;
        }
        tbody td.mono {
            font-family: {{ $__pdf ? "'Courier', 'DejaVu Sans Mono', monospace" : "'SF Mono', ui-monospace, monospace" }};
            font-size: {{ max($__fontPx - 0.5, 8) }}px;
            color: var(--ink);
        }
        tbody tr:last-child td { border-bottom: 0; }
        tbody tr.zebra td { background: #fbfcfd; }

        /* Status badges */
        .status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: {{ max($__fontPx - 1.5, 7.5) }}px;
            font-weight: 700;
            text-transform: capitalize;
            white-space: nowrap;
            background: var(--neutral-bg);
            color: var(--neutral-fg);
        }
        .status.success  { background: var(--success-bg); color: var(--success-fg); }
        .status.warning  { background: var(--warning-bg); color: var(--warning-fg); }
        .status.error    { background: var(--error-bg);   color: var(--error-fg);   }
        .status.info     { background: var(--info-bg);    color: var(--info-fg);    }

        /* Empty state */
        .empty {
            padding: 36px 24px;
            text-align: center;
            color: var(--muted);
            background: #fbfcfd;
        }
        .empty p { margin: 0; font-size: 12px; }

        @if (! $__pdf)
        /* Toolbar (screen only — never rendered in PDF) */
        .toolbar {
            margin: 16px auto 0;
            max-width: 1200px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 8px;
            background: var(--brand);
            color: #fff;
            border: 0;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn:hover { background: #3a52e5; }
        .btn.secondary { background: #fff; color: var(--ink-2); border: 1px solid var(--line); }
        .btn.secondary:hover { background: #f9fafb; }

        /* Print — match the browser print output to what the PDF looks like */
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; padding: 0; border-radius: 0; max-width: 100%; }
            .toolbar { display: none !important; }
            .table-wrap { border: 0; border-radius: 0; }
            thead th { background: #f9fafb !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            tbody tr.zebra td { background: #fbfcfd !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .status, .filters .chip, header .meta .row {
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
            @page { size: A4 landscape; margin: 8mm; }
        }
        @endif
    </style>
</head>
<body>

<div class="sheet">
    <header>
        <div class="hleft">
            <div class="brand">{{ config('app.name') !== 'Laravel' ? config('app.name') : ($settings->name ?? 'Store') }}</div>
            <h1>{{ $title ?? 'Report' }}</h1>
        </div>
        <div class="hright">
            <div class="meta">
                <div class="row"><span class="k">Generated:</span>{{ now()->format('d M Y, H:i') }}</div>
                @if (!empty($filters['from']) || !empty($filters['to']))
                    <div class="row"><span class="k">Range:</span>{{ $filters['from'] ?? '—' }} — {{ $filters['to'] ?? '—' }}</div>
                @endif
            </div>
        </div>
    </header>

    @php
        $displayFilters = collect($filters ?? [])
            ->filter(fn ($v, $k) => $v !== null && $v !== '' && ! in_array($k, ['from', 'to'], true))
            ->all();
    @endphp
    @if (!empty($displayFilters))
        <div class="filters">
            @foreach ($displayFilters as $k => $v)
                <span class="chip">
                    <span class="k">{{ str_replace('_', ' ', ucfirst($k)) }}:</span>
                    <span class="v">{{ is_scalar($v) ? $v : json_encode($v) }}</span>
                </span>
            @endforeach
        </div>
    @endif

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    @foreach ($headers as $i => $h)
                        <th class="{{ in_array($colTypes[$i], ['currency', 'num'], true) ? 'num' : '' }}">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $rowIdx => $r)
                    <tr class="{{ $rowIdx % 2 ? 'zebra' : '' }}">
                        @foreach (array_values((array) $r) as $i => $cell)
                            @php
                                $type = $colTypes[$i] ?? 'text';
                                $raw  = is_scalar($cell) ? (string) $cell : json_encode($cell);
                                // Cells may embed "\n" to stack sub-lines (e.g. Customer =
                                // name\nemail\nphone in the condensed Orders PDF). Convert
                                // to <br> after escaping so the layout still degrades safely.
                                $multi = str_contains($raw, "\n");
                            @endphp
                            @if ($type === 'currency')
                                <td class="num">@if ($raw === '' || $raw === null)—@else&#8377;{{ number_format((float) $raw, 2) }}@endif</td>
                            @elseif ($type === 'num')
                                <td class="num">{{ $raw === '' ? '—' : $raw }}</td>
                            @elseif ($type === 'status')
                                <td>
                                    @if ($raw === '' || $raw === null)—
                                    @else<span class="status {{ $statusVariant($raw) }}">{{ str_replace('_', ' ', $raw) }}</span>
                                    @endif
                                </td>
                            @elseif ($type === 'mono')
                                <td class="mono">
                                    @if ($raw === '') — @elseif ($multi) {!! nl2br(e($raw)) !!} @else {{ $raw }} @endif
                                </td>
                            @else
                                <td>
                                    @if ($raw === '') — @elseif ($multi) {!! nl2br(e($raw)) !!} @else {{ $raw }} @endif
                                </td>
                            @endif
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($headers) }}" class="empty">
                            <p>No records match the current filters.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@unless ($__pdf)
<div class="toolbar">
    <button class="btn secondary" onclick="window.close()">Close</button>
    <button class="btn" onclick="window.print()">Print / Save as PDF</button>
</div>

<script>
    // Auto-open the print dialog only when the "Print" button (?autoprint=1) was clicked.
    (function () {
        const shouldAutoPrint = new URLSearchParams(window.location.search).get('autoprint') === '1';
        if (!shouldAutoPrint) return;
        window.addEventListener('load', () => {
            setTimeout(() => { try { window.print(); } catch (_) {} }, 350);
        });
    })();
</script>
@endunless

</body>
</html>
