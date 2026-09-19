@extends('layouts.client')

@php
    use App\Helper\CommonHelper;
    use App\Helper\OrderStatusHelper;
    use App\Models\Order;

    $isCancelled = $order->status === Order::CANCELLED;
    $isDelivered = $order->status === Order::COMPLETED;
    $badge       = OrderStatusHelper::orderBadge($order->status);
    $payBadge    = OrderStatusHelper::paymentBadge($order->payment?->status);
    $milestones  = OrderStatusHelper::milestones($order, $order->latestShipment);

    // Tracking is only offered when the storefront actually has it: a courier
    // is configured AND the admin has not switched "Show track order" off. The
    // /track routes 404 in either case, so a link here would dead-end.
    $canTrack = OrderStatusHelper::publicTrackingVisible($settings ?? null) && ! $isCancelled;

    // Estimated arrival. Only meaningful while the order is still on its way —
    // a cancelled or delivered order has no future date to promise.
    $etaMin = (int) config('services.shipping.eta_min_days', 3);
    $etaMax = (int) config('services.shipping.eta_max_days', 7);
    $etaFrom = $order->created_at?->copy()->addDays($etaMin);
    $etaTo   = $order->created_at?->copy()->addDays($etaMax);
    $showEta = ! $isCancelled && ! $isDelivered && $etaFrom && $etaTo;

    $paymentLabel = match ($order->payment?->type) {
        'cod'   => 'Cash on Delivery',
        null    => 'Payment',
        default => ucfirst($order->payment->type),
    };
@endphp

@section('title')
    Order #{{ $order->order_no }} — {{ $badge['label'] }} | {{ config('app.name') }}
@endsection

@section('meta_description')
    Your {{ config('app.name') }} order {{ $order->order_no }} and its current status.
@endsection

