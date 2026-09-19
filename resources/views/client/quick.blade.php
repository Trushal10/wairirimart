@php
    $defaultImage = asset('client/images/home/product-placeholder.webp');
    $mainImage = !empty($product['medias'][0]['url']) ? asset('storage/product/' . $product['medias'][0]['url']) : $defaultImage;

    $hasVariants     = !empty($product['has_variants']);
    $variantsPayload = $variants ?? [];
    $groups          = $attributeGroups ?? [];

    // The variant to open on — the admin's default while it has stock, else the
    // first in-stock one — is chosen by VariantOptionService with the payload.
    $defaultVariant = ($hasVariants && !empty($variantsPayload))
        ? ($displayVariant ?? $variantsPayload[0])
        : null;

    $displayPrice   = $defaultVariant ? (float) $defaultVariant['price'] : (float) $product['price'];
    $displayCompare = $defaultVariant ? (float) $defaultVariant['compare_price'] : (float) ($product['compere_price'] ?? 0);
    $displayStock   = $defaultVariant ? (int) $defaultVariant['stock'] : (int) ($product['stock'] ?? 0);
    $hasSale        = $displayCompare > 0 && $displayCompare > $displayPrice;
@endphp

<div class="tf-product-info-list quick-add-modal">
    <div class="tf-product-info-item">
        <div class="image">
            <img src="{{ $mainImage }}" alt="{{ $product['name'] }}" data-role="variant-image">
        </div>
        <div class="content">
            <a href="{{ route('client.product', ['productSlug' => $product['slug'] ?? '']) }}" class="quick-title">{{ $product['name'] }}</a>
            <div class="tf-product-info-price">
                <h5 class="price-on-sale font-2" data-role="variant-price">₹{{ number_format($displayPrice, 2) }}</h5>
                <div class="compare-at-price font-2" data-role="variant-compare" style="{{ $hasSale ? '' : 'display:none;' }}">
                    @if($hasSale) ₹{{ number_format($displayCompare, 2) }} @endif
                </div>
                <div class="badges-on-sale text-btn-uppercase" data-role="variant-discount" style="{{ $hasSale ? '' : 'display:none;' }}">
                    @if($hasSale)
                        {{ round((($displayCompare - $displayPrice) / $displayCompare) * 100) }}% OFF
                    @endif
                </div>
            </div>
            <p class="quick-stock text-caption-1" data-role="variant-stock">
                {{ $displayStock > 0 ? 'In stock ('.$displayStock.')' : 'Out of stock' }}
            </p>
        </div>
    </div>

    <div class="tf-product-info-choose-option">

        {{-- Modern variant pickers (product_variants + option_types) --}}
        @if ($hasVariants && !empty($groups))
            @include('components.variant-picker', [
                'groups'   => $groups,
                'variants' => $variantsPayload,
                'selected' => ($defaultVariant['options'] ?? []) ?: [],
                'idPrefix' => 'quick-opt',
            ])

        {{-- Legacy: color radios (no variants table row) --}}
        @elseif (!empty($product['color']))
            @php $colors = json_decode($product['color']); @endphp
            @if(!empty($colors))
            <div class="quick-choose variant-picker-item">
                <div class="variant-picker-label mb_12">
                    Color:<span class="text-title variant-picker-label-value value-currentColor">{{ $colors[0] }}</span>
                </div>
                <div class="variant-picker-values gap12">
                    @foreach ($colors as $key => $color)
                        <input class="color-product" id="color-{{ $color }}" type="radio" name="color" value="{{ $color }}" {{ $key == 0 ? 'checked' : '' }}>
                        <label class="color-btn style-text-1 style-rounded radius-60 {{ $key == 0 ? 'active' : '' }}" for="color-{{ $color }}" data-value="{{ $color }}" data-color="{{ $color }}">
                            <span class="text-title">{{ $color }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            @endif
        @endif

        {{-- Legacy: sizes radios (only shown when NOT using the modern variant table) --}}
        @if (!$hasVariants && !empty($product['sizes']))
            @php $sizes = json_decode($product['sizes']); @endphp
            @if(!empty($sizes))
            <div class="quick-choose variant-picker-item">
                <div class="variant-picker-label mb_12">
                    Sizes:<span class="text-title variant-picker-label-value value-currentZise">{{ $sizes[0] }}</span>
                </div>
                <div class="variant-picker-values gap12">
                    @foreach ($sizes as $key => $size)
                        <input id="size-{{ $size }}" class="size-product" type="radio" name="size" value="{{ $size }}" {{ $key == 0 ? 'checked' : '' }}>
                        <label class="size-btn style-text-1 style-rounded radius-60 {{ $key == 0 ? 'active' : '' }}" for="size-{{ $size }}" data-value="{{ $size }}" data-size="{{ $size }}">
                            <span class="text-title">{{ $size }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            @endif
        @endif

        @include('components.quantity-picker', [
            'stock' => $displayStock,
            'type'  => 'text',
            'class' => 'quick-choose',
            'show'  => $product['show_quantity'] ?? true,
        ])

        <div class="quick-actions">
            <div class="tf-product-info-by-btn">
                <button
                    type="button"
                    data-product-id="{{ $product['id'] }}"
                    data-url="{{ route('client.addToCart') }}"
                    data-variant-id="{{ $defaultVariant['id'] ?? '' }}"
                    class="tf-btn btn-fill radius-4 flex-grow-1 fw-6 show-shopping-cart add-to-cart"
                    {{ $displayStock < 1 ? 'disabled' : '' }}
                >
                    <span class="text" data-role="variant-cta-label">{{ $displayStock < 1 ? 'Out of Stock' : 'Add to cart' }}</span>
                </button>
            </div>
        </div>
    </div>
</div>

@if ($hasVariants && !empty($variantsPayload))
<script>
// The modal body is swapped in via AJAX on every open, so boot the shared
// picker (public/client/js/variant-picker.js) against this freshly injected
// markup rather than waiting on DOMContentLoaded. The picker takes a ROOT and
// finds the group container inside it, so this must be the modal element —
// passing the container itself leaves it with nothing to search.
(function () {
    var root = document.querySelector('.quick-add-modal');
    if (root && typeof window.initVariantPicker === 'function') {
        window.initVariantPicker(root);
    }
})();
</script>
@endif
