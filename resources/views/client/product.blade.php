@extends('layouts.client')

@section('title')
    {{$product['name'] ?? 'Product'}} | {{ config('app.name') }}
@endsection

@section('meta_description')
@php
$category = collect($product['getCategoryDetails'])->pluck('name')->implode(', ');
@endphp
{{ 'Shop ' . $product['name'] . ' at ' . config('app.name') . '. ' . (trim($category) ? 'Part of our ' . $category . ' collection. ' : '') . 'Durable, non-toxic craft materials shipped across India with easy returns and fast delivery.' }}
@endsection

@section('og_type', 'product')
@php
    $productImg = ! empty($product['medias'][0]['url'])
        ? asset('storage/product/' . $product['medias'][0]['url'])
        : asset('client/images/logo/logo.svg');
@endphp
@section('og_image', $productImg)

@section('structured_data')
@php
    $breadcrumbTrail = [
        ['name' => 'Home', 'url' => route('client.home')],
        ['name' => 'Shop', 'url' => route('client.shop')],
    ];
    $firstCategoryName = optional(collect($product['getCategoryDetails'] ?? [])->first())['name'] ?? null;
    if ($firstCategoryName) {
        $breadcrumbTrail[] = ['name' => $firstCategoryName, 'url' => route('client.category')];
    }
    $breadcrumbTrail[] = ['name' => $product['name'] ?? 'Product', 'url' => url()->current()];
