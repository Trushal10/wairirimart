@extends('layouts.client')

@section('title')
    My Wishlist | {{ config('app.name') }}
@endsection

@section('meta_description')
    Your saved items on {{ config('app.name') }}. Add them to your cart and enjoy fast delivery across India.
@endsection

@section('style')
<style>
    /* Grid — respects the existing site container breakpoints */
    .wl-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }
    @media (min-width: 576px) { .wl-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; } }
    @media (min-width: 768px) { .wl-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px; } }
    @media (min-width: 992px) { .wl-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }

    .wl-count {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 20px; flex-wrap: wrap; gap: 12px;
    }
    .wl-count-badge {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 12px; border-radius: 999px;
        background: #f2f4f7; color: #344054;
        font-size: 13px; font-weight: 600;
    }
    .wl-count-badge svg { color: #e53935; }
    .wl-clear-all {
        display: inline-flex; align-items: center; gap: 6px;
        background: transparent; border: 0; padding: 0;
        color: #667085; font-size: 13px; font-weight: 500;
        cursor: pointer; transition: color .15s ease;
    }
    .wl-clear-all:hover { color: #e53935; }

    /* Card — mirrors the .card-product visual language */
    .wl-card {
        position: relative;
        background: #fff;
        border: 1px solid #eef0f3;
        border-radius: 14px;
        overflow: hidden;
        transition: box-shadow .18s ease, transform .18s ease, border-color .18s ease;
        display: flex; flex-direction: column;
    }
    .wl-card:hover {
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
        border-color: #e4e7ec;
        transform: translateY(-2px);
    }
    .wl-card__media {
        position: relative;
        aspect-ratio: 1;
        overflow: hidden;
        background: #f5f6f8;
    }
    .wl-card__media img {
        width: 100%; height: 100%;
        object-fit: cover;
        transition: transform .28s ease;
    }
    .wl-card:hover .wl-card__media img { transform: scale(1.03); }

    /* Compact icon-only remove button (top-right of image) */
    .wl-remove-btn {
        position: absolute; top: 10px; right: 10px;
        width: 34px; height: 34px;
        padding: 0; border: 0;
        display: inline-flex; align-items: center; justify-content: center;
        background: #ffffff; color: #667085;
        border-radius: 50%;
        box-shadow: 0 4px 10px rgba(0, 0, 0, .08);
        cursor: pointer;
        transition: background .15s ease, color .15s ease, transform .15s ease;
        z-index: 2;
    }
    .wl-remove-btn:hover { background: #ffe4e6; color: #b42318; transform: scale(1.05); }
    .wl-remove-btn:active { transform: scale(0.95); }
    .wl-remove-btn:focus-visible { outline: 0; box-shadow: 0 0 0 3px rgba(229, 57, 53, 0.28); }

    /* Sale badge (top-left of image) */
    .wl-card__badge {
        position: absolute; top: 10px; left: 10px;
        display: inline-flex; align-items: center;
        padding: 4px 10px; border-radius: 999px;
        background: linear-gradient(135deg, #e53935, #c62828);
        color: #fff; font-size: 11px; font-weight: 700;
        box-shadow: 0 4px 10px rgba(198, 40, 40, .28);
        z-index: 2;
    }

    .wl-card__body { padding: 14px 14px 16px; display: flex; flex-direction: column; gap: 10px; }
    .wl-card__name {
        font-size: 14px; font-weight: 600; color: #101828;
        line-height: 1.35;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 38px;
        text-decoration: none;
    }
    .wl-card__name:hover { color: #465fff; text-decoration: none; }
    .wl-card__price { display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; }
    .wl-card__price .now { font-size: 16px; font-weight: 700; color: #101828; letter-spacing: -0.01em; }
    .wl-card__price .was { font-size: 12px; color: #98a2b3; text-decoration: line-through; }

    /* Meta line — variant text or stock status */
    .wl-card__meta { font-size: 12px; color: #667085; }
    .wl-card__meta.oos { color: #b42318; font-weight: 600; }

    /* Actions */
    .wl-card__cta {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        width: 100%;
        padding: 10px 12px;
        border: 0;
        border-radius: 10px;
        background: #101828; color: #fff;
        font-size: 13px; font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: background .15s ease, transform .15s ease;
    }
    .wl-card__cta:hover { background: #1d2939; color: #fff; transform: translateY(-1px); }
    .wl-card__cta:active { transform: translateY(0); }
    .wl-card__cta:disabled { background: #98a2b3; cursor: not-allowed; transform: none; }
    .wl-card__cta.wl-card__cta-outline {
        background: #fff; color: #101828;
        border: 1px solid #d0d5dd;
    }
    .wl-card__cta.wl-card__cta-outline:hover { background: #f9fafb; color: #101828; }

    /* Empty state */
    .wl-empty {
        text-align: center;
        padding: 56px 20px;
        border: 1px dashed #e4e7ec;
        border-radius: 18px;
        background: #fafbfc;
        margin: 20px 0 40px;
    }
    .wl-empty__icon {
        width: 84px; height: 84px; margin: 0 auto 16px;
        border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        background: #fee4e6; color: #e53935;
    }
    .wl-empty h4 { color: #101828; margin-bottom: 8px; font-size: 20px; font-weight: 700; }
    .wl-empty p  { color: #667085; font-size: 14px; margin-bottom: 20px; }
    .wl-empty__cta {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 12px 24px;
        border-radius: 999px;
        background: #101828; color: #fff;
        font-size: 14px; font-weight: 600; letter-spacing: .02em;
        text-decoration: none;
        transition: background .15s ease, transform .15s ease;
    }
    .wl-empty__cta:hover { background: #1d2939; color: #fff; transform: translateY(-1px); }

    .wl-card.is-removing { opacity: .55; pointer-events: none; }
</style>
@endsection

@section('content')
    @include('client.partials.page-hero', [
        'title'  => 'My Wishlist',
        'crumbs' => [['label' => 'Wishlist']],
    ])

    <section class="flat-spacing">
        <div class="container">
            @if ($items->isEmpty())
                <div class="wl-empty">
                    <div class="wl-empty__icon">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                        </svg>
                    </div>
                    <h4>Your wishlist is empty</h4>
                    <p>Save your favourite products here and come back to buy them later.</p>
                    <a href="{{ route('client.shop') }}" class="wl-empty__cta">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
                        </svg>
                        Browse shop
                    </a>
                </div>
            @else
                <div class="wl-count">
                    <span class="wl-count-badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                        </svg>
                        {{ $items->count() }} saved item{{ $items->count() !== 1 ? 's' : '' }}
                    </span>
                </div>

                <div class="wl-grid" id="wl-grid">
                    @foreach ($items as $item)
                        @php $product = $item->product; @endphp
                        @continue(! $product)
                        @php
                            $image = $product->medias?->first()?->url
                                ? asset('storage/product/' . $product->medias->first()->url)
                                : asset('client/images/home/product-placeholder.webp');
                            $price = $product->price ?? 0;
                            $comparePrice = $product->compere_price ?? 0;
                            $hasSale = $comparePrice > 0 && $comparePrice > $price;
                            $discountPct = $hasSale ? round((($comparePrice - $price) / $comparePrice) * 100) : 0;
                            $hasVariants = !empty($product->has_variants);
                            $rawStock = (int) ($hasVariants ? ($product->variants_stock_sum ?? 0) : ($product->stock ?? 0));
                            $isOos = $rawStock <= 0;
                        @endphp
                        <article class="wl-card {{ $isOos ? 'is-oos' : '' }}" id="wl-card-{{ $item->id }}" data-product-id="{{ $product->id }}">
                            <div class="wl-card__media">
                                @if ($hasSale)
                                    <span class="wl-card__badge">{{ $discountPct }}% OFF</span>
                                @endif
                                <button
                                    type="button"
                                    class="wl-remove-btn wishlist-remove-inline"
                                    data-product-id="{{ $product->id }}"
                                    aria-label="Remove {{ $product->name }} from wishlist"
                                    title="Remove from wishlist"
                                >
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                    </svg>
                                </button>
                                <a href="{{ route('client.product', ['productSlug' => $product->slug]) }}" aria-label="View {{ $product->name }}">
                                    <img src="{{ $image }}" alt="{{ $product->name }}" loading="lazy"
                                         onerror="this.src='{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}'">
                                </a>
                            </div>
                            <div class="wl-card__body">
                                <a href="{{ route('client.product', ['productSlug' => $product->slug]) }}" class="wl-card__name">
                                    {{ $product->name }}
                                </a>
                                <div class="wl-card__price">
                                    <span class="now">₹{{ number_format($price, 2) }}</span>
                                    @if ($hasSale)
                                        <span class="was">₹{{ number_format($comparePrice, 2) }}</span>
                                    @endif
                                </div>
                                @if ($isOos)
                                    <div class="wl-card__meta oos">Out of stock</div>
                                @endif
                                @if ($isOos)
                                    <a href="{{ route('client.product', ['productSlug' => $product->slug]) }}" class="wl-card__cta wl-card__cta-outline">
                                        View product
                                    </a>
                                @elseif ($hasVariants)
                                    <button
                                        type="button"
                                        class="wl-card__cta quick-add"
                                        data-product-id="{{ $product->id }}"
                                        data-url="{{ route('client.quickAdd') }}"
                                    >
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                                        </svg>
                                        Choose options
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        class="wl-card__cta btn-main-product wl-add-to-cart"
                                        data-product-id="{{ $product->id }}"
                                        data-url="{{ route('client.addToCart') }}"
                                    >
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                                        </svg>
                                        Add to cart
                                    </button>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection

@section('scripts')
<script>
(function () {
    // AJAX remove — no browser confirm, just a toast. Row fades out on success.
    $(document).off('click.wlrem').on('click.wlrem', '.wishlist-remove-inline', function (e) {
        e.preventDefault();
        var $btn  = $(this);
        var pid   = parseInt($btn.data('product-id'), 10);
        var $card = $btn.closest('.wl-card');

        $card.addClass('is-removing');
        $.ajax({
            url: '{{ route('client.wishlist.toggle') }}',
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { product_id: pid },
            success: function (res) {
                $card.fadeOut(180, function () {
                    $(this).remove();
                    if ($('#wl-grid .wl-card').length === 0) {
                        location.reload();
                    }
                });
                if (typeof updateWishlistCount === 'function') updateWishlistCount(res.count);
                if (typeof showSweetAlert === 'function') {
                    showSweetAlert('success', res.message || 'Removed from wishlist.');
                }
            },
            error: function (xhr) {
                $card.removeClass('is-removing');
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not remove item. Please try again.';
                if (typeof showSweetAlert === 'function') showSweetAlert('error', msg);
            }
        });
    });

    // Simple in-place add-to-cart for single-SKU products (variant products
    // still open the quick-add modal via .quick-add — handled by global JS).
    $(document).off('click.wladd').on('click.wladd', '.wl-add-to-cart', function (e) {
        e.preventDefault();
        var $btn = $(this);
        if ($btn.is(':disabled') || $btn.prop('disabled')) return;
        var pid = parseInt($btn.data('product-id'), 10);
        var url = $btn.data('url');
        $btn.prop('disabled', true);
        $.ajax({
            url: url, type: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { productId: pid, quantity: 1 },
            dataType: 'json',
            success: function (res) {
                $btn.prop('disabled', false);
                if (res && res.success) {
                    if (typeof updateCartModal === 'function') updateCartModal(res.cart);
                    if (typeof updateCartCount === 'function') updateCartCount(res.cart);
                    if (typeof showSweetAlert === 'function') showSweetAlert('success', 'Added to cart.');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false);
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not add to cart.';
                if (typeof showSweetAlert === 'function') showSweetAlert('error', msg);
            }
        });
    });
})();
</script>
@endsection
