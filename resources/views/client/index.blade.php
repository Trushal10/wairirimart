@extends('layouts.client')

@section('title') {{ config('app.name') }} @endsection

@section('meta_description')
    Shop premium silicone moulds, DIY painting kits, resin and candle moulds, concrete casting materials, T-light
    holders, flower pots and craft supplies at {{ config('app.name') }}. Made for hobbyists, artists, small businesses
    and home décor makers. Fast delivery across India, easy returns, and durable, non-toxic materials.
@endsection

@php $__heroSlides = collect($data['sliders'][0]['sliderMedias'] ?? []); @endphp

{{-- The first hero slide is the LCP element on this page. Preloading it from
     the document head (see @stack('preload') in the layout) is what stops the
     request from waiting on the render-blocking CSS ahead of it. --}}
@php $__lcpSlide = $__heroSlides->first(); @endphp
@if ($__lcpSlide && ! empty($__lcpSlide->url))
    @push('preload')
        @php $__lcpSrcset = \App\Helper\CommonHelper::srcsetFor('slider/' . $__lcpSlide->url); @endphp
        <link rel="preload" as="image"
              href="{{ asset('storage/slider/' . $__lcpSlide->url) }}"
              @if ($__lcpSrcset) imagesrcset="{{ $__lcpSrcset }}" imagesizes="100vw" @endif
              fetchpriority="high">
    @endpush
@endif

