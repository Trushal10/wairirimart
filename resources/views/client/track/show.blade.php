@extends('layouts.client')

@php
    use App\Helper\CommonHelper;
    use App\Helper\OrderStatusHelper;
    use App\Models\Order;

    $isCancelled = $order->status === Order::CANCELLED;
    // The courier's own status is the more specific truth once a shipment
    // exists; before that the order's status is all there is.
    $badge = $shipment
        ? OrderStatusHelper::shipmentBadge($shipment->status)
        : OrderStatusHelper::orderBadge($order->status);
    $milestones = OrderStatusHelper::milestones($order, $shipment);

    $etaMin = (int) config('services.shipping.eta_min_days', 3);
    $etaMax = (int) config('services.shipping.eta_max_days', 7);
    $etaTo  = $order->created_at?->copy()->addDays($etaMax);
    $showEta = ! $isCancelled
        && $order->status !== Order::COMPLETED
        && ! $shipment?->delivered_at
        && $etaTo;

    // Polling only earns its keep while something can still change.
    $isLive = ! $isCancelled && ! $shipment?->isTerminal();
@endphp

@section('title', 'Tracking order ' . $order->order_no . ' | ' . config('app.name'))
@section('meta_description', 'Live tracking for your order and shipment.')

@section('structured_data')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('style')
<style>{!! CommonHelper::inlineCss(['client/css/order-ui.css']) !!}</style>
<style>
    .tr-page { background: var(--ou-ground); }
    .tr-wrap { max-width: 820px; margin: 0 auto; padding: 0 16px; }

    .tr-head {
        display: flex; flex-wrap: wrap; align-items: flex-start;
        justify-content: space-between; gap: 14px; margin-bottom: 18px;
    }
    .tr-head-no { font-size: 12.5px; color: var(--ou-soft); margin: 0 0 4px; }
    .tr-head-status { margin: 0; font-size: 23px; font-weight: 700; color: var(--ou-ink); letter-spacing: -.01em; }

    .tr-eta {
        display: flex; align-items: center; gap: 9px;
        background: var(--ou-ok-bg); color: var(--ou-ok-fg);
        border-radius: 10px; padding: 11px 14px;
        font-size: 13.5px; font-weight: 600;
        margin: 0 0 22px;
    }

    .tr-facts {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 14px;
        margin-top: 22px;
        padding-top: 20px;
        border-top: 1px solid var(--ou-line-soft);
    }
    .tr-fact-k { font-size: 11.5px; text-transform: uppercase; letter-spacing: .05em; color: var(--ou-soft); margin-bottom: 3px; }
    .tr-fact-v { font-size: 14px; font-weight: 600; color: var(--ou-ink); word-break: break-word; }

    /* ---- timeline ---- */
    .tr-timeline { position: relative; margin: 0; padding: 0 0 0 26px; list-style: none; }
    .tr-timeline::before {
        content: ''; position: absolute; left: 5px; top: 6px; bottom: 6px;
        width: 2px; background: var(--ou-line);
    }
    .tr-event { position: relative; padding: 0 0 20px; }
    .tr-event:last-child { padding-bottom: 0; }
    .tr-event::before {
        content: ''; position: absolute; left: -26px; top: 5px;
        width: 12px; height: 12px; border-radius: 50%;
        background: #fff; border: 2px solid var(--ou-line);
    }
    .tr-event.is-ok::before  { background: var(--ou-ok-fg);  border-color: var(--ou-ok-fg); }
    .tr-event.is-bad::before { background: var(--ou-bad-fg); border-color: var(--ou-bad-fg); }
    .tr-event.is-latest::before { border-color: var(--ou-ok-fg); box-shadow: 0 0 0 4px rgba(23,122,59,.13); }
    .tr-event-title { font-size: 14px; font-weight: 600; color: var(--ou-ink); margin: 0 0 2px; }
    .tr-event-note { font-size: 13px; color: var(--ou-body); margin: 0 0 3px; line-height: 1.5; }
    .tr-event-at { font-size: 12px; color: var(--ou-soft); }
    .tr-tag {
        display: inline-block; margin-left: 7px; padding: 1px 7px;
        background: var(--ou-ground); border: 1px solid var(--ou-line);
        border-radius: 4px; font-size: 10px; font-weight: 600;
        text-transform: uppercase; letter-spacing: .04em; color: var(--ou-soft);
        vertical-align: 1px;
    }

    .tr-live {
        display: flex; align-items: center; gap: 7px;
        font-size: 12px; color: var(--ou-soft);
    }
    .tr-live-dot {
        width: 7px; height: 7px; border-radius: 50%;
        background: var(--ou-ok-fg);
        animation: tr-pulse 2s ease-in-out infinite;
    }
    @keyframes tr-pulse { 0%,100% { opacity: 1 } 50% { opacity: .25 } }
    @media (prefers-reduced-motion: reduce) { .tr-live-dot { animation: none } }

    .tr-foot {
        display: flex; flex-wrap: wrap; align-items: center;
        justify-content: space-between; gap: 12px;
        padding-top: 4px;
    }
    .tr-signout {
        background: none; border: 0; padding: 0;
        color: var(--ou-soft); font-size: 13px; text-decoration: underline;
        text-underline-offset: 3px; cursor: pointer;
    }
    .tr-signout:hover { color: var(--ou-ink); }

    @media (max-width: 575.98px) {
        .tr-head-status { font-size: 20px; }
        .tr-facts { grid-template-columns: 1fr 1fr; }
    }
