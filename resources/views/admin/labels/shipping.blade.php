<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shipping Label · {{ $order->order_no }}</title>
    <style>
        /* Layout is designed for a 100×150 mm (A6-ish) label. */
        @page { size: 100mm 150mm; margin: 0; }
        html, body { margin: 0; padding: 0; background: #eee; }
        body {
            font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            color: #111;
            font-size: 11px;
            line-height: 1.35;
        }
        .toolbar {
            position: sticky; top: 0; z-index: 10;
            background: #111; color: #fff;
            padding: 10px 16px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 10px;
            font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
        }
        .toolbar b { font-size: 13px; }
        .toolbar button, .toolbar a {
            background: #fff; color: #111; border: 0;
            padding: 6px 14px; border-radius: 6px;
            font-size: 12px; font-weight: 600; cursor: pointer;
            text-decoration: none;
        }
        .toolbar button:hover { background: #eee; }
        .toolbar .ghost { background: transparent; color: #fff; border: 1px solid #444; }
        .toolbar .ghost:hover { background: #222; }

        .label-page {
            width: 100mm; height: 150mm;
            margin: 20px auto;
            background: #fff;
            box-shadow: 0 2px 12px rgba(0,0,0,.1);
            display: flex; flex-direction: column;
            box-sizing: border-box;
            overflow: hidden;
        }
        .label-head {
            padding: 6px 8px;
            border-bottom: 2px solid #000;
            display: flex; justify-content: space-between; align-items: center;
        }
        .label-brand { font-weight: 700; font-size: 12px; }
        .label-mode { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; padding: 2px 6px; border: 1px solid #000; border-radius: 3px; }
        .label-mode.cod { background: #000; color: #fff; }
        .label-body { padding: 8px; flex: 1; display: flex; flex-direction: column; gap: 6px; }

        .k { font-size: 8.5px; color: #666; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 1px; }
        .v { font-size: 11px; color: #111; }
        .row-flex { display: flex; gap: 12px; }
        .row-flex > div { flex: 1; }

        .to-box {
            border: 1px dashed #000;
            padding: 6px 8px;
            border-radius: 4px;
        }
        .to-box .name { font-weight: 700; font-size: 13px; margin-bottom: 2px; }
        .to-box .addr { font-size: 11px; line-height: 1.4; }

        .from-box { font-size: 10px; color: #333; }

        .items-strip {
            border-top: 1px dashed #999;
            padding-top: 6px;
            font-size: 10px;
        }
        .items-strip .row {
            display: flex; justify-content: space-between; padding: 2px 0;
        }

        .barcode-block {
            padding: 6px 0;
            border-top: 2px solid #000;
            display: flex; flex-direction: column; align-items: center;
            gap: 2px;
        }
        .barcode-svg { display: block; }
        .barcode-num { font-family: "SF Mono", "Courier New", monospace; font-size: 11px; letter-spacing: 1px; }

        .foot {
            padding: 4px 8px;
            border-top: 1px solid #ccc;
            font-size: 8.5px;
            color: #666;
            display: flex; justify-content: space-between;
        }

        @media print {
            html, body { background: #fff; }
            .toolbar { display: none !important; }
            .label-page { box-shadow: none; margin: 0; page-break-after: always; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <b>Shipping Label · {{ $order->order_no }}</b>
    <div style="display:flex;gap:8px;align-items:center;">
        @if ($courierLabelUrl)
            <span style="font-size:11px;opacity:.7">This is the in-house fallback.</span>
            <a href="{{ $courierLabelUrl }}" target="_blank" rel="noopener">Courier's PDF ↗</a>
        @endif
        <a class="ghost" href="{{ route('admin.order.detail', $order->id) }}">← Back</a>
        <button onclick="window.print()">Print / Save PDF</button>
    </div>
</div>

<div class="label-page">
    <div class="label-head">
        <div class="label-brand">{{ config('app.name') }}</div>
        <div class="label-mode {{ $paymentType === 'cod' ? 'cod' : '' }}">
            {{ $paymentType === 'cod' ? 'COD ₹' . number_format((float) $order->total, 2) : 'Prepaid' }}
        </div>
    </div>

    <div class="label-body">
        <div class="to-box">
            <div class="k">Ship To</div>
            <div class="name">{{ $order->shipping_name }}</div>
            <div class="addr">
                {{ $order->shipping_address }}<br>
                {{ $order->shipping_city }}, {{ $order->shipping_state }} — <b>{{ $order->shipping_pincode }}</b><br>
                Phone: {{ $order->shipping_phone }}
            </div>
        </div>

        <div class="row-flex">
            <div>
                <div class="k">Order</div>
                <div class="v">#{{ $order->order_no }}</div>
            </div>
            <div>
                <div class="k">Date</div>
                <div class="v">{{ optional($order->created_at)->format('d M Y') }}</div>
            </div>
        </div>

        @if ($shipment)
            <div class="row-flex">
                <div>
                    <div class="k">Courier</div>
                    <div class="v">{{ $shipment->courier_name ?: ($shipment->provider ?: '—') }}</div>
                </div>
                <div>
                    <div class="k">AWB</div>
                    <div class="v"><b>{{ $shipment->awb_code ?: 'Pending' }}</b></div>
                </div>
            </div>
        @endif

        <div class="from-box">
            <div class="k">Return To</div>
            @if (! empty($returnAddress))
                <div>{{ $returnAddress }}</div>
            @else
                <div>{{ config('app.name') }}</div>
            @endif
        </div>

        <div class="items-strip">
            <div class="k">Package</div>
            @foreach ($order->orderItems as $item)
                <div class="row">
                    <span>{{ Str::limit(optional($item->product)->name ?? 'Item', 40) }} × {{ $item->quantity }}</span>
                    <span>₹{{ number_format($item->price * $item->quantity, 2) }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="barcode-block">
        {!! $barcodeSvg !!}
        <span class="barcode-num">{{ $barcodeText }}</span>
    </div>

    <div class="foot">
        <span>Weight ~{{ $shipmentWeight }} kg</span>
        <span>Printed {{ now()->format('d M Y H:i') }}</span>
    </div>
</div>

<script>
    // Kick off the print dialog automatically only if the query string asks for it,
    // so admins can review before printing when they land here normally.
    if (new URLSearchParams(location.search).get('autoprint') === '1') {
        window.addEventListener('load', () => setTimeout(() => window.print(), 400));
    }
</script>
</body>
</html>