@section('content')
    <!-- Slider -->
    {{-- A single banner renders statically: initializing Swiper (loop + fade)
         on one slide leaves it with opacity 0 / translated off-canvas. --}}
    <section class="tf-slideshow slider-default">
        @if ($__heroSlides->count() > 1)
            <div dir="ltr" class="swiper tf-sw-slideshow" data-speed="0" data-preview="1" data-tablet="1"
                data-mobile="1" data-centered="false" data-space="0" data-space-mb="0" data-loop="true" data-auto-play="true">
                <div class="swiper-wrapper">
                    @foreach ($__heroSlides as $__slideIndex => $slider)
                        <div class="swiper-slide">
                            @include('client.partials.hero-banner', ['slider' => $slider, 'eager' => $__slideIndex === 0])
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="wrap-pagination">
                <div class="container">
                    <div class="sw-dots sw-pagination-slider type-circle white-circle justify-content-center"></div>
                </div>
            </div>
        @elseif ($__heroSlides->count() === 1)
            @include('client.partials.hero-banner', ['slider' => $__heroSlides->first(), 'eager' => true])
        @endif
    </section>
    <!-- /Slider -->

    <!-- Categories -->
    <section class="flat-spacing">
        <div class="container">
            <div class="heading-section-2 wow fadeInUp">
                <h3>Shop by Category</h3>
                <a href="{{ route('client.shop') }}" class="btn-line">Browse All Products</a>
            </div>
            <div class="flat-collection-circle wow fadeInUp">
                <div dir="ltr" class="swiper tf-sw-categories" data-preview="6" data-tablet="4" data-mobile-sm="3"
                    data-mobile="2" data-space-lg="30" data-space-md="20" data-space="15" data-pagination="2"
                    data-pagination-md="4" data-pagination-lg="1">
                    <div class="swiper-wrapper">
                        @if (!empty($data['categories']))
                            @foreach ($data['categories'] as $category)
                                <!-- category -->
                                <div class="swiper-slide">
                                    <div class="collection-circle hover-img">
                                        <a href="{{route('client.category', ['category' => $category['slug']])}}"
                                            class="img-style radius-50">
                                            {{-- These circles are the mobile LCP element. They used to render a
                                                 placeholder in src with the real file behind data-src, so the real
                                                 image could not even be discovered until lazysizes had parsed and
                                                 run. Loading the first row eagerly from src lets the preload
                                                 scanner fetch it while the HTML is still streaming. --}}
                                            @php $__catSrcset = \App\Helper\CommonHelper::srcsetFor('category/' . $category->image); @endphp
                                            <img src="{{ asset('storage/category/' . $category->image) }}"
                                                @if ($__catSrcset) srcset="{{ $__catSrcset }}" sizes="(max-width: 575px) 45vw, (max-width: 991px) 25vw, 180px" @endif
                                                {!! \App\Helper\CommonHelper::imageSizeAttrs('category/' . $category->image) !!}
                                                loading="{{ $loop->index < 4 ? 'eager' : 'lazy' }}"
                                                decoding="async"
                                                onerror="this.src='{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}'"
                                                alt="{{$category['name']}}">
                                        </a>
                                        <div class="collection-content text-center">
                                            <div>
                                                <a href="{{route('client.category', ['category' => $category['slug']])}}"
                                                    class="cls-title">
                                                    <h4 class="text">{{$category->name}}</h4>
                                                    <i class="icon icon-arrowUpRight"></i>
                                                </a>
                                            </div>
                                            <div class="count text-secondary">{{$category->product_category_count}} Items</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                    <div class="d-flex d-lg-none sw-pagination-categories sw-dots type-circle justify-content-center">
                    </div>
                </div>
                <div class="nav-prev-categories d-none d-lg-flex nav-sw style-line nav-sw-left">
                    <i class="icon icon-arrLeft"></i>
                </div>
                <div class="nav-next-categories d-none d-lg-flex nav-sw style-line nav-sw-right">
                    <i class="icon icon-arrRight"></i>
                </div>
            </div>
        </div>
    </section>
    <!-- /Categories -->

    <!-- today -->
    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="heading-section text-center wow fadeInUp">
                <h3 class="heading">Today's Top Picks</h3>
                <p class="subheading text-secondary">Hand-picked moulds and kits — new designs added every week.</p>
            </div>
            <div dir="ltr" class="swiper tf-sw-latest" data-preview="4" data-tablet="3" data-mobile="2" data-space-lg="30"
                data-space-md="30" data-space="15" data-pagination="1" data-pagination-md="1" data-pagination-lg="1">
                <div class="swiper-wrapper">
                    @if (!empty($data['highlighted_products']))
                        @foreach ($data['highlighted_products'] as $product)
                            <div class="swiper-slide">
                                <div class="card-product wow fadeInUp" data-wow-delay="0.2s">
                                    @include('components.product-card')
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
                <div class="sw-pagination-latest sw-dots type-circle justify-content-center"></div>
            </div>
        </div>
    </section>
    <!-- /today -->

    <!-- slider collection -->
    <section>
        @if(!empty($data['sliders'][1]['sliderMedias']))
            <div class="container">
                {{-- These banners are designed posters with their own text, so they are
                     shown whole (never cropped) and kept to a third of the row on desktop:
                     two-up they rendered ~700px wide and taller than the screen. --}}
                @php $__colCount = collect($data['sliders'][1]['sliderMedias'])->filter(fn ($m) => ! empty($m->url))->count(); @endphp
                <div dir="ltr" class="swiper tf-sw-collection {{ $__colCount < 3 ? 'is-few' : '' }}" data-preview="3" data-tablet="2" data-mobile="1.2" data-mobile-sm="2" data-space-lg="24" data-space-md="20" data-space="12" data-pagination="1" data-pagination-md="1" data-pagination-lg="1">
                    <div class="swiper-wrapper">
                        @foreach ($data['sliders'][1]['sliderMedias'] as $slider)
                            {{-- The slide is nothing but the banner image, so skip it entirely when the
                                 record has no file rather than emitting src="" and a broken-image icon. --}}
                            @continue(empty($slider->url))
                            @php
                                $__cCaption   = trim((string) ($slider->caption    ?? ''));
                                $__cActionUrl = trim((string) ($slider->action_url ?? ''));
                                $__cHasOverlay = $__cCaption !== '' || $__cActionUrl !== '';
                            @endphp
                            <div class="swiper-slide">
                                <div class="collection-position collection-position--poster radius style-lg hover-img">
                                    <div class="img-style">
                                        {{-- Setting src *and* data-src made the browser fetch the banner
                                             eagerly and lazysizes fetch it a second time. The fix is to
                                             give lazysizes the URL alone: native loading="lazy" does not
                                             hold these back on a throttled connection (Chrome stretches
                                             its lazy threshold to thousands of pixels there), and at
                                             ~125KB each they were outbidding the LCP image for bandwidth. --}}
                                        @php $__colSrcset = \App\Helper\CommonHelper::srcsetFor('slider/' . $slider->url); @endphp
                                        <img class="lazyload"
                                             data-src="{{ asset('storage/slider/' . $slider->url) }}"
                                             @if ($__colSrcset) data-srcset="{{ $__colSrcset }}" data-sizes="(max-width: 991px) 100vw, 50vw" @endif
                                             {!! \App\Helper\CommonHelper::imageSizeAttrs('slider/' . $slider->url) !!}
                                             decoding="async"
                                             alt="{{ $__cCaption ?: 'Collection banner' }}">
                                    </div>
                                    @if ($__cHasOverlay)
                                        <div class="hero-overlay hero-overlay--center hero-overlay--sm">
                                            <div class="hero-overlay__inner">
                                                @if ($__cCaption !== '')
                                                    <h3 class="hero-caption">{{ $__cCaption }}</h3>
                                                @endif
                                                @if ($__cActionUrl !== '')
                                                    <a href="{{ $__cActionUrl }}" class="hero-cta">
                                                        <span>Shop Now</span>
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="sw-pagination-collection sw-dots type-circle justify-content-center"></div>
                </div>
            </div>
        @endif
    </section>
    <!-- /slider collection -->

    <!-- Trendy Collection -->
    <section class="flat-spacing">
        <div class="container">
            <div class="heading-section text-center wow fadeInUp">
                <h3 class="heading">Bestsellers Right Now</h3>
                <p class="subheading text-secondary">The moulds and kits our makers keep reordering.</p>
            </div>
            <div dir="ltr" class="swiper tf-sw-recent" data-preview="4" data-tablet="3" data-mobile="2" data-space-lg="30"
                data-space-md="30" data-space="15" data-pagination="1" data-pagination-md="1" data-pagination-lg="1">
                <div class="swiper-wrapper">
                    @if(!empty($data['trendy_products']))
                        @foreach ($data['trendy_products'] as $product)
                            <!-- trendy products -->
                            <div class="swiper-slide">
                                <div class="card-product wow fadeInUp" data-wow-delay="0.1s">
                                    @include('components.product-card')
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
                <div class="sw-pagination-recent sw-dots type-circle justify-content-center"></div>
            </div>
        </div>
    </section>
    <!-- /Trendy Collection -->

    <!-- What Our Makers Say: video reel -->
    @include('client.partials.home-videos', ['videos' => $data['videos'] ?? collect()])
    <!-- /video reel -->

    <!-- Testimonial -->
    @if(!empty($data['reviews']))
        <section class="flat-spacing pt-0">
            <div class="container">
                <div class="heading-section text-center wow fadeInUp">
                    <h3 class="heading">Loved by Makers</h3>
                    <p class="subheading">Real results from hobbyists, artists and small businesses who cast with us.</p>
                </div>
                <div dir="ltr" class="swiper tf-sw-testimonial wow fadeInUp" data-wow-delay="0.1s" data-preview="2"
                    data-tablet="1.3" data-mobile="1" data-space-lg="30" data-space-md="30" data-space="15" data-pagination="1"
                    data-pagination-md="1" data-pagination-lg="1">
                    <div class="swiper-wrapper">
                        @foreach ($data['reviews'] as $review)
                            <div class="swiper-slide">
                                <div class="testimonial-item hover-img">
                                    {{-- A review photo is optional and most reviews have none. Rendering
                                         the tag with src="" made the browser draw a broken-image icon and
                                         the alt text, so drop the whole block instead — .testimonial-item
                                         is a flex row and the content simply takes the full width. --}}
                                    @if (!empty($review->image))
                                        <div class="img-style">
                                            <img src="{{ asset('storage/review/' . $review->image) }}"
                                                {!! \App\Helper\CommonHelper::imageSizeAttrs('review/' . $review->image) !!}
                                                loading="lazy" decoding="async"
                                                alt="Photo from {{ $review->name ?: 'a customer' }}'s review">
                                        </div>
                                    @endif
                                    <div class="content">
                                        <div class="content-top">
                                            <div class="list-star-default">
                                                @for ($rate = 0; $rate < (int) ($review->rate ?? 0); $rate++)
                                                    <i class="icon icon-star"></i>
                                                @endfor
                                            </div>
                                            <p class="text-secondary">"{{ $review->review }}"</p>
                                            <div class="box-author">
                                                <div class="text-title author">{{ $review->name ?? '' }}</div>
                                                <svg class="icon" width="20" height="21" viewBox="0 0 20 21" fill="none"
                                                    xmlns="http://www.w3.org/2000/svg">
                                                    <g clip-path="url(#clip0_15758_14563)"><path d="M6.875 11.6255L8.75 13.5005L13.125 9.12549" stroke="#3DAB25" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M10 18.5005C14.1421 18.5005 17.5 15.1426 17.5 11.0005C17.5 6.85835 14.1421 3.50049 10 3.50049C5.85786 3.50049 2.5 6.85835 2.5 11.0005C2.5 15.1426 5.85786 18.5005 10 18.5005Z" stroke="#3DAB25" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></g><defs><clipPath id="clip0_15758_14563"><rect width="20" height="20" fill="white" transform="translate(0 0.684082)" /></clipPath></defs>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="box-avt">
                                            {{-- <div class="avatar avt-60 round">
                                                <img src="{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}" alt="avt">
                                            </div> --}}
                                            <div class="box-price">
                                                <p class="text-title text-line-clamp-1">{{$review->title}}</p>
                                                {{-- <div class="text-button price">$60.00</div> --}}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>   
                        @endforeach
                    </div>
                    <div class="sw-pagination-testimonial sw-dots type-circle d-flex justify-content-center"></div>
                </div>
            </div>
        </section>
    @endif
    <!-- /Testimonial -->

    @php
        $__features = collect($settings?->content('features') ?? [])
            ->filter(fn ($f) => is_array($f) && trim((string)($f['title'] ?? '')) !== '')
            ->values()
            ->all();
    @endphp
    @if (! empty($__features))
    <!-- Iconbox (admin-editable via Settings → Storefront content → Homepage feature grid) -->
    <section class="flat-spacing line-top-container">
        <div class="container">
            <div dir="ltr" class="swiper tf-sw-iconbox" data-preview="4" data-tablet="3" data-mobile-sm="2" data-mobile="1"
                data-space-lg="30" data-space-md="30" data-space="15" data-pagination="1" data-pagination-sm="2"
                data-pagination-md="3" data-pagination-lg="4">
                <div class="swiper-wrapper">
                    @foreach ($__features as $__feat)
                        <div class="swiper-slide">
                            <div class="tf-icon-box">
                                <div class="icon-box"><span class="icon {{ $__feat['icon'] ?? 'icon-sealCheck' }}"></span></div>
                                <div class="content text-center">
                                    <h4 class="icon-box-title">{{ $__feat['title'] }}</h4>
                                    <p class="text-secondary">{{ $__feat['description'] ?? '' }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="sw-pagination-iconbox sw-dots type-circle justify-content-center"></div>
            </div>
        </div>
    </section>
    <!-- /Iconbox -->
    @endif

    <!-- Gallery shop gram -->
    <section class="flat-spacing pt-0 d-none">
        <div class="container">
            <div class="heading-section text-center wow fadeInUp">
                <h3 class="heading">Shop Instagram</h3>
                <p class="subheading text-secondary">Tag @{{ config('app.name') }} for a chance to be featured.</p>
            </div>
            <div dir="ltr" class="swiper tf-sw-shop-gallery" data-preview="5" data-tablet="3" data-mobile="2"
                data-space-lg="10" data-space-md="10" data-space="8" data-pagination="2" data-pagination-md="3"
                data-pagination-lg="1">
                <div class="swiper-wrapper">
                    <div class="swiper-slide">
                        <div class="gallery-item hover-overlay hover-img wow fadeInUp" data-wow-delay=".1s">
                            <div class="img-style">
                                <img class="lazyload img-hover"
                                    data-src="{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}"
                                    src="{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}" alt="{{ config('app.name') }} Instagram feed">
                            </div>
                            <a href="{{route('client.product')}}" class="box-icon hover-tooltip"><span
                                    class="icon icon-eye"></span>
                                <span class="tooltip">View Product</span></a>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <div class="gallery-item hover-overlay hover-img wow fadeInUp" data-wow-delay=".2s">
                            <div class="img-style">
                                <img class="lazyload img-hover"
                                    data-src="{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}"
                                    src="{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}" alt="{{ config('app.name') }} Instagram feed">
                            </div>
                            <a href="{{route('client.product')}}" class="box-icon hover-tooltip"><span
                                    class="icon icon-eye"></span>
                                <span class="tooltip">View Product</span></a>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <div class="gallery-item hover-overlay hover-img wow fadeInUp" data-wow-delay=".3s">
                            <div class="img-style">
                                <img class="lazyload img-hover"
                                    data-src="{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}"
                                    src="{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}" alt="{{ config('app.name') }} Instagram feed">
                            </div>
                            <a href="{{route('client.product')}}" class="box-icon hover-tooltip"><span
                                    class="icon icon-eye"></span>
                                <span class="tooltip">View Product</span></a>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <div class="gallery-item hover-overlay hover-img wow fadeInUp" data-wow-delay=".4s">
                            <div class="img-style">
                                <img class="lazyload img-hover"
                                    data-src="{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}"
                                    src="{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}" alt="{{ config('app.name') }} Instagram feed">
                            </div>
                            <a href="{{route('client.product')}}" class="box-icon hover-tooltip"><span
                                    class="icon icon-eye"></span>
                                <span class="tooltip">View Product</span></a>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <div class="gallery-item hover-overlay hover-img wow fadeInUp" data-wow-delay=".5s">
                            <div class="img-style">
                                <img class="lazyload img-hover"
                                    data-src="{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}"
                                    src="{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}" alt="{{ config('app.name') }} Instagram feed">
                            </div>
                            <a href="{{route('client.product')}}" class="box-icon hover-tooltip"><span
                                    class="icon icon-eye"></span>
                                <span class="tooltip">View Product</span></a>
                        </div>
                    </div>
                </div>
                <div class="sw-pagination-gallery sw-dots type-circle justify-content-center"></div>
            </div>
        </div>
    </section>
    <!-- /Gallery shop gram -->
    
    <!-- Marquee -->
    <section class="tf-marquee">
        <div class="marquee-wrapper">
            <div class="initial-child-container">
                <div class="marquee-child-item">
                    <p class="text-btn-uppercase">Free shipping on all orders over ₹999</p>
                </div>
                <div class="marquee-child-item">
                    <span class="icon icon-lightning-line"></span>
                </div>
                <div class="marquee-child-item">
                    <p class="text-btn-uppercase">Premium silicone moulds · 14-day easy returns</p>
                </div>
                <div class="marquee-child-item">
                    <span class="icon icon-lightning-line"></span>
                </div>
                <!-- 2 -->
                <div class="marquee-child-item">
                    <p class="text-btn-uppercase">Free shipping on all orders over ₹999</p>
                </div>
                <div class="marquee-child-item">
                    <span class="icon icon-lightning-line"></span>
                </div>
                <div class="marquee-child-item">
                    <p class="text-btn-uppercase">Premium silicone moulds · 14-day easy returns</p>
                </div>
                <div class="marquee-child-item">
                    <span class="icon icon-lightning-line"></span>
                </div>
                <!-- 3 -->
                <div class="marquee-child-item">
                    <p class="text-btn-uppercase">Free shipping on all orders over ₹999</p>
                </div>
                <div class="marquee-child-item">
                    <span class="icon icon-lightning-line"></span>
                </div>
                <div class="marquee-child-item">
                    <p class="text-btn-uppercase">Premium silicone moulds · 14-day easy returns</p>
                </div>
                <div class="marquee-child-item">
                    <span class="icon icon-lightning-line"></span>
                </div>
                <!-- 4 -->
                <div class="marquee-child-item">
                    <p class="text-btn-uppercase">Free shipping on all orders over ₹999</p>
                </div>
                <div class="marquee-child-item">
                    <span class="icon icon-lightning-line"></span>
                </div>
                <div class="marquee-child-item">
                    <p class="text-btn-uppercase">Premium silicone moulds · 14-day easy returns</p>
                </div>
                <div class="marquee-child-item">
                    <span class="icon icon-lightning-line"></span>
                </div>
                <!-- 5 -->
                <div class="marquee-child-item">
                    <p class="text-btn-uppercase">Free shipping on all orders over ₹999</p>
                </div>
                <div class="marquee-child-item">
                    <span class="icon icon-lightning-line"></span>
                </div>
                <div class="marquee-child-item">
                    <p class="text-btn-uppercase">Premium silicone moulds · 14-day easy returns</p>
                </div>
                <div class="marquee-child-item">
                    <span class="icon icon-lightning-line"></span>
                </div>
                <!-- 6 -->
                <div class="marquee-child-item">
                    <p class="text-btn-uppercase">Free shipping on all orders over ₹999</p>
                </div>
                <div class="marquee-child-item">
                    <span class="icon icon-lightning-line"></span>
                </div>
                <div class="marquee-child-item">
                    <p class="text-btn-uppercase">Premium silicone moulds · 14-day easy returns</p>
                </div>
                <div class="marquee-child-item">
                    <span class="icon icon-lightning-line"></span>
                </div>
            </div>
        </div>
    </section>
    <!-- /Marquee -->
@endsection