</style>
@endsection

@section('content')
    @include('client.partials.page-hero', [
        'title'  => 'Track order',
        'crumbs' => [
            ['label' => 'Track', 'url' => route('client.track.form')],
            ['label' => '#' . $order->order_no],
        ],
    ])

    <section class="flat-spacing tr-page">
        <div class="tr-wrap" id="tracking-root"
            data-order-no="{{ $order->order_no }}"
            data-live="{{ $isLive ? '1' : '0' }}"
            data-live-url="{{ route('client.track.live', ['orderNo' => $order->order_no]) }}">

            {{-- ---------- Status ---------- --}}
            <div class="ou-card">
                <div class="tr-head">
                    <div>
                        <p class="tr-head-no">Order #{{ $order->order_no }}</p>
                        {{-- Server-rendered with its tone already applied. This
                             used to ship as a bare .status-pill and only gained
                             a colour when the first poll landed three seconds
                             later, so every visit opened on an unstyled chip. --}}
                        {{-- Not an <h1>: page-hero above already emits the page's one
                             heading, and two of them on a page is a real a11y problem
                             rather than a styling preference. --}}
                        <p class="tr-head-status" id="tr-status-label">{{ $badge['label'] }}</p>
                    </div>
                    <span class="ou-chip ou-chip-{{ $badge['tone'] }}" id="tr-status-chip">{{ $badge['label'] }}</span>
                </div>

                @if ($isCancelled)
                    <div class="ou-note ou-note-bad" style="margin-bottom:20px">
                        <svg class="ou-note-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>
                        </svg>
                        <div>This order was cancelled, so it will not be shipped. Anything paid is refunded to the original payment method.</div>
                    </div>
                @elseif ($showEta)
                    <div class="tr-eta" id="tr-eta">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5h-7V8Z"/>
                            <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                        </svg>
                        Expected by {{ $etaTo->format('D, d M Y') }}
                    </div>
                @endif

                {{-- Four milestones a shopper recognises, not the courier's
                     seven-state machine. The old rail printed six labels while
                     the JS filled a bar against a seven-item list, so the fill
                     never lined up with the label underneath it. --}}
                <ol class="ou-rail {{ $isCancelled ? 'is-cancelled' : '' }}" id="tr-rail">
                    @foreach ($milestones as $step)
                        <li class="ou-rail-step is-{{ $step['state'] }}">
                            <span class="ou-rail-label">{{ $step['label'] }}</span>
                            @if ($step['at'])
                                <span class="ou-rail-at">{{ $step['at']->format('d M') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>

                {{-- Courier details (courier, AWB, shipped/delivered dates), the
                     courier-site link and the Journey timeline are deliberately
                     not shown to customers; the status chip and milestone rail
                     above carry the progress. The live script guards every
                     element it touches, so it simply skips the missing ones. --}}
                <div class="ou-actions" style="margin-top:22px">
                    <a href="{{ route('client.invoice', ['orderNo' => $order->order_no]) }}" target="_blank" rel="noopener" class="ou-btn ou-btn-ghost">
                        Invoice
                    </a>
                    @auth('customer')
                        <a href="{{ route('client.profile') }}?type=orders" class="ou-btn ou-btn-ghost">All orders</a>
                    @endauth
                    {{-- Only the signed-in owner, and only until the order ships
                         (Order::isCancellableByCustomer). --}}
                    @if (auth('customer')->id() === $order->customer_id && $order->isCancellableByCustomer())
                        <button type="button" class="ou-btn ou-btn-danger js-cancel-order"
                            data-order-no="{{ $order->order_no }}"
                            data-action="{{ route('client.order.cancel', ['orderNo' => $order->order_no]) }}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/></svg>
                            Cancel order
                        </button>
                    @endif
                </div>
                @if (auth('customer')->id() === $order->customer_id && $order->isCancellableByCustomer())
                    <p style="font-size:12.5px;color:var(--ou-soft);margin:12px 0 0">
                        You can cancel free of charge until your order ships.
                    </p>
                @endif
            </div>

            {{-- ---------- What's in it ---------- --}}
            <div class="ou-card">
                <h2 class="ou-card-title">
                    <span>In this order</span>
                    <span style="font-weight:500;text-transform:none;letter-spacing:0;color:var(--ou-soft)">
                        ₹{{ number_format((float) $order->total, 2) }}
                    </span>
                </h2>
                @foreach ($order->orderItems as $item)
                    @php
                        $img = ! empty($item->media?->url)
                            ? asset('storage/product/' . $item->media->url)
                            : CommonHelper::assetV('client/images/home/product-placeholder.webp');
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
                                @if ($item->options_label){{ $item->options_label }} · @endif
                                Qty {{ $item->quantity }}
                            </div>
                        </div>
                        <div class="ou-item-price">₹{{ number_format((float) $item->price * $item->quantity, 2) }}</div>
                    </div>
                @endforeach
            </div>

            <div class="tr-foot">
                <a href="{{ route('client.shop') }}" style="font-size:13.5px;color:var(--ou-ink);font-weight:600">← Continue shopping</a>
                {{-- Only meaningful for someone who got in with order-no + email;
                     a signed-in customer reaches their own orders regardless. --}}
                @guest('customer')
                    <form method="post" action="{{ route('client.track.logout', ['orderNo' => $order->order_no]) }}">
                        @csrf
                        <button type="submit" class="tr-signout">Sign out of tracking</button>
                    </form>
                @endguest
            </div>
        </div>
    </section>
@endsection

@section('scripts')
<script>
(function () {
    var root = document.getElementById('tracking-root');
    if (!root || root.getAttribute('data-live') !== '1') return;

    var liveUrl = root.getAttribute('data-live-url');
    var timer = null;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
        });
    }

    function setText(id, value) {
        var el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    // Tone → class comes straight from the server now, so the polled state and
    // the first paint can never disagree about what colour "in transit" is.
    function applyBadge(badge) {
        if (!badge) return;
        var chip = document.getElementById('tr-status-chip');
        if (chip) {
            chip.className = 'ou-chip ou-chip-' + badge.tone;
            chip.textContent = badge.label;
        }
        setText('tr-status-label', badge.label);
    }

    function applyRail(milestones, cancelled) {
        var rail = document.getElementById('tr-rail');
        if (!rail || !Array.isArray(milestones)) return;
        rail.classList.toggle('is-cancelled', !!cancelled);
        rail.innerHTML = milestones.map(function (m) {
            return '<li class="ou-rail-step is-' + esc(m.state) + '">'
                + '<span class="ou-rail-label">' + esc(m.label) + '</span>'
                + (m.at ? '<span class="ou-rail-at">' + esc(m.at) + '</span>' : '')
                + '</li>';
        }).join('');
    }

    function applyTimeline(events) {
        var el = document.getElementById('tr-timeline');
        if (!el || !Array.isArray(events)) return;
        if (!events.length) return;
        el.innerHTML = events.map(function (e, i) {
            var tone = e.tone || '';
            var cls = tone === 'bad' ? 'is-bad' : (tone === 'ok' ? 'is-ok' : '');
            return '<li class="tr-event ' + cls + (i === 0 ? ' is-latest' : '') + '">'
                + '<p class="tr-event-title">' + esc(e.label || e.status)
                + '<span class="tr-tag">' + esc(e.source || 'system') + '</span></p>'
                + (e.comment ? '<p class="tr-event-note">' + esc(e.comment) + '</p>' : '')
                + '<span class="tr-event-at">' + esc(e.at || '') + '</span>'
                + '</li>';
        }).join('');
    }

    function apply(data) {
        var shipment = data.shipment || {};

        applyBadge(data.badge);
        applyRail(data.milestones, data.cancelled);
        applyTimeline(data.timeline);

        setText('tr-courier', shipment.courier_name || 'Not assigned yet');
        setText('tr-awb', shipment.awb_code || '—');

        var link = document.getElementById('tr-courier-link');
        if (link) {
            if (shipment.tracking_url) {
                link.href = shipment.tracking_url;
                link.hidden = false;
            } else {
                link.hidden = true;
            }
        }

        setText('tr-refreshed', new Date().toLocaleTimeString('en-IN', {
            hour: '2-digit', minute: '2-digit'
        }));

        // Nothing further will change once it is delivered, cancelled or
        // returned — stop polling rather than hammer the endpoint forever.
        if (data.cancelled || (data.badge && data.badge.tone === 'ok' && shipment.delivered_at)) {
            if (timer) clearInterval(timer);
        }
    }

    function tick() {
        // A backgrounded tab does not need updates, and mobile browsers
        // throttle it anyway — skip the request instead of queueing it.
        if (document.hidden) return;
        fetch(liveUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) { if (data) apply(data); })
            .catch(function () { /* transient — the next tick retries */ });
    }

    setTimeout(tick, 4000);
    timer = setInterval(tick, 30000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) tick();
    });
})();
</script>
@include('client.partials.cancel-order-script')
@endsection
