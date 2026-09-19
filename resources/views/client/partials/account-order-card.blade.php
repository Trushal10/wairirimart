{{--
    One order in the customer's "Your Orders" list (client-auth/profile).

    Expects:
      $order        App\Models\Order with orderItems.product, orderItems.media,
                    payment and latestShipment loaded
      $trackingOn   whether the /track routes exist on this shop
--}}
@php
    $created    = \Illuminate\Support\Carbon::parse($order->created_at);
    $items      = $order->orderItems;
    $itemsCount = $order->order_items_count ?? $items->count();
    $payType    = $order->payment?->type;
    $payStatus  = $order->payment?->status;
    $shipment   = $order->latestShipment;
    $badge      = \App\Helper\OrderStatusHelper::orderBadge($order->status);
    $cancelled  = $order->status === \App\Models\Order::CANCELLED;
    $cancellable = $order->isCancellableByCustomer();
    $placeholder = asset('client/images/home/product-placeholder.webp');
    $imgFor = fn ($item) => ! empty($item->media?->url) ? asset('storage/product/' . $item->media->url) : $placeholder;

    // Milestones for the progress rail. Shipped is inferred from an AWB,
    // since the order status itself goes straight from confirmed to delivered.
    $delivered = $order->status === \App\Models\Order::COMPLETED;
    $steps = [
        ['label' => 'Placed',    'done' => true],
        ['label' => 'Confirmed', 'done' => in_array($order->status, [\App\Models\Order::CONFIRMED, \App\Models\Order::COMPLETED], true)],
        ['label' => 'Shipped',   'done' => $delivered || ! empty($shipment?->awb_code)],
        ['label' => 'Delivered', 'done' => $delivered],
    ];
    $currentStep = collect($steps)->search(fn ($s) => ! $s['done']);

    $firstName = $items->first()?->product?->name ?? 'Product';
    $payLabel  = $payType === 'cod' ? 'Cash on Delivery' : ($payType ? ucfirst($payType) : null);
@endphp

