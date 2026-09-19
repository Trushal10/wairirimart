<div class="card-product-wrapper">
    @php
        $defaultImage = asset('client/images/home/product-placeholder.webp');
        $mainImage = !empty($product['medias'][0]['url']) ? asset('storage/product/' . $product['medias'][0]['url']) : $defaultImage;
        $hoverImage = !empty($product['medias'][1]['url']) ? asset('storage/product/' . $product['medias'][1]['url']) : $mainImage;

        // Effective stock: for variant products the base column is 0 by design,
        // so read the SQL rollup the list controller adds via withSum('variants').
        // Falls back to the base column for legacy / single-SKU products.
        $cardHasVariants  = !empty($product['has_variants']);
        $cardEffectiveStock = $cardHasVariants
            ? (int) ($product['variants_stock_sum'] ?? 0)
            : (int) ($product['stock'] ?? 0);
    @endphp
    @php
        // Never set src and data-src together: that makes the browser fetch the
        // image natively and lazysizes fetch it again. lazysizes owns these,
        // because native loading="lazy" does not actually defer them on a
        // throttled connection — Chrome widens its lazy threshold there, so a
        // grid of product cards (two images each, counting the hover state)
        // downloads during initial load and starves the LCP image.
        $__cardSizes  = '(max-width: 575px) 50vw, (max-width: 991px) 33vw, 25vw';
        $__mainSet    = !empty($product['medias'][0]['url']) ? \App\Helper\CommonHelper::srcsetFor('product/' . $product['medias'][0]['url']) : '';
        $__hoverSet   = !empty($product['medias'][1]['url']) ? \App\Helper\CommonHelper::srcsetFor('product/' . $product['medias'][1]['url']) : '';
        // On a product grid the top row is on screen immediately and holds the
        // LCP element, so those cards opt in to a normal eager <img>: handing
        // them to lazysizes would hide them from the preload scanner and stall
        // the LCP behind the whole script bundle. Callers that render cards
        // below the fold (the homepage carousels, related products) leave this
        // unset and get the deferred path.
        $__eagerImage = ! empty($eagerImage);
    @endphp
    <a href="{{ route('client.product', ['productSlug' => $product['slug']]) }}" class="product-img">
        @if ($__eagerImage)
            <img class="img-product" src="{{ $mainImage }}"
                @if ($__mainSet) srcset="{{ $__mainSet }}" sizes="{{ $__cardSizes }}" @endif
                alt="{{ $product['name'] }}" width="400" height="400" fetchpriority="high" decoding="async"
                onerror="this.onerror=null;this.removeAttribute('srcset');this.src='{{ $defaultImage }}'">
        @else
            <img class="img-product lazyload" data-src="{{ $mainImage }}"
                @if ($__mainSet) data-srcset="{{ $__mainSet }}" data-sizes="{{ $__cardSizes }}" @endif
                alt="{{ $product['name'] }}" width="400" height="400" decoding="async"
                onerror="this.onerror=null;this.removeAttribute('srcset');this.src='{{ $defaultImage }}'">
        @endif
        {{-- The hover image is never the LCP and is invisible until pointer-over,
             so it always waits, even in the top row. --}}
        <img class="img-hover lazyload" data-src="{{ $hoverImage }}"
            @if ($__hoverSet) data-srcset="{{ $__hoverSet }}" data-sizes="{{ $__cardSizes }}" @endif
            alt="{{ $product['name'] }} — alternate view" width="400" height="400" decoding="async"
            onerror="this.onerror=null;this.removeAttribute('srcset');this.src='{{ $defaultImage }}'">
    </a>
    @if($product['compere_price'] > 0)
    <div class="on-sale-wrap">
        <span class="on-sale-item">
            {{round((($product['compere_price'] - $product['price'])/$product['compere_price'])*100)}}% Off
        </span>
    </div>
    @endif
    <div class="list-btn-main">
        @if($cardEffectiveStock <= 0)
            <button type="button" class="text-danger btn-main-product" data-product-id="{{$product['id']}}" data-url="{{route('client.quickAdd')}}" disabled aria-disabled="true">
                Out of stock
            </button>
        @else
        <button type="button" data-product-id="{{$product['id']}}" data-url="{{route('client.quickAdd')}}" class="btn-main-product quick-add">
            Quick Add
        </button>
        @endif
    </div>
    {{-- Wishlist heart button --}}
    <button type="button"
        class="wishlist-toggle-btn"
        data-product-id="{{ $product['id'] }}"
        data-url="{{ route('client.wishlist.toggle') }}"
        title="Add to wishlist"
        aria-label="Add to wishlist"
        aria-pressed="false">
        <svg class="wishlist-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
        </svg>
    </button>
</div>
<div class="card-product-info">
    <a href="{{route('client.product', ['productSlug' => $product['slug']])}}" class="title link">{{$product['name']}}</a>
    <div class="price">
        @if($product['compere_price'] > 0)
        <span class="old-price">₹{{ number_format((float) $product['compere_price'], 2) }}</span>
        @endif
        ₹<span class="current-price">{{ number_format((float) $product['price'], 2) }}</span>
    </div>
</div>