{{-- This page should never be indexed or shared: it is one customer's order. --}}
@section('structured_data')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('style')
<style>{!! CommonHelper::inlineCss(['client/css/order-ui.css']) !!}</style>
<style>
    .oc-page { background: var(--ou-ground); }
    .oc-wrap { max-width: 760px; margin: 0 auto; padding: 0 16px; }

    .oc-hero {
        background: var(--ou-surface);
        border: 1px solid var(--ou-line);
        border-radius: var(--ou-radius);
        padding: 36px 28px 28px;
        text-align: center;
        margin-bottom: 16px;
    }
    .oc-mark {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 66px; height: 66px;
        border-radius: 50%;
        margin-bottom: 18px;
        animation: oc-pop .35s cubic-bezier(.2, .8, .3, 1.2);
    }
    .oc-mark-ok  { background: var(--ou-ok-bg);  color: var(--ou-ok-fg); }
    .oc-mark-bad { background: var(--ou-bad-bg); color: var(--ou-bad-fg); }
    @keyframes oc-pop { from { transform: scale(.6); opacity: 0 } to { transform: scale(1); opacity: 1 } }
    /* Respect a reduced-motion preference — the mark is decorative. */
    @media (prefers-reduced-motion: reduce) { .oc-mark { animation: none } }

    .oc-title { font-size: 26px; font-weight: 700; color: var(--ou-ink); margin: 0 0 8px; letter-spacing: -.01em; }
    .oc-sub { color: var(--ou-body); font-size: 14.5px; line-height: 1.6; margin: 0 auto 20px; max-width: 46ch; }
    .oc-sub strong { color: var(--ou-ink); }

    .oc-idrow {
        display: flex; flex-wrap: wrap; align-items: center; justify-content: center;
        gap: 10px; margin-bottom: 22px;
    }
    .oc-orderno {
        display: inline-flex; align-items: center; gap: 8px;
        background: var(--ou-ground);
        border: 1px solid var(--ou-line);
        color: var(--ou-ink);
        padding: 7px 14px;
        border-radius: 999px;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 13px;
        letter-spacing: .04em;
    }
    .oc-copy {
        background: none; border: 0; padding: 0; cursor: pointer;
        color: var(--ou-soft); display: inline-flex; line-height: 0;
    }
    .oc-copy:hover { color: var(--ou-ink); }

    .oc-eta {
        background: var(--ou-ok-bg);
        color: var(--ou-ok-fg);
        border-radius: 12px;
        padding: 13px 16px;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 22px;
    }
    .oc-eta span { display: block; font-weight: 500; font-size: 12.5px; opacity: .85; margin-top: 2px; }

    .oc-actions { justify-content: center; }

    .oc-two { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    @media (max-width: 640px) { .oc-two { grid-template-columns: 1fr; gap: 0; } }

    .oc-address { color: var(--ou-body); font-size: 13.5px; line-height: 1.75; }
    .oc-address strong { color: var(--ou-ink); display: block; margin-bottom: 2px; }

    .oc-next { counter-reset: step; list-style: none; margin: 0; padding: 0; }
    .oc-next li {
        position: relative;
        padding: 0 0 14px 34px;
        font-size: 13.5px;
        color: var(--ou-body);
        line-height: 1.55;
    }
    .oc-next li:last-child { padding-bottom: 0; }
    .oc-next li::before {
        counter-increment: step;
        content: counter(step);
        position: absolute; left: 0; top: -1px;
        width: 22px; height: 22px;
        border-radius: 50%;
        background: var(--ou-ground);
        border: 1px solid var(--ou-line);
        color: var(--ou-ink);
        font-size: 11px; font-weight: 700;
        display: flex; align-items: center; justify-content: center;
    }

    .oc-foot {
        text-align: center;
        padding: 8px 0 4px;
        font-size: 13.5px;
        color: var(--ou-soft);
    }
    .oc-foot a { color: var(--ou-ink); font-weight: 600; text-decoration: underline; text-underline-offset: 3px; }

    @media (max-width: 575.98px) {
        .oc-hero { padding: 28px 18px 22px; }
        .oc-title { font-size: 21px; }
        .oc-sub { font-size: 13.5px; }
    }
</style>
@endsection

@section('content')
<section class="flat-spacing oc-page">
    <div class="oc-wrap">

        {{-- ---------- Hero ----------
             State-aware: a cancelled order must not open with "Thank you for
             your order!", which is what it did before — the page rendered the
             celebration regardless of status, and the status chip that would
             have contradicted it had no CSS for 'canceled' so it came out
             invisible. --}}
        <div class="oc-hero">
            @if ($isCancelled)
                <div class="oc-mark oc-mark-bad">
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                        <path d="M18 6 6 18M6 6l12 12"/>
                    </svg>
                </div>
                <h1 class="oc-title">Order cancelled</h1>
                <p class="oc-sub">
                    Order <strong>#{{ $order->order_no }}</strong> has been cancelled and the items released.
                    @if ($order->payment?->status === 'paid')
                        Your refund is on its way back to the original payment method — allow 5–7 working days.
                    @endif
                </p>
            @elseif ($isDelivered)
                <div class="oc-mark oc-mark-ok">
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <h1 class="oc-title">Delivered</h1>
                <p class="oc-sub">This order has been delivered. We hope everything arrived in good shape.</p>
            @else
                <div class="oc-mark oc-mark-ok">
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <h1 class="oc-title">Thank you for your order!</h1>
                <p class="oc-sub">
                    We've received it and will start packing shortly.
                    A confirmation has been sent to <strong>{{ $order->shipping_email }}</strong>.
                </p>
            @endif

            <div class="oc-idrow">
                <span class="oc-orderno" id="oc-order-no" data-order-no="{{ $order->order_no }}">
                    #{{ $order->order_no }}
                    <button type="button" class="oc-copy" id="oc-copy" aria-label="Copy order number" title="Copy order number">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="9" y="9" width="13" height="13" rx="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                    </button>
                </span>
                <span class="ou-chip ou-chip-{{ $badge['tone'] }}">{{ $badge['label'] }}</span>
            </div>

            @if ($showEta)
                <div class="oc-eta">
                    Estimated delivery {{ $etaFrom->format('D, d M') }} – {{ $etaTo->format('D, d M') }}
                    <span>You'll get a tracking update as soon as it ships.</span>
                </div>
            @endif

            {{-- ---------- Milestone rail ---------- --}}
            <ol class="ou-rail {{ $isCancelled ? 'is-cancelled' : '' }}">
                @foreach ($milestones as $step)
                    <li class="ou-rail-step is-{{ $step['state'] }}">
                        <span class="ou-rail-label">{{ $step['label'] }}</span>
                        @if ($step['at'])
                            <span class="ou-rail-at">{{ $step['at']->format('d M') }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>

            {{-- ---------- Actions ----------
                 The cancel action lives here, next to Track and Invoice,
                 because this is the page someone is looking at when they
                 realise they ordered the wrong thing. It is a real button in
                 the action row rather than a link buried below the fold. --}}
            <div class="ou-actions oc-actions" style="margin-top:26px">
                @if ($canTrack)
                    <a href="{{ route('client.track.show', ['orderNo' => $order->order_no]) }}" class="ou-btn ou-btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="1" y="3" width="15" height="13" rx="2"/>
                            <path d="M16 8h4l3 3v5h-7V8Z"/>
                            <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                        </svg>
                        Track order
                    </a>
                @endif

                <a href="{{ route('client.invoice', ['orderNo' => $order->order_no]) }}" target="_blank" rel="noopener" class="ou-btn ou-btn-ghost">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/>
                        <path d="M14 2v6h6M9 13h6M9 17h6"/>
                    </svg>
                    Invoice
                </a>

                @if ($order->isCancellableByCustomer())
                    <button type="button"
                        class="ou-btn ou-btn-danger js-cancel-order"
                        data-order-no="{{ $order->order_no }}"
                        data-action="{{ route('client.order.cancel', ['orderNo' => $order->order_no]) }}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/>
                        </svg>
                        Cancel order
                    </button>
                @endif
            </div>

            @if ($order->isCancellableByCustomer())
                <p style="font-size:12.5px;color:var(--ou-soft);margin:14px 0 0">
                    You can cancel free of charge until your order ships.
                </p>
            @endif
        </div>

        {{-- ---------- Items ---------- --}}
        <div class="ou-card">
            <h2 class="ou-card-title">
                <span>Your items</span>
                <span style="font-weight:500;text-transform:none;letter-spacing:0;color:var(--ou-soft)">
                    {{ $order->orderItems->count() }} item{{ $order->orderItems->count() === 1 ? '' : 's' }}
                </span>
            </h2>

            @forelse ($order->orderItems as $item)
                @php
                    $img = ! empty($item->media?->url)
                        ? asset('storage/product/' . $item->media->url)
                        : CommonHelper::assetV('client/images/home/product-placeholder.webp');
                    $optionsLabel = $item->options_label;
                @endphp
                <div class="ou-item">
                    <img class="ou-item-img" src="{{ $img }}" alt="{{ $item->product->name ?? 'Product' }}"
                        loading="lazy" width="56" height="56"
                        onerror="this.src='{{ CommonHelper::assetV('client/images/home/product-placeholder.webp') }}'">
                    <div class="ou-item-body">
                        <p class="ou-item-name">
                            @if (! empty($item->product?->slug))
                                <a href="{{ route('client.product') }}/{{ $item->product->slug }}">{{ $item->product->name }}</a>
                            @else
                                {{ $item->product->name ?? 'Product' }}
                            @endif
                        </p>
                        <div class="ou-item-meta">
                            @if ($optionsLabel){{ $optionsLabel }} · @endif
                            Qty {{ $item->quantity }} × ₹{{ number_format((float) $item->price, 2) }}
                        </div>
                    </div>
                    <div class="ou-item-price">₹{{ number_format((float) $item->price * $item->quantity, 2) }}</div>
                </div>
            @empty
                <p class="ou-item-meta">No items recorded on this order.</p>
            @endforelse

            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--ou-line-soft)">
                <div class="ou-total"><span>Subtotal</span><span>₹{{ number_format((float) $order->sub_total, 2) }}</span></div>
                @if ((float) $order->discount > 0)
                    <div class="ou-total ou-total-save">
                        <span>Discount{{ $order->coupan_code ? ' (' . $order->coupan_code . ')' : '' }}</span>
                        <span>−₹{{ number_format((float) $order->discount, 2) }}</span>
                    </div>
                @endif
                <div class="ou-total">
                    <span>Shipping</span>
                    <span>{{ (float) $order->shipping > 0 ? '₹' . number_format((float) $order->shipping, 2) : 'Free' }}</span>
                </div>
                @if ((float) $order->tax_amount > 0)
                    <div class="ou-total"><span>Tax</span><span>₹{{ number_format((float) $order->tax_amount, 2) }}</span></div>
                @endif
                <div class="ou-total ou-total-grand">
                    <span>{{ $order->payment?->status === 'paid' ? 'Total paid' : 'Order total' }}</span>
                    <span>₹{{ number_format((float) $order->total, 2) }}</span>
                </div>
            </div>
        </div>

        {{-- ---------- Delivery + payment ---------- --}}
        <div class="oc-two">
            <div class="ou-card">
                <h2 class="ou-card-title">Delivering to</h2>
                <address class="oc-address" style="font-style:normal;margin:0">
                    <strong>{{ $order->shipping_name }}</strong>
                    {{ $order->shipping_address }}<br>
                    {{ $order->shipping_city }}, {{ $order->shipping_state }} — {{ $order->shipping_pincode }}<br>
                    {{ $order->shipping_phone }}<br>
                    {{ $order->shipping_email }}
                </address>
            </div>

            <div class="ou-card">
                <h2 class="ou-card-title">Payment</h2>
                <dl style="margin:0">
                    <div class="ou-kv"><dt>Method</dt><dd>{{ $paymentLabel }}</dd></div>
                    <div class="ou-kv">
                        <dt>Status</dt>
                        <dd><span class="ou-chip ou-chip-{{ $payBadge['tone'] }}">{{ $payBadge['label'] }}</span></dd>
                    </div>
                    <div class="ou-kv"><dt>Placed</dt><dd>{{ $order->created_at->format('d M Y, g:i A') }}</dd></div>
                </dl>
            </div>
        </div>

        {{-- ---------- What happens next ---------- --}}
        @if (! $isCancelled && ! $isDelivered)
            <div class="ou-card">
                <h2 class="ou-card-title">What happens next</h2>
                <ol class="oc-next">
                    <li>We confirm and pack your order, usually within 1–2 business days.</li>
                    <li>
                        You'll get an email
                        @if ($canTrack) and a tracking link @endif
                        as soon as it ships.
                    </li>
                    <li>Standard delivery takes {{ $etaMin }}–{{ $etaMax }} days from dispatch.</li>
                </ol>
            </div>
        @endif

        @if ($isCancelled)
            <div class="ou-note ou-note-info" style="margin-bottom:16px">
                <svg class="ou-note-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
                </svg>
                <div>Changed your mind again? The items are back in stock and you can reorder any time.</div>
            </div>
        @endif

        <p class="oc-foot">
            <a href="{{ route('client.profile') }}?type=orders">View all orders</a>
            &nbsp;·&nbsp;
            <a href="{{ route('client.shop') }}">Continue shopping</a>
        </p>
    </div>
</section>
@endsection

@section('scripts')
<script>
(function () {
    // ---- copy order number ----
    // Shoppers quote this number to support; selecting monospace text on a
    // phone is fiddly, so every marketplace offers a copy affordance.
    var copyBtn = document.getElementById('oc-copy');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var no = document.getElementById('oc-order-no').getAttribute('data-order-no');
            var done = function () {
                if (typeof showSweetAlert === 'function') {
                    showSweetAlert('success', 'Order number copied.');
                }
            };
            // navigator.clipboard needs a secure context; this shop runs on
            // plain http in staging, so keep the execCommand fallback.
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(no).then(done).catch(function () {});
                return;
            }
            var ta = document.createElement('textarea');
            ta.value = no;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); done(); } catch (e) {}
            document.body.removeChild(ta);
        });
    }

})();
</script>
@include('client.partials.cancel-order-script')
@endsection
