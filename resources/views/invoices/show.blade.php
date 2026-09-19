<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice #{{ $order->order_no }} — {{ config('app.name') }}</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color:#222; background:#f4f4f4; padding:30px 16px; }
        .invoice-wrapper { max-width:820px; margin:0 auto; background:#fff; padding:40px; box-shadow:0 4px 24px rgba(0,0,0,.05); border-radius:8px; }
        .top-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:32px; }
        .btn-print, .btn-back { display:inline-block; padding:8px 16px; border-radius:6px; font-size:13px; text-decoration:none; font-weight:600; border:none; cursor:pointer; }
        .btn-print { background:#111; color:#fff; }
        .btn-back { background:#f0f0f0; color:#333; margin-right:8px; }

        .invoice-head { display:flex; justify-content:space-between; align-items:flex-start; padding-bottom:24px; border-bottom:2px solid #111; margin-bottom:24px; }
        .company-name { font-size:22px; font-weight:700; color:#111; margin-bottom:4px; letter-spacing:-.3px; }
        .company-meta { font-size:12px; color:#666; line-height:1.6; }
        .invoice-meta { text-align:right; }
        .invoice-meta h2 { font-size:26px; font-weight:800; color:#111; letter-spacing:1px; margin-bottom:8px; }
        .invoice-meta .row { font-size:13px; color:#666; margin-bottom:4px; }
        .invoice-meta .row strong { color:#111; }

        .section { display:flex; gap:32px; margin-bottom:28px; }
        .section .box { flex:1; }
        .box h5 { font-size:11px; font-weight:700; color:#666; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px; }
        .box p { font-size:13px; color:#333; line-height:1.6; }
        .box p strong { color:#111; }

        table.invoice-items { width:100%; border-collapse:collapse; margin-bottom:24px; }
        .invoice-items thead th { background:#111; color:#fff; padding:12px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; text-align:left; }
        .invoice-items thead th.right { text-align:right; }
        .invoice-items tbody td { padding:12px; border-bottom:1px solid #eee; font-size:13px; color:#333; }
        .invoice-items tbody td.right { text-align:right; font-variant-numeric:tabular-nums; }
        .invoice-items tbody tr:last-child td { border-bottom:2px solid #111; }
        .item-name { color:#111; font-weight:600; }
        .item-sku { color:#888; font-size:11px; margin-top:2px; }

        .totals { display:flex; justify-content:flex-end; margin-bottom:24px; }
        .totals-table { min-width:280px; }
        .total-row { display:flex; justify-content:space-between; padding:6px 0; font-size:13px; color:#555; }
        .total-row.discount { color:#177a3b; }
        .total-row.grand {
            border-top:2px solid #111; margin-top:8px; padding-top:12px;
            font-weight:700; font-size:16px; color:#111;
        }

        .payment-status {
            display:inline-block; padding:6px 14px; border-radius:999px; font-size:11px;
            font-weight:700; text-transform:uppercase; letter-spacing:1px;
        }
        .payment-status.paid { background:#d4edda; color:#155724; }
        .payment-status.pending { background:#fff3cd; color:#856404; }
        .payment-status.failed, .payment-status.refunded { background:#f8d7da; color:#721c24; }

        .footer { margin-top:32px; padding-top:20px; border-top:1px dashed #ccc; font-size:11px; color:#888; text-align:center; line-height:1.6; }
        .footer p { margin-bottom:4px; }

        /* Print styles: strip the surrounding chrome + drop shadows */
        @media print {
            body { background:#fff; padding:0; }
            .invoice-wrapper { box-shadow:none; padding:20px; max-width:100%; border-radius:0; }
            .top-bar, .no-print { display:none !important; }
        }

        @media (max-width:640px) {
            .invoice-wrapper { padding:20px; }
            .invoice-head, .section { flex-direction:column; gap:16px; }
            .invoice-meta { text-align:left; }
            .totals-table { min-width:100%; }
        }
    </style>
</head>
<body>
    <div class="invoice-wrapper">
        <div class="top-bar no-print">
            <a href="{{ url()->previous() }}" class="btn-back">← Back</a>
            <button onclick="window.print()" class="btn-print">🖨️ Print / Save PDF</button>
        </div>

        <div class="invoice-head">
            <div>
                <div class="company-name">{{ $settings->name ?? config('app.name') }}</div>
                <div class="company-meta">
                    @if (! empty($settings?->address))
                        {{ $settings->address }}<br>
                    @endif
                    @if (! empty($settings?->city))
                        {{ $settings->city }}<br>
                    @endif
                    @if (! empty($settings?->email))
                        {{ $settings->email }}
                    @endif
                    @if (! empty($settings?->phone))
                        · {{ $settings->phone }}
                    @endif
                </div>
            </div>
            <div class="invoice-meta">
                <h2>INVOICE</h2>
                <div class="row"><strong>#{{ $order->order_no }}</strong></div>
                <div class="row">Date: {{ $order->created_at->format('d M Y') }}</div>
                <div class="row" style="margin-top:8px">
                    <span class="payment-status {{ $order->payment?->status ?? 'pending' }}">
                        {{ $order->payment?->status === 'paid' ? '✓ Paid' : ucfirst($order->payment?->status ?? 'pending') }}
                    </span>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="box">
                <h5>Bill To</h5>
                <p>
                    <strong>{{ $order->customer?->name ?? $order->shipping_name }}</strong><br>
                    {{ $order->customer?->email ?? $order->shipping_email }}<br>
                    {{ $order->customer?->phone ?? $order->shipping_phone }}
                </p>
            </div>
            <div class="box">
                <h5>Ship To</h5>
                <p>
                    <strong>{{ $order->shipping_name }}</strong><br>
                    {{ $order->shipping_address }}<br>
                    {{ $order->shipping_city }}, {{ $order->shipping_state }} — {{ $order->shipping_pincode }}<br>
                    {{ $order->shipping_phone }}
                </p>
            </div>
            <div class="box">
                <h5>Payment</h5>
                <p>
                    <strong>{{ $order->payment?->type === 'cod' ? 'Cash on Delivery' : 'Razorpay (Online)' }}</strong><br>
                    @if ($order->payment?->payment_id)
                        Txn: {{ $order->payment->payment_id }}<br>
                    @endif
                    @if ($order->coupan_code)
                        Coupon: <strong>{{ $order->coupan_code }}</strong>
                    @endif
                </p>
            </div>
        </div>

        <table class="invoice-items">
            <thead>
                <tr>
                    <th style="width:50%">Item</th>
                    <th class="right" style="width:15%">Unit Price</th>
                    <th class="right" style="width:10%">Qty</th>
                    <th class="right" style="width:25%">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->orderItems as $item)
                    @php
                        $name = $item->product?->name ?? $item->product_name_snapshot ?? 'Product';
                        $sku  = $item->variant?->sku ?? $item->product?->sku ?? $item->variant_sku_snapshot ?? '';
                    @endphp
                    <tr>
                        <td>
                            <div class="item-name">{{ $name }}</div>
                            @if ($sku)
                                <div class="item-sku">SKU: {{ $sku }}</div>
                            @endif
                        </td>
                        <td class="right">₹{{ number_format((float) $item->price, 2) }}</td>
                        <td class="right">{{ $item->quantity }}</td>
                        <td class="right">₹{{ number_format((float) $item->price * $item->quantity, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-table">
                <div class="total-row">
                    <span>Subtotal</span>
                    <span>₹{{ number_format((float) $order->sub_total, 2) }}</span>
                </div>
                @if ((float) $order->discount > 0)
                    <div class="total-row discount">
                        <span>Discount{{ $order->coupan_code ? ' (' . $order->coupan_code . ')' : '' }}</span>
                        <span>-₹{{ number_format((float) $order->discount, 2) }}</span>
                    </div>
                @endif
                <div class="total-row">
                    <span>Shipping</span>
                    <span>{{ (float) $order->shipping > 0 ? '₹' . number_format((float) $order->shipping, 2) : 'Free' }}</span>
                </div>
                <div class="total-row grand">
                    <span>Total</span>
                    <span>₹{{ number_format((float) $order->total, 2) }}</span>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>Thank you for shopping with {{ $settings->name ?? config('app.name') }}!</p>
            <p>This is a computer-generated invoice. For queries email {{ $settings->email ?? config('mail.from.address') }}.</p>
        </div>
    </div>
</body>
</html>