<article class="ao-card {{ $cancelled ? 'is-cancelled' : '' }}">
    <header class="ao-head">
        <div class="ao-id">
            <div class="ao-no">Order <strong>#{{ $order->order_no }}</strong></div>
            <div class="ao-date">
                Placed {{ $created->format('d M Y') }} · {{ $itemsCount }} item{{ $itemsCount === 1 ? '' : 's' }}
            </div>
        </div>
        <span class="ou-chip ou-chip-{{ $badge['tone'] }}">{{ $badge['label'] }}</span>
    </header>

    @if ($cancelled)
        <div class="ao-note ao-note--bad">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/></svg>
            <span>This order was cancelled. Anything you paid is refunded to your original payment method.</span>
        </div>
    @else
        <ol class="ao-steps" aria-label="Order progress">
            @foreach ($steps as $i => $step)
                <li class="ao-step {{ $step['done'] ? 'is-done' : '' }} {{ $currentStep === $i ? 'is-current' : '' }}"
                    @if ($currentStep === $i) aria-current="step" @endif>
                    <span class="ao-step-dot" aria-hidden="true">
                        @if ($step['done'])
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        @endif
                    </span>
                    <span class="ao-step-label">{{ $step['label'] }}</span>
                </li>
            @endforeach
        </ol>
    @endif

    <div class="ao-body">
        <div class="ao-thumbs">
            @foreach ($items->take(3) as $item)
                <span class="ao-thumb" title="{{ $item->product?->name ?? 'Product' }}">
                    <img src="{{ $imgFor($item) }}" alt="{{ $item->product?->name ?? 'Product' }}" loading="lazy" decoding="async"
                         onerror="this.onerror=null;this.src='{{ $placeholder }}'">
                    @if ($item->quantity > 1)
                        <span class="ao-qty">×{{ $item->quantity }}</span>
                    @endif
                </span>
            @endforeach
            @if ($items->count() > 3)
                <span class="ao-thumb ao-thumb--more">+{{ $items->count() - 3 }}</span>
            @endif
        </div>

        <div class="ao-summary">
            <div class="ao-title">
                {{ $firstName }}@if ($items->count() > 1) <span>and {{ $items->count() - 1 }} more</span>@endif
            </div>
            <div class="ao-meta">
                @if ($payLabel)
                    <span>{{ $payLabel }}@if ($payStatus) · <span class="ao-pay" data-status="{{ $payStatus }}">{{ ucfirst(str_replace('_', ' ', $payStatus)) }}</span>@endif</span>
                @endif
                @if ($shipment && $shipment->awb_code)
                    <span>{{ $shipment->courier_name ?? 'Courier' }} · AWB {{ $shipment->awb_code }}</span>
                @endif
            </div>
        </div>

        <div class="ao-total">
            <span>Total</span>
            <strong>₹{{ number_format($order->total, 2) }}</strong>
        </div>
    </div>

    @if ($cancellable)
        <div class="ao-note ao-note--warn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            <span>Changed your mind? You can cancel free of charge until your order ships.</span>
        </div>
    @endif

    <footer class="ao-actions">
        <button type="button" class="ou-btn ou-btn-ghost ao-toggle collapsed"
                data-bs-toggle="collapse" data-bs-target="#order-detail-{{ $order->id }}"
                aria-expanded="false" aria-controls="order-detail-{{ $order->id }}">
            View details
            <svg class="ao-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </button>

        {{-- The hard switch, not the storefront toggle: this is the customer's
             own order, so it stays reachable even when the public "Track your
             order" links are hidden. Never on a cancelled order. --}}
        @if ($trackingOn && ! $cancelled)
            <a class="ou-btn ou-btn-primary" href="{{ route('client.track.show', ['orderNo' => $order->order_no]) }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5h-7V8Z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                Track order
            </a>
        @endif

        @unless ($cancelled)
            <a class="ou-btn ou-btn-ghost" href="{{ route('client.invoice', ['orderNo' => $order->order_no]) }}" target="_blank" rel="noopener">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M9 13h6M9 17h6"/></svg>
                Invoice
            </a>
        @endunless

        @if ($order->isReturnable())
            <a class="ou-btn ou-btn-ghost" href="{{ route('client.returns.create', ['orderNo' => $order->order_no]) }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 5 5v6"/></svg>
                Return items
            </a>
        @endif

        {{-- Only while the order is still pending. Once it is confirmed it is
             being packed, and the way out is a return. --}}
        @if ($cancellable)
            <button type="button" class="ou-btn ou-btn-danger ao-cancel order-card__cancel" data-order-no="{{ $order->order_no }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/></svg>
                Cancel order
            </button>
        @endif
    </footer>

    <div class="collapse" id="order-detail-{{ $order->id }}">
        <div class="ao-detail">
            <div class="ao-items">
                @foreach ($items as $item)
                    @php
                        $customization = ! empty($item->customization)
                            ? (is_array($item->customization) ? $item->customization : json_decode($item->customization, true))
                            : [];
                    @endphp
                    <div class="ou-item">
                        <img class="ou-item-img" src="{{ $imgFor($item) }}" alt="{{ $item->product?->name ?? 'Product' }}" loading="lazy"
                             onerror="this.onerror=null;this.src='{{ $placeholder }}'">
                        <div class="ou-item-body">
                            <div class="ou-item-name">
                                @if (! empty($item->product?->slug))
                                    <a href="{{ route('client.product', ['productSlug' => $item->product->slug]) }}">{{ $item->product->name }}</a>
                                @else
                                    {{ $item->product?->name ?? 'Product' }}
                                @endif
                            </div>
                            <div class="ou-item-meta">
                                Qty {{ $item->quantity }} · ₹{{ number_format($item->price, 2) }} each
                                @if (! empty($item->size)) · Size {{ $item->size }} @endif
                                @if (! empty($item->color)) · {{ $item->color }} @endif
                            </div>
                            @if (! empty($customization['option']))
                                <div class="ou-item-meta">Option: {{ $customization['option'] }}</div>
                            @endif
                            @if (! empty($customization['names']) && is_array($customization['names']))
                                @foreach ($customization['names'] as $i => $name)
                                    <div class="ou-item-meta">Name {{ $i + 1 }}: {{ $name }}</div>
                                @endforeach
                            @endif
                        </div>
                        <div class="ou-item-price">₹{{ number_format($item->price * $item->quantity, 2) }}</div>
                    </div>
                @endforeach
            </div>

            <div class="ao-totals">
                <div class="ou-total"><span>Subtotal</span><span>₹{{ number_format($order->sub_total, 2) }}</span></div>
                <div class="ou-total"><span>Shipping</span><span>₹{{ number_format($order->shipping, 2) }}</span></div>
                @if ($order->discount > 0)
                    <div class="ou-total ou-total-save"><span>Discount</span><span>− ₹{{ number_format($order->discount, 2) }}</span></div>
                @endif
                <div class="ou-total ou-total-grand"><span>Total</span><span>₹{{ number_format($order->total, 2) }}</span></div>
            </div>
        </div>
    </div>
</article>