@endphp
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org/',
    '@type' => 'Product',
    'name' => $product['name'] ?? '',
    'image' => [$productImg],
    'description' => strip_tags($product['short_description'] ?? ''),
    'sku' => $product['sku'] ?? null,
    'brand' => ['@type' => 'Brand', 'name' => config('app.name')],
    'offers' => [
        '@type' => 'Offer',
        'url' => url()->current(),
        'priceCurrency' => 'INR',
        'price' => number_format((float) ($product['price'] ?? 0), 2, '.', ''),
        'availability' => (
            !empty($product['has_variants'])
                ? collect($data['variants'] ?? [])->sum('stock')
                : (int) ($product['stock'] ?? 0)
        ) > 0
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock',
        'itemCondition' => 'https://schema.org/NewCondition',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
{!! \App\Helper\SeoHelper::breadcrumbsJsonLd($breadcrumbTrail) !!}
@endsection

@section('style')
    <style>
        /* ============================ Review form (redesigned) ============================ */
        .review-card { background:#fff; border:1px solid #eee; border-radius:16px; padding:28px; box-shadow:0 6px 24px rgba(0,0,0,.03); }
        .review-card__head { margin-bottom:20px; }
        .review-card__title { font-size:20px; font-weight:600; color:var(--ds-charcoal); margin:0 0 4px; }
        .review-card__hint { font-size:13px; color:#888; margin:0; }
        .review-form { display:flex; flex-direction:column; gap:18px; }
        .review-field { display:flex; flex-direction:column; gap:6px; }
        .review-label { font-size:13px; font-weight:600; color:#333; letter-spacing:.2px; }
        .review-label .req { color:var(--ds-danger); }
        .review-optional { color:#999; font-weight:400; font-size:11px; }
        .review-input {
            width:100%; padding:11px 14px; border:1px solid #e0e0e0; border-radius:10px;
            font-size:14px; color:var(--ds-charcoal); background:#fff; transition:border-color .15s, box-shadow .15s;
            font-family:inherit;
        }
        .review-input:focus { outline:none; border-color:var(--ds-charcoal); box-shadow:0 0 0 3px rgba(0,0,0,.06); }
        .review-input::placeholder { color:#aaa; }
        .review-hint { font-size:11px; color:#999; text-align:right; }
        .review-error { min-height:0; font-size:12px; color:var(--ds-danger); }
        .review-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        @media (max-width:576px){ .review-grid-2 { grid-template-columns:1fr; } }

        /* Star rating (upgrade of the existing .list-rating-check) */
        .star-rating { display:inline-flex; flex-direction:row-reverse; gap:2px; }
        .star-rating input { display:none; }
        .star-rating label { cursor:pointer; width:28px; height:28px; background:transparent; position:relative; }
        .star-rating label::before {
            content:''; position:absolute; inset:0;
            background:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23d4d4d4'><path d='M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z'/></svg>") no-repeat center/24px 24px;
            transition:.15s;
        }
        .star-rating input:checked ~ label::before,
        .star-rating label:hover::before,
        .star-rating label:hover ~ label::before {
            background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23f5b400'><path d='M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z'/></svg>");
        }

        /* Dropzone */
        .review-dropzone {
            display:block; border:2px dashed #d5d5d5; border-radius:12px; padding:22px 20px;
            background:#fafafa; cursor:pointer; text-align:center; transition:.15s; min-height:150px; position:relative;
        }
        .review-dropzone:hover, .review-dropzone.drag-over { border-color:var(--ds-charcoal); background:#f4f4f4; }
        .review-dropzone__idle { color:#666; display:flex; flex-direction:column; align-items:center; gap:8px; padding:20px 0; }
        .review-dropzone__idle svg { color:#999; }
        .review-dropzone__title { font-size:14px; font-weight:500; color:#333; }
        .review-dropzone__sub { font-size:12px; color:#888; }
        .review-dropzone__preview { position:relative; display:inline-block; margin:0 auto; }
        .review-dropzone__preview img { max-height:160px; border-radius:10px; display:block; }
        .review-dropzone__remove {
            position:absolute; top:-8px; right:-8px; width:26px; height:26px; border-radius:50%;
            background:var(--ds-charcoal); color:#fff; border:2px solid #fff; cursor:pointer; font-size:16px; line-height:1;
            box-shadow:0 2px 6px rgba(0,0,0,.15);
        }
        .review-dropzone__remove:hover { background:var(--ds-danger); }

        /* Checkbox */
        .review-checkbox { display:flex; align-items:flex-start; gap:8px; cursor:pointer; font-size:13px; color:#555; }
        .review-checkbox input { accent-color:var(--ds-charcoal); width:16px; height:16px; margin-top:2px; }

        /* Footer / submit */
        .review-footer { display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; padding-top:8px; border-top:1px solid #eee; }
        .review-footer__hint { font-size:11px; color:#999; }
        .review-submit {
            background:var(--ds-charcoal); color:#fff; padding:12px 28px; border-radius:999px;
            font-size:13px; font-weight:600; letter-spacing:.5px; text-transform:uppercase;
            border:0; cursor:pointer; transition:.15s;
        }
        .review-submit:hover { background:#333; }
        .review-submit:disabled { opacity:.55; cursor:not-allowed; }

        /* Response banners */
        .review-message:empty { display:none; }
        .review-message .banner { padding:10px 14px; border-radius:8px; font-size:13px; margin-top:8px; }
        .review-message .banner-success { background:#e2f7e9; color:#177a3b; border:1px solid #b6e5c3; }
        .review-message .banner-error { background:#fdecec; color:var(--ds-danger); border:1px solid #f5c4c4; }
        .review-message .banner-info { background:#e5f0ff; color:#1758c6; border:1px solid #b8d2f5; }

        /* Not-signed-in state */
        .review-signin { text-align:center; padding:24px 16px; color:#333; }
        .review-signin svg { color:#bbb; margin:0 auto 8px; display:block; }
        .review-signin h5 { font-size:16px; font-weight:600; color:var(--ds-charcoal); margin:0 0 6px; }
        .review-signin p { font-size:13px; color:#777; margin:0 0 14px; max-width:340px; margin-left:auto; margin-right:auto; }

        /* Legacy compatibility for other places on the page still using the old class */
        .image-upload-box { border:2px dashed #ccc; padding:20px; text-align:center; border-radius:12px; cursor:pointer; transition:.3s; background:#fafafa; }
        .image-upload-box:hover { border-color:#777; background:#f0f0f0; }
        .preview-img { max-width:100px; margin-top:10px; border-radius:8px; display:block; }
    </style>
@endsection
@section('content')
    @php
        use App\Models\ProductMedia;
    @endphp
    <!-- breadcrumb -->
    <div class="tf-breadcrumb">
        <div class="container">
            <div class="tf-breadcrumb-wrap pb-3 pt-0">
                <div class="tf-breadcrumb-list">
                    <a href="{{route('client.home')}}" class="text text-caption-1">Homepage</a>
                    <i class="icon icon-arrRight"></i>
                    <a href="{{ route('client.shop') }}" class="text text-caption-1">Shop</a>
                    <i class="icon icon-arrRight"></i>
                    <span class="text text-caption-1">{{$product['name'] ?? ''}}</span>
                </div>
            </div>
        </div>
    </div>
    <!-- /breadcrumb -->

    <!-- tf-add-cart-success -->
    <div class="tf-add-cart-success">
        <div class="tf-add-cart-heading">
            <h5>Shopping Cart</h5>
            <i class="icon icon-close tf-add-cart-close"></i>
        </div>
        <div class="tf-add-cart-product">
            <div class="image">
                @php
                $defaultImage = asset('client/images/home/product-placeholder.webp');
                $mainImage = !empty($product['medias'][0]['url']) ? asset('storage/product/' . $product['medias'][0]['url']) : $defaultImage;
                @endphp
                <img class=" ls-is-cached lazyloaded" data-src="{{$mainImage}}" alt="{{$product['name']}}" src="{{$mainImage}}">
            </div>
            <div class="content">
                <div class="text-title">
                    <a class="link" href="{{route('client.product', ['productSlug'=>$product['slug']])}}">{{$product['name'] ?? ''}}</a>
                </div>
                @php
                    $category = $product['getCategoryDetails']->pluck('name')->toArray();
                @endphp  
                <div class="text-caption-1 text-secondary-2">
                    {{implode(', ', $category)}}
                </div>
                <div class="text-title">₹{{$product['price']}}</div>
            </div>
        </div>
        <a href="{{route('client.shoppingcart')}}" class="tf-btn w-100 btn-fill radius-4">
            <span class="text text-btn-uppercase">View cart</span>
        </a>
    </div>
    <!-- /tf-add-cart-success -->

    <!-- Product_Main -->
    <section class="">
        <div class="tf-main-product section-image-zoom">
            <div class="container">
                <div class="row">
                    <!-- Product default -->
                    <div class="col-md-6">
                        <div class="tf-product-media-wrap sticky-top">
                            <div class="thumbs-slider">
                                <div dir="ltr" class="swiper tf-product-media-thumbs other-image-zoom" data-direction="vertical">
                                    <div class="swiper-wrapper stagger-wrap">
                                        @if (count($product['medias']) > 0)
                                            @foreach ($product['medias'] as $media)
                                                @if($media['type'] == ProductMedia::IMAGE)
                                                <div class="swiper-slide stagger-item">
                                                    <div class="item">
                                                        <img class="lazyload" data-src="{{asset('storage/product/'.$media['url'])}}" src="{{asset('storage/product/'.$media['url'])}}" alt="{{$product['name']}}">
                                                    </div>
                                                </div>
                                                @endif
                                            @endforeach
                                        @else
                                            <div class="swiper-slide stagger-item">
                                                <div class="item">
                                                    <img class="lazyload" data-src="{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}" src="{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}" alt="{{$product['name']}}">
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div dir="ltr" class="swiper tf-product-media-main" id="gallery-swiper-started">
                                    <div class="swiper-wrapper">
                                        @if (count($product['medias']) > 0)
                                            @foreach ($product['medias'] as $media)
                                                @if($media['type'] == ProductMedia::IMAGE)
                                                <div class="swiper-slide">
                                                    <a href="{{asset('storage/product/'.$media['url'])}}" target="_blank" class="item" data-pswp-width="600px" data-pswp-height="800px">
                                                        <img class="tf-image-zoom lazyload" data-zoom="{{asset('storage/product/'.$media['url'])}}" data-src="{{asset('storage/product/'.$media['url'])}}" src="{{asset('storage/product/'.$media['url'])}}" alt="{{$product['name']}}">
                                                    </a>
                                                </div>
                                                @endif
                                            @endforeach
                                        @else
                                            <div class="swiper-slide">
                                                <a href="{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}" target="_blank" class="item" data-pswp-width="600px" data-pswp-height="800px">
                                                    <img class="tf-image-zoom lazyload" data-zoom="{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}" data-src="{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}" src="{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}" alt="{{$product['name']}}">
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>  
                        </div>
                    </div>
                    <!-- /Product default -->
                    <!-- tf-product-info-list -->
                    <div class="col-md-6">
                        <div class="tf-product-info-wrap position-relative">
                            <div class="tf-zoom-main"></div>
                                <div class="tf-product-info-list other-image-zoom">
                                    <div class="tf-product-info-heading">
                                        <div class="tf-product-info-name">
                                            <h3 class="name">{{$product['name'] ?? ''}}</h3>
                                        </div>
                                        <div class="tf-product-info-desc">
                                            @php
                                                // The presenter picks the variant to open on: the admin's
                                                // default while it has stock, else the first in-stock one.
                                                $defaultVariant = $product['has_variants'] ? ($data['displayVariant'] ?? null) : null;
                                                $displayPrice = $defaultVariant ? $defaultVariant['price'] : (float) $product['price'];
                                                $displayCompare = $defaultVariant ? (float) $defaultVariant['compare_price'] : (float) $product['compere_price'];
                                                $displayStock = $defaultVariant ? (int) $defaultVariant['stock'] : (int) $product['stock'];
                                                $displaySku = $defaultVariant && !empty($defaultVariant['sku']) ? $defaultVariant['sku'] : $product['sku'];
                                            @endphp
                                            <div class="tf-product-info-price">
                                                <h5 class="price-on-sale font-2" data-role="variant-price">₹{{ number_format($displayPrice, 2) }}</h5>
                                                <div class="compare-at-price font-2" data-role="variant-compare"
                                                    style="{{ $displayCompare > $displayPrice ? '' : 'display:none;' }}">
                                                    @if ($displayCompare > $displayPrice) ₹{{ number_format($displayCompare, 2) }} @endif
                                                </div>
                                                <div class="badges-on-sale text-btn-uppercase" data-role="variant-discount"
                                                    style="{{ $displayCompare > $displayPrice ? '' : 'display:none;' }}">
                                                    @if ($displayCompare > $displayPrice)
                                                        {{ round((($displayCompare - $displayPrice) / $displayCompare) * 100) }}% Off
                                                    @endif
                                                </div>
                                            </div>
                                            <p>{{$product['short_description']}}</p>
                                        </div>
                                    </div>
                                    {{-- Variant picker: only rendered when admin has enabled variants
                                         for this product. Single-SKU products go straight from title →
                                         quantity → add-to-cart with no picker. --}}
                                    <div class="tf-product-info-choose-size my-2">
                                        @if ($product['has_variants'] && ! empty($data['attributeGroups']))
                                            @include('components.variant-picker', [
                                                'groups'   => $data['attributeGroups'],
                                                'variants' => $data['variants'],
                                                'selected' => ($defaultVariant['options'] ?? []) ?: [],
                                                'idPrefix' => 'pdp-opt',
                                            ])
                                        @endif
                                        @include('components.quantity-picker', [
                                            'stock' => $displayStock,
                                            'type'  => 'number',
                                            'show'  => $product['show_quantity'] ?? true,
                                        ])
                                        <div>
                                            <div class="tf-product-info-by-btn mb_10">
                                                <button class="tf-btn btn-fill radius-4 flex-grow-1 fw-6 btn-add-to-cart add-to-cart" type="button"
                                                    data-product-id="{{ $product['id'] }}"
                                                    data-variant-id="{{ $defaultVariant['id'] ?? '' }}"
                                                    data-url="{{ route('client.addToCart') }}"
                                                    {{ $displayStock < 1 ? 'disabled' : '' }}>
                                                    <span class="text" data-role="variant-cta-label">{{ $displayStock < 1 ? 'Out of Stock' : 'Add to cart' }}</span>
                                                </button>
                                            </div>
                                            <a href="#" class="tf-btn btn-fill radius-4 d-none"><span class="text">Buy it now</span></a>
                                        </div>
                                        <div class="tf-product-info-help">
                                            <div class="tf-product-info-extra-link">
                                                <a href="#delivery_return" data-bs-toggle="modal" class="tf-product-extra-icon">
                                                    <div class="icon">
                                                        <i class="icon-shipping"></i>
                                                    </div>
                                                    <p class="text-caption-1">Delivery & Return</p>
                                                </a>
                                                <a href="#share_social" data-bs-toggle="modal" class="tf-product-extra-icon">
                                                    <div class="icon">
                                                        <i class="icon-share"></i>
                                                    </div>
                                                    <p class="text-caption-1">Share</p>
                                                </a>
                                            </div>
                                            @php
                                                $__deliveryEstimate = $settings?->content('product.delivery_estimate') ?: '3–5 business days across India';
                                                $__returnWindow = $settings?->content('product.return_window') ?: 'Return within 14 days of delivery.';
                                            @endphp
                                            <div class="tf-product-info-time">
                                                <div class="icon">
                                                    <i class="icon-timer"></i>
                                                </div>
                                                <p class="text-caption-1">Estimated delivery:&nbsp;&nbsp;<span>{{ $__deliveryEstimate }}</span></p>
                                            </div>
                                            <div class="tf-product-info-return">
                                                <div class="icon">
                                                    <i class="icon-arrowClockwise"></i>
                                                </div>
                                                <p class="text-caption-1">{{ $__returnWindow }}</p>
                                            </div>
                                            <div class="dropdown dropdown-store-location">
                                                <div class="dropdown-title dropdown-backdrop" data-bs-toggle="dropdown" aria-haspopup="true">
                                                    <div class="tf-product-info-view link">
                                                        <div class="icon">
                                                            <i class="icon-map-pin"></i>
                                                        </div>
                                                        <span>View Store Information</span>
                                                    </div>
                                                </div>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <div class="dropdown-content">
                                                        <div class="dropdown-content-heading">
                                                            <h5>Store Location</h5>
                                                            <i class="icon icon-close"></i>
                                                        </div>
                                                        <div class="line-bt"></div>
                                                        <div>
                                                            <h6>{{ config('app.name') }}</h6>
                                                            <p>Usually ships within 24 hours.</p>
                                                        </div>
                                                        @if (! empty($settings?->address))
                                                        <div>
                                                            <p>{{ $settings->address }}</p>
                                                        </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <ul class="tf-product-info-sku">
                                            <li>
                                                <p class="text-caption-1">SKU:</p>
                                                <p class="text-caption-1 text-1" data-role="variant-sku">{{ $displaySku }}</p>
                                            </li>
                                            <li>
                                                <p class="text-caption-1">Available:</p>
                                                <p class="text-caption-1 text-1" data-role="variant-stock">{{ $displayStock > 0 ? 'In stock ('.$displayStock.')' : 'Out of stock' }}</p>
                                            </li>
                                            <li>
                                                <p class="text-caption-1">Categories:</p>
                                                <p class="text-caption-1">
                                                    {{implode(', ', $category)}}
                                                </p>
                                            </li>
                                        </ul>
                                        @php $__productBadges = $paymentBadges ?? []; @endphp
                                        @if (! empty($__productBadges))
                                        <div class="tf-product-info-guranteed">
                                            <div class="text-title">
                                                Guaranteed safe checkout:
                                            </div>
                                            <div class="tf-payment">
                                                @foreach ($__productBadges as $__pb)
                                                    @if (! empty($__pb['image']))
                                                        <img src="{{ asset('client/images/payment/' . $__pb['image']) }}"
                                                            alt="{{ $__pb['label'] ?? '' }}">
                                                    @elseif (! empty($__pb['chip']))
                                                        <span class="pay-chip {{ ! empty($__pb['is_cod']) ? 'pay-chip--cod' : '' }}"
                                                            title="{{ $__pb['label'] ?? '' }}">{{ $__pb['chip'] }}</span>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /tf-product-info-list -->
                </div>
            </div>
        </div>
        {{-- display none --}}
        <div class="tf-sticky-btn-atc d-none">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <form class="form-sticky-atc">
                            <div class="tf-sticky-atc-product">
                                <div class="image">
                                    @php
                                        $defaultImage = asset('client/images/home/product-placeholder.webp');
                                        $mainImage = !empty($product['medias'][0]['url']) ? asset('storage/product/' . $product['medias'][0]['url']) : $defaultImage;
                                    @endphp
                                    <img class="lazyload" data-src="{{$mainImage}}" alt="" src="{{$mainImage}}">
                                </div>
                                <div class="content">
                                    <div class="text-title">
                                        {{$product['name'] ?? ''}}
                                    </div>
                                    <div class="text-caption-1 text-secondary-2">{{implode(', ', $category)}}</div>
                                    <div class="text-title">₹{{$product['price']}}</div>
                                </div>
                            </div>
                            <div class="tf-sticky-atc-infos">
                                <div class="tf-sticky-atc-size d-flex gap-12 align-items-center">
                                    <div class="tf-sticky-atc-infos-title text-title">Size:</div>
                                    <div class="tf-dropdown-sort style-2" data-bs-toggle="dropdown">
                                        <div class="btn-select">
                                            <span class="text-sort-value font-2">M</span>
                                            <span class="icon icon-arrow-down"></span>
                                        </div>
                                        <div class="dropdown-menu">
                                            <div class="select-item">
                                                <span class="text-value-item">S</span>
                                            </div>
                                            <div class="select-item active">
                                                <span class="text-value-item">M</span>
                                            </div>
                                            <div class="select-item">
                                                <span class="text-value-item">L</span>
                                            </div>
                                            <div class="select-item">
                                                <span class="text-value-item">XL</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tf-sticky-atc-quantity d-flex gap-12 align-items-center">
                                    <div class="tf-sticky-atc-infos-title text-title">Quantity:</div>
                                    <div class="wg-quantity style-1">
                                        <span class="btn-quantity minus-btn">-</span>
                                        <input type="text" name="number" value="1">
                                        <span class="btn-quantity plus-btn">+</span>
                                    </div>
                                </div>
                                <div class="tf-sticky-atc-btns">
                                    <button class="tf-btn w-100 btn-reset radius-4 btn-add-to-cart add-to-cart" type="button" data-product-id="{{$product['id']}}" data-url="{{route('client.addToCart')}}">
                                        <span class="text text-btn-uppercase">Add To Cart</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- /Product_Main -->

    @php
        use Carbon\Carbon;
    @endphp
    <!-- Product_Description_List -->
    <section class="mt-5">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="product-description-list">
                        <div class="product-description-list-item">
                            <h6 class="product-description-list-title">Description</h6>
                            <div class="product-description-list-content">
                                {!! \App\Helper\CommonHelper::sanitizeHtml($product['description'] ?? '') !!}
                            </div>
                        </div>
                        <div class="product-description-list-item">
                            @if(!empty($data['reviews']))
                                <h6 class="product-description-list-title">Customer Reviews</h6>
                                <div class="product-description-list-content">
                                    <div class="tab-reviews write-cancel-review-wrap ">
                                        <div class="tab-reviews-heading">
                                            <div class="top">
                                                <div class="text-center">
                                                    {{-- Average rating --}}
                                                    <div class="number title-display">{{ $data['averageRating'] }}</div>

                                                    {{-- Star icons (you can change to dynamic filled stars if you want) --}}
                                                    <div class="list-star">
                                                        <i class="icon icon-star"></i>
                                                        <i class="icon icon-star"></i>
                                                        <i class="icon icon-star"></i>
                                                        <i class="icon icon-star"></i>
                                                        <i class="icon icon-star"></i>
                                                    </div>

                                                    {{-- Total reviews count --}}
                                                    <p>{{ $data['totalReviews'] }} Reviews</p>
                                                </div>

                                                {{-- Rating breakdown 5 → 1 --}}
                                                <div class="rating-score">
                                                    @foreach([5,4,3,2,1] as $star)
                                                        <div class="item">
                                                            <div class="number-1 text-caption-1">{{ $star }}</div>
                                                            <i class="icon icon-star"></i>

                                                            <div class="line-bg">
                                                                {{-- progress width from percentage (guard against undefined) --}}
                                                                @php
                                                                    $percent = isset($data['ratingPercent'][$star]) ? $data['ratingPercent'][$star] : 0;
                                                                @endphp
                                                                <div style="width: {{ number_format($percent, 2) }}%;"></div>
                                                            </div>

                                                            <div class="number-2 text-caption-1">
                                                                {{ $data['ratingCounts'][$star] ?? 0 }}
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>

                                            <div>
                                                <div class="tf-btn btn-white has-border radius-4 letter-1 btn-comment-review btn-cancel-review" style="cursor:pointer"><span class="text">Cancel Review</span></div>
                                                <div class="tf-btn btn-fill radius-4 letter-1 btn-comment-review btn-write-review" style="cursor:pointer"><span class="text">Write a review</span></div>
                                            </div>
                                        </div>

                                        {{-- Comments / sorting --}}
                                        <div class="reply-comment style-1 cancel-review-wrap">
                                            <div class="d-flex mb_24 gap-20 align-items-center justify-content-between flex-wrap">
                                                <h4 class="">Comments</h4>
                                                <div class="d-flex align-items-center gap-12 d-none">
                                                    <div class="text-caption-1">Sort by:</div>
                                                    <div class="tf-dropdown-sort" data-bs-toggle="dropdown">
                                                        <div class="btn-select">
                                                            <span class="text-sort-value">Most Recent</span>
                                                            <span class="icon icon-arrow-down"></span>
                                                        </div>
                                                        <div class="dropdown-menu">
                                                            <div class="select-item active">
                                                                <span class="text-value-item">Most Recent</span>
                                                            </div>
                                                            <div class="select-item">
                                                                <span class="text-value-item">Oldest</span>
                                                            </div>
                                                            <div class="select-item">
                                                                <span class="text-value-item">Most Popular</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="reply-comment-wrap">
                                                @foreach ($data['reviews'] as $review)
                                                    <div class="reply-comment-item">
                                                        <div class="user">
                                                            <div class="image">
                                                                @if(!empty($review['image']))
                                                                    <img src="{{ asset('storage/review/'.$review['image']) }}" alt="">
                                                                @else
                                                                    <img src="images/avatar/user-default.jpg" alt="">
                                                                @endif
                                                            </div>
                                                            <div>
                                                                <h6>
                                                                    {{ $review['title'] }}
                                                                </h6>
                                                                <div class="day text-secondary-2 text-caption-1">{{ Carbon::parse($review['created_at'])->diffForHumans() }}</div>
                                                            </div>
                                                        </div>

                                                        {{-- show review text and optional star rating if you store it --}}
                                                        <p class="text-secondary">{{ $review['review'] }}</p>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        {{-- Review form --}}
                                        <div class="review-card">
                                            <div class="review-card__head">
                                                <h4 class="review-card__title">Write a review</h4>
                                                <p class="review-card__hint">Share what you loved (or didn't) — helps other makers pick the right mould.</p>
                                            </div>

                                            @auth('customer')
                                            <form id="reviewForm" class="review-form" method="post" enctype="multipart/form-data">
                                                @csrf
                                                <input type="hidden" name="product_id" value="{{ $product['id'] }}">

                                                <div class="review-field">
                                                    <label class="review-label">Overall rating <span class="req">*</span></label>
                                                    <div class="star-rating list-rating-check">
                                                        <input type="radio" id="star5" name="rate" value="5">
                                                        <label for="star5" title="5 stars"></label>
                                                        <input type="radio" id="star4" name="rate" value="4">
                                                        <label for="star4" title="4 stars"></label>
                                                        <input type="radio" id="star3" name="rate" value="3">
                                                        <label for="star3" title="3 stars"></label>
                                                        <input type="radio" id="star2" name="rate" value="2">
                                                        <label for="star2" title="2 stars"></label>
                                                        <input type="radio" id="star1" name="rate" value="1">
                                                        <label for="star1" title="1 star"></label>
                                                    </div>
                                                    <div class="review-error error" id="error-rate"></div>
                                                </div>

                                                <div class="review-field">
                                                    <label class="review-label" for="review-title">Review title <span class="req">*</span></label>
                                                    <input id="review-title" class="review-input" type="text" placeholder="Sum it up in a few words" name="title" maxlength="255">
                                                    <div class="review-error error" id="error-title"></div>
                                                </div>

                                                <div class="review-field">
                                                    <label class="review-label" for="review-body">Your review <span class="req">*</span></label>
                                                    <textarea id="review-body" class="review-input" rows="4" name="review" maxlength="2000"
                                                        placeholder="How did it cast? Was the detail crisp, the release clean, the finish what you expected?"></textarea>
                                                    <div class="review-hint">
                                                        <span id="review-counter">0</span> / 2000 characters
                                                    </div>
                                                    <div class="review-error error" id="error-review"></div>
                                                </div>

                                                <div class="review-grid-2">
                                                    <div class="review-field">
                                                        <label class="review-label" for="review-name">Display name <span class="req">*</span></label>
                                                        <input id="review-name" class="review-input" type="text" placeholder="Public name shown on the review" name="name" maxlength="100"
                                                            value="{{ auth('customer')->user()?->name }}">
                                                        <div class="review-error error" id="error-name"></div>
                                                    </div>
                                                    <div class="review-field">
                                                        <label class="review-label" for="review-email">Email <span class="req">*</span></label>
                                                        <input id="review-email" class="review-input" type="email" placeholder="Kept private" name="email" maxlength="100"
                                                            value="{{ auth('customer')->user()?->email }}">
                                                        <div class="review-error error" id="error-email"></div>
                                                    </div>
                                                </div>

                                                <div class="review-field">
                                                    <label class="review-label">Add a photo <span class="review-optional">(optional)</span></label>
                                                    <label for="reviewImage" class="review-dropzone" id="reviewDropzone">
                                                        <div class="review-dropzone__idle" id="reviewDropzoneIdle">
                                                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                                                <polyline points="21 15 16 10 5 21"/>
                                                            </svg>
                                                            <div class="review-dropzone__title">Click to upload or drag &amp; drop</div>
                                                            <div class="review-dropzone__sub">JPG, PNG or WebP · max 2 MB</div>
                                                        </div>
                                                        <div class="review-dropzone__preview d-none" id="reviewDropzonePreview">
                                                            <img id="previewImage" alt="Selected photo">
                                                            <button type="button" class="review-dropzone__remove" id="reviewImageRemove" aria-label="Remove photo">×</button>
                                                        </div>
                                                    </label>
                                                    <input type="file" id="reviewImage" class="d-none" name="image" accept="image/jpeg,image/png,image/webp">
                                                    <div class="review-error error" id="error-image"></div>
                                                </div>

                                                <div class="review-field">
                                                    <label class="review-checkbox">
                                                        <input type="checkbox" name="save_info" id="check1" checked>
                                                        <span>Save my name and email in this browser for the next review.</span>
                                                    </label>
                                                </div>

                                                <div class="review-footer">
                                                    <button class="review-submit submit-review-btn" type="submit">
                                                        <span class="review-submit__label">Submit Review</span>
                                                    </button>
                                                    <span class="review-footer__hint">Reviews are approved by our team before appearing publicly.</span>
                                                </div>

                                                <div id="reviewMessage" class="review-message"></div>
                                            </form>
                                            @else
                                            <div class="review-signin">
                                                <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                                    <circle cx="12" cy="7" r="4"/>
                                                </svg>
                                                <h5>Sign in to write a review</h5>
                                                <p>Only verified buyers can leave a review. Sign in with the account you used to place the order.</p>
                                                <a class="tf-btn btn-fill radius-4" href="{{ route('client.login') }}"><span class="text">Sign in</span></a>
                                            </div>
                                            @endauth
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        @php
                            $__shippingCopy = $settings?->content('product.shipping_copy') ?: "One flat delivery fee across India. Free returns within 14 days on unused items in original packaging.";
                            $__returnCopy = $settings?->content('product.return_copy') ?: "If it's not a hit with the little one, send it back within 14 days for a full refund to your original payment method. Contact support to start a return.";
                            $__estimate = $settings?->content('product.delivery_estimate') ?: '3–5 business days across India';
                        @endphp
                        <div class="product-description-list-item">
                            <h6 class="product-description-list-title">Shipping & Returns</h6>
                            <div class="product-description-list-content">
                                <div class="tab-shipping">
                                    <div class="w-100">
                                        <div class="text-btn-uppercase mb_12">We've got your back</div>
                                        <p class="mb_12">{!! nl2br(e($__shippingCopy)) !!}</p>
                                    </div>
                                    <div class="w-100">
                                        <div class="text-btn-uppercase mb_12">Estimated delivery</div>
                                        <p class="font-2">{{ $__estimate }}</p>
                                    </div>
                                    <div class="w-100">
                                        <div class="text-btn-uppercase mb_12">Need more information?</div>
                                        <div>
                                            <a href="{{ route('client.contact') }}" class="link text-secondary text-decoration-underline mb_6 font-2">Contact support</a>
                                        </div>
                                        @if (\App\Helper\OrderStatusHelper::publicTrackingVisible($settings ?? null))
                                            <div>
                                                <a href="{{ route('client.track.form') }}" class="link text-secondary text-decoration-underline font-2">Track my order</a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="product-description-list-item">
                            <h6 class="product-description-list-title">Return Policies</h6>
                            <div class="product-description-list-content">
                                <div class="tab-policies">
                                    <p class="text-secondary">{!! nl2br(e($__returnCopy)) !!}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- /Product_Description_List -->

    <!-- Ralated Products -->
    <section class="flat-spacing">
        <div class="container flat-animate-tab">
            <ul class="tab-product justify-content-sm-center wow fadeInUp" data-wow-delay="0s" role="tablist">
                <li class="nav-tab-item" role="presentation">
                    <a href="#ralatedProducts" class="active" data-bs-toggle="tab">Ralated Products</a>
                </li>
                <li class="nav-tab-item" role="presentation">
                    <a href="#recentlyViewed" data-bs-toggle="tab">Recently Viewed</a>
                </li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane active show" id="ralatedProducts" role="tabpanel">
                    <div dir="ltr" class="swiper tf-sw-latest" data-preview="4" data-tablet="3" data-mobile="2" data-space-lg="30" data-space-md="30" data-space="15" data-pagination="1" data-pagination-md="1" data-pagination-lg="1">
                        <div class="swiper-wrapper">
                            @foreach ($data['related_products'] as $product)
                                <div class="swiper-slide">
                                    <div class="card-product">
                                        @include('components.product-card')
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="sw-pagination-latest sw-dots type-circle justify-content-center"></div>
                    </div>
                </div>
                <div class="tab-pane" id="recentlyViewed" role="tabpanel">
                    <div dir="ltr" class="swiper tf-sw-recent" data-preview="4" data-tablet="3" data-mobile="2" data-space-lg="30" data-space-md="30" data-space="15" data-pagination="1" data-pagination-md="1" data-pagination-lg="1">
                        <div class="swiper-wrapper">
                            @foreach ($data['related_products'] as $product)
                                <div class="swiper-slide">
                                    <div class="card-product">
                                        @include('components.product-card')
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="sw-pagination-recent sw-dots type-circle justify-content-center"></div>
                    </div>
                </div>
            </div>

        </div>
    </section>
    <!-- /Ralated Products -->

    <!-- modal ask_question -->
    <div class="modal modalCentered fade tf-product-modal modal-part-content" id="ask_question">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="header">
                    <div class="demo-title">Ask a question</div>
                    <span class="icon-close icon-close-popup" data-bs-dismiss="modal"></span>
                </div>
                <div class="overflow-y-auto">
                    <form class="">
                        <fieldset class="">
                            <label >Name *</label>
                            <input type="text" placeholder="" class="" name="text" tabindex="2" value=""
                                aria-required="true" required="">
                        </fieldset>
                        <fieldset class="">
                            <label >Email *</label>
                            <input type="email" placeholder="" class="" name="text" tabindex="2" value=""
                                aria-required="true" required="">
                        </fieldset>
                        <fieldset class="">
                            <label >Phone number</label>
                            <input type="number" placeholder="" class="" name="text" tabindex="2" value=""
                                aria-required="true" required="">
                        </fieldset>
                        <fieldset class="">
                            <label >Message</label>
                            <textarea name="message" rows="4" placeholder="" class="" tabindex="2" aria-required="true"
                                required=""></textarea>
                        </fieldset>
                        <button type="submit" class="tf-btn btn-fill radius-4 w-100"><span class="text">Send</span></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- /modal ask_question -->
    
    <!-- modal delivery_return -->
    <div class="modal modalCentered fade tf-product-modal modal-part-content" id="delivery_return">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="header">
                    <div class="demo-title">Shipping & Delivery</div>
                    <span class="icon-close icon-close-popup" data-bs-dismiss="modal"></span>
                </div>
                <div class="overflow-y-auto">
                    <div class="tf-product-popup-delivery">
                        <div class="title">Delivery</div>
                        <p class="text-paragraph">{{ $__estimate ?? '3–5 business days across India' }}</p>
                        <p class="text-paragraph">{!! nl2br(e($__shippingCopy ?? '')) !!}</p>
                    </div>
                    <div class="tf-product-popup-delivery">
                        <div class="title">Returns</div>
                        <p class="text-paragraph">{!! nl2br(e($__returnCopy ?? '')) !!}</p>
                    </div>
                    <div class="tf-product-popup-delivery">
                        <div class="title">Help</div>
                        <p class="text-paragraph">Give us a shout if you have any other questions.</p>
                        @if (!empty($settings?->email))
                            <p class="text-paragraph">
                                Email: <a href="mailto:{{ $settings->email }}">{{ $settings->email }}</a>
                            </p>
                        @endif
                        @if (!empty($settings?->phone))
                            <p class="text-paragraph mb-0">Phone: {{ $settings->phone }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /modal delivery_return -->
    
    <!-- modal share social -->
    <div class="modal modalCentered fade tf-product-modal modal-part-content" id="share_social">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="header">
                    <div class="demo-title">Share</div>
                    <span class="icon-close icon-close-popup" data-bs-dismiss="modal"></span>
                </div>
                <div class="overflow-y-auto">
                    @php
                        // All five icons pointed at href="#" and the Copy button had no
                        // handler at all, so every control in this modal did nothing.
                        // Instagram and TikTok have no web share intent to point at, so
                        // they make way for WhatsApp — the channel this shop's customers
                        // actually use, and the one the floating chat button already backs.
                        $__shareUrl   = urlencode(url()->current());
                        $__shareTitle = urlencode(($product['name'] ?? 'Take a look at this') . ' · ' . config('app.name'));
                        $__shareImage = ! empty($product['medias'][0]['url'])
                            ? urlencode(asset('storage/product/' . $product['medias'][0]['url']))
                            : '';
                    @endphp
                    <ul class="tf-social-icon d-flex gap-10">
                        <li>
                            <a href="https://www.facebook.com/sharer/sharer.php?u={{ $__shareUrl }}"
                               target="_blank" rel="noopener" class="box-icon social-facebook bg_line" aria-label="Share on Facebook">
                                <i class="icon icon-fb"></i>
                            </a>
                        </li>
                        <li>
                            <a href="https://twitter.com/intent/tweet?url={{ $__shareUrl }}&text={{ $__shareTitle }}"
                               target="_blank" rel="noopener" class="box-icon social-twiter bg_line" aria-label="Share on X">
                                <i class="icon icon-x"></i>
                            </a>
                        </li>
                        <li>
                            <a href="https://api.whatsapp.com/send?text={{ $__shareTitle }}%20{{ $__shareUrl }}"
                               target="_blank" rel="noopener" class="box-icon bg_line" aria-label="Share on WhatsApp">
                                <i class="icon icon-whatsapp"></i>
                            </a>
                        </li>
                        <li>
                            <a href="https://pinterest.com/pin/create/button/?url={{ $__shareUrl }}&description={{ $__shareTitle }}@if ($__shareImage)&media={{ $__shareImage }}@endif"
                               target="_blank" rel="noopener" class="box-icon social-pinterest bg_line" aria-label="Share on Pinterest">
                                <i class="icon icon-pinterest"></i>
                            </a>
                        </li>
                    </ul>
                    <form class="form-share" id="product-share-form" method="post" accept-charset="utf-8" onsubmit="return false">
                        <fieldset>
                            <label for="product-share-url" class="visually-hidden">Product link</label>
                            <input type="text" id="product-share-url" value="{{ url()->current() }}" readonly aria-label="Product link">
                        </fieldset>
                        <div class="button-submit">
                            <button class="tf-btn radius-4 btn-fill" type="button" id="product-share-copy"><span class="text">Copy</span></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- /modal share social -->

    {{-- Sticky mobile buy bar — only visible on mobile, slides in when the primary add-to-cart scrolls out of view. --}}
    <div class="sticky-buy-bar" id="stickyBuyBar" aria-hidden="true">
        <div class="sticky-buy-bar__inner">
            <div class="sticky-buy-bar__preview">
                @if (! empty($product['medias'][0]['url']))
                    <img src="{{ asset('storage/product/' . $product['medias'][0]['url']) }}"
                        alt="{{ $product['name'] ?? 'Product' }}"
                        width="44" height="44" loading="lazy" decoding="async">
                @endif
                <div class="sticky-buy-bar__meta">
                    <div class="sticky-buy-bar__name">{{ $product['name'] ?? '' }}</div>
                    @php
                        // Follows the selected variant. The picker boots against the whole
                        // document and updates EVERY element carrying a given data-role, so
                        // this bar uses the same role names as the buy box rather than a
                        // parallel set the script would have to know about separately.
                        $__barPrice   = (float) ($displayPrice ?? $product['price'] ?? 0);
                        $__barCompare = (float) ($displayCompare ?? $product['compere_price'] ?? 0);
                        $__barStock   = (int) ($displayStock ?? $product['stock'] ?? 0);
                    @endphp
                    <div class="sticky-buy-bar__price">
                        <strong data-role="variant-price">₹{{ number_format($__barPrice, 2) }}</strong>
                        <s data-role="variant-compare" style="{{ $__barCompare > $__barPrice ? '' : 'display:none;' }}">₹{{ number_format($__barCompare, 2) }}</s>
                    </div>
                </div>
            </div>
            <button class="tf-btn btn-fill add-to-cart" type="button"
                data-product-id="{{ $product['id'] }}"
                data-variant-id="{{ $defaultVariant['id'] ?? '' }}"
                data-url="{{ route('client.addToCart') }}"
                {{ $__barStock < 1 ? 'disabled' : '' }}>
                <span class="text" data-role="variant-cta-label">{{ $__barStock < 1 ? 'Out of stock' : 'Add to cart' }}</span>
            </button>
        </div>
    </div>

    <style>
        .sticky-buy-bar {
            position: fixed; left: 0; right: 0; bottom: 0;
            background: #fff; border-top: 1px solid #eee;
            box-shadow: 0 -4px 14px rgba(0,0,0,.06);
            padding: 10px 14px calc(10px + env(safe-area-inset-bottom));
            transform: translateY(120%); transition: transform .25s ease;
            z-index: 999; display: none;
        }
        .sticky-buy-bar.is-visible { transform: translateY(0); }
        .sticky-buy-bar__inner { display: flex; align-items: center; gap: 12px; }
        .sticky-buy-bar__preview { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
        .sticky-buy-bar__preview img {
            width: 44px; height: 44px; object-fit: cover; border-radius: 8px; flex-shrink: 0;
        }
        .sticky-buy-bar__meta { min-width: 0; flex: 1; }
        .sticky-buy-bar__name {
            font-size: 12px; font-weight: 600; color: var(--ds-charcoal);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .sticky-buy-bar__price { font-size: 13px; color: var(--ds-charcoal); }
        .sticky-buy-bar__price strong { font-weight: 700; }
        .sticky-buy-bar__price s { color: #999; margin-left: 6px; font-size: 11px; }
        /* Override the theme's giant 99px-pill + 15/32 padding so the CTA fits
           this compact bar without dominating the row. Keep it obviously a
           button, but proportional to the 44px product thumbnail next to it. */
        .sticky-buy-bar .tf-btn,
        .sticky-buy-bar .tf-btn.btn-fill {
            padding: 10px 18px !important;
            border-radius: 10px !important;
            font-size: 13px !important;
            line-height: 1.2 !important;
            font-weight: 600 !important;
            text-transform: none !important;
            white-space: nowrap;
            flex-shrink: 0;
            min-height: 40px;
        }
        .sticky-buy-bar .tf-btn .text { font-size: inherit; letter-spacing: 0; }
        /* Kill the theme's skew-in hover overlay for this bar — visual noise on a small button */
        .sticky-buy-bar .tf-btn::after { display: none !important; }
        @media (max-width: 480px) {
            .sticky-buy-bar { padding: 8px 12px calc(8px + env(safe-area-inset-bottom)); }
            .sticky-buy-bar__inner { gap: 8px; }
            .sticky-buy-bar .tf-btn,
            .sticky-buy-bar .tf-btn.btn-fill {
                padding: 9px 14px !important;
                font-size: 12px !important;
                min-height: 38px;
            }
            .sticky-buy-bar__preview img { width: 40px; height: 40px; }
        }
        @media (max-width: 768px) { .sticky-buy-bar { display: block; } }
    </style>
    <script>
        (function () {
            const bar = document.getElementById('stickyBuyBar');
            if (! bar) return;
            const desktopBtn = document.querySelector('.btn-add-to-cart:not(.add-to-cart)') || document.querySelector('.btn-add-to-cart');
            if (! desktopBtn) return;
            let observer;
            const io = () => new IntersectionObserver(entries => {
                entries.forEach(e => {
                    if (e.isIntersecting) {
                        bar.classList.remove('is-visible');
                        bar.setAttribute('aria-hidden', 'true');
                    } else {
                        bar.classList.add('is-visible');
                        bar.setAttribute('aria-hidden', 'false');
                    }
                });
            }, { rootMargin: '0px 0px -60px 0px' });
            observer = io();
            observer.observe(desktopBtn);
        })();
    </script>
@endsection

@section('scripts')
    <script>
        // The share modal's Copy button had no handler — it looked live and did
        // nothing. navigator.clipboard needs a secure context and this shop is
        // served over plain http outside production, so keep the select+copy
        // fallback rather than failing silently on exactly the environments
        // where someone is most likely to test it.
        (function () {
            var btn = document.getElementById('product-share-copy');
            var field = document.getElementById('product-share-url');
            if (!btn || !field) return;

            btn.addEventListener('click', function () {
                var done = function () {
                    if (typeof showSweetAlert === 'function') {
                        showSweetAlert('success', 'Link copied.');
                    }
                };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(field.value).then(done).catch(function () {});
                    return;
                }
                field.removeAttribute('readonly');
                field.select();
                field.setSelectionRange(0, 99999);
                try { document.execCommand('copy'); done(); } catch (e) {}
                field.setAttribute('readonly', 'readonly');
                window.getSelection().removeAllRanges();
            });
        })();
    </script>
    <script type="text/javascript" src="{{asset('client/js/min/drift.min.js')}}"></script>
    <script type="module" src="{{asset('client/js/min/model-viewer.min.js')}}"></script>
    <script type="module" src="{{asset('client/js/min/zoom.min.js')}}"></script>
    <script>
        (function () {
            const $img = $('#reviewImage');
            const $dropzone = $('#reviewDropzone');
            const $idle = $('#reviewDropzoneIdle');
            const $preview = $('#reviewDropzonePreview');
            const $previewImg = $('#previewImage');
            const $remove = $('#reviewImageRemove');
            const MAX_BYTES = 2 * 1024 * 1024; // 2 MB — matches server rule

            function setImage(file) {
                if (! file) { clearImage(); return; }
                if (! file.type || ! file.type.startsWith('image/')) {
                    $('#error-image').text('Please choose a JPG, PNG or WebP image.');
                    return;
                }
                if (file.size > MAX_BYTES) {
                    $('#error-image').text('Image is too large (max 2 MB). Try a smaller file or a compressed version.');
                    return;
                }
                $('#error-image').text('');
                const url = URL.createObjectURL(file);
                $previewImg.attr('src', url);
                $idle.addClass('d-none');
                $preview.removeClass('d-none');
                // Sync the DataTransfer so the input.files reflects our choice (for drag-and-drop)
                if (window.DataTransfer) {
                    try {
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        $img[0].files = dt.files;
                    } catch (e) { /* ignore in browsers that don't support it */ }
                }
            }
            function clearImage() {
                $img.val('');
                $previewImg.attr('src', '');
                $preview.addClass('d-none');
                $idle.removeClass('d-none');
                $('#error-image').text('');
            }

            $img.on('change', function () { setImage(this.files && this.files[0]); });
            $remove.on('click', function (e) { e.preventDefault(); e.stopPropagation(); clearImage(); });

            // Drag & drop
            $dropzone.on('dragover dragenter', function (e) {
                e.preventDefault(); e.stopPropagation();
                $(this).addClass('drag-over');
            });
            $dropzone.on('dragleave dragend', function (e) {
                e.preventDefault(); e.stopPropagation();
                $(this).removeClass('drag-over');
            });
            $dropzone.on('drop', function (e) {
                e.preventDefault(); e.stopPropagation();
                $(this).removeClass('drag-over');
                const files = e.originalEvent?.dataTransfer?.files;
                if (files && files[0]) setImage(files[0]);
            });

            // Character counter
            const $counter = $('#review-counter');
            $('#review-body').on('input', function () { $counter.text(this.value.length); });

            // Restore saved name/email if the customer ticked "save" previously
            try {
                const saved = JSON.parse(localStorage.getItem('reviewer') || 'null');
                if (saved) {
                    if (! $('#review-name').val()) $('#review-name').val(saved.name || '');
                    if (! $('#review-email').val()) $('#review-email').val(saved.email || '');
                }
            } catch (e) { /* ignore */ }

            function showBanner(kind, message) {
                const $msg = $('#reviewMessage');
                $msg.html(`<div class="banner banner-${kind}">${$('<span>').text(message).html()}</div>`);
                if (kind === 'success' || kind === 'info') {
                    $('html, body').animate({ scrollTop: $msg.offset().top - 120 }, 250);
                }
            }

            $(document).on('submit', '#reviewForm', function (e) {
                e.preventDefault();

                const $form = $(this);
                const $btn = $form.find('.submit-review-btn');
                const $label = $btn.find('.review-submit__label');
                const formData = new FormData($form[0]);

                // Clear old inline errors + banner
                $form.find('.error').text('');
                $('#reviewMessage').html('');

                $btn.prop('disabled', true);
                $label.text('Submitting…');

                $.ajax({
                    url: `{{ route('client.reviews.store') }}`,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (res) {
                        $btn.prop('disabled', false);
                        $label.text('Submit Review');
                        if (res.success === true) {
                            // Save name/email locally if the customer opted in.
                            if ($('#check1').is(':checked')) {
                                try {
                                    localStorage.setItem('reviewer', JSON.stringify({
                                        name: $('#review-name').val(),
                                        email: $('#review-email').val(),
                                    }));
                                } catch (e) { /* ignore */ }
                            }
                            showBanner('success', res.message || 'Thank you! Your review is awaiting approval.');
                            $form[0].reset();
                            clearImage();
                            $('#review-counter').text('0');
                        } else {
                            showBanner('error', res.message || 'Could not submit your review.');
                        }
                    },
                    error: function (xhr) {
                        $btn.prop('disabled', false);
                        $label.text('Submit Review');

                        // 422 = validation errors — surface each field message inline
                        if (xhr.status === 422) {
                            const body = xhr.responseJSON || {};
                            if (body.errors && typeof body.errors === 'object') {
                                Object.keys(body.errors).forEach(function (key) {
                                    const arr = body.errors[key];
                                    const msg = Array.isArray(arr) ? arr[0] : arr;
                                    const $el = $(`#error-${key}`);
                                    if ($el.length) $el.text(msg);
                                });
                                showBanner('error', 'Please fix the highlighted fields and try again.');
                                return;
                            }
                            // Some 422s (e.g. "already reviewed") come without .errors
                            showBanner('info', body.message || 'This review can\'t be submitted right now.');
                            return;
                        }

                        // 403 = not authenticated OR not eligible ("You can review only after purchase")
                        if (xhr.status === 403) {
                            const body = xhr.responseJSON || {};
                            const msg = body.message
                                || 'You can only review products you\'ve bought. Sign in with your buying account to leave a review.';
                            showBanner('info', msg);
                            return;
                        }

                        // 401 = session expired
                        if (xhr.status === 401 || xhr.status === 419) {
                            showBanner('error', 'Your session expired. Please refresh the page and sign in again.');
                            return;
                        }

                        // 413 or 500+
                        if (xhr.status === 413) {
                            showBanner('error', 'The image you uploaded is too large. Please attach a smaller file.');
                            return;
                        }

                        console.error('Review submit failed:', xhr);
                        showBanner('error', 'Something went wrong on our side. Please try again in a moment.');
                    },
                });
            });
        })();
    </script>

    {{-- Picker behaviour lives in public/client/js/variant-picker.js, shared with the
         quick-add modal. It boots itself on DOMContentLoaded. --}}
@endsection


