@extends('layouts.client')

@section('title')
    About Us | {{ config('app.name') }}
@endsection

@section('meta_description')
At {{ config('app.name') }} we make premium silicone moulds, DIY painting kits, resin and candle moulds, concrete casting materials, T-light holders and flower pots. Durable, non-toxic materials, quick-shipped across India and backed by a 14-day return promise.
@endsection

@php
    $__aboutTitle  = $settings?->content('about.page_title') ?: 'About Our Store';
    $__aboutMain   = $settings?->content('about.main_title') ?: 'Premium moulds and DIY kits for every maker.';
    $__aboutCta    = $settings?->content('about.cta_label')  ?: 'Shop moulds & kits';
    $__aboutTabs   = collect($settings?->content('about.tabs') ?? [])
        ->filter(fn ($t) => is_array($t) && trim((string)($t['label'] ?? '')) !== '' && trim((string)($t['content'] ?? '')) !== '')
        ->values()
        ->all();
    $__features    = collect($settings?->content('features') ?? [])
        ->filter(fn ($f) => is_array($f) && trim((string)($f['title'] ?? '')) !== '')
        ->values()
        ->all();
@endphp

@section('content')
    <!-- page-hero -->
    @include('client.partials.page-hero', [
        'title'  => $__aboutTitle,
        'crumbs' => [['label' => $__aboutTitle]],
    ])
    <!-- /page-hero -->

    <!-- about-us -->
    <section class="flat-spacing about-us-main pb_0">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <div class="about-us-features wow fadeInLeft">
                        <img class="lazyload" data-src="{{ \App\Helper\CommonHelper::assetV('client/images/home/brand-about.webp') }}" src="{{ \App\Helper\CommonHelper::assetV('client/images/home/brand-about.webp') }}" alt="Silicone moulds, DIY painting kits and T-light holders from {{ config('app.name') }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="about-us-content">
                        <h3 class="title wow fadeInUp">{{ $__aboutMain }}</h3>
                        @if (! empty($__aboutTabs))
                            <div class="widget-tabs style-3">
                                <ul class="widget-menu-tab wow fadeInUp">
                                    @foreach ($__aboutTabs as $i => $tab)
                                        <li class="item-title {{ $i === 0 ? 'active' : '' }}">
                                            <span class="inner text-button">{{ $tab['label'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="widget-content-tab wow fadeInUp">
                                    @foreach ($__aboutTabs as $i => $tab)
                                        <div class="widget-content-inner {{ $i === 0 ? 'active' : '' }}">
                                            <p>{!! nl2br(e($tab['content'])) !!}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <a href="{{ route('client.shop') }}" class="tf-btn btn-fill radius-4 wow fadeInUp"><span class="text text-button">{{ $__aboutCta }}</span></a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- /about-us -->

    @if (! empty($__features))
    <!-- Iconbox (admin-editable via Settings → Storefront content → Homepage feature grid) -->
    <section class="flat-spacing line-bottom-container">
        <div class="container">
            <div dir="ltr" class="swiper tf-sw-iconbox" data-preview="4" data-tablet="3" data-mobile-sm="2" data-mobile="1" data-space-lg="30" data-space-md="30" data-space="15" data-pagination="1" data-pagination-sm="2" data-pagination-md="3" data-pagination-lg="4">
                <div class="swiper-wrapper">
                    @foreach ($__features as $__feat)
                        <div class="swiper-slide">
                            <div class="tf-icon-box style-2">
                                <div class="icon-box"><span class="icon {{ $__feat['icon'] ?? 'icon-sealCheck' }}"></span></div>
                                <div class="content">
                                    <h6>{{ $__feat['title'] }}</h6>
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
@endsection
