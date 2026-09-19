@extends('layouts.client')

@section('title')
Shop Silicone Moulds, DIY Kits & Craft Supplies | {{ config('app.name') }}
@endsection

@section('meta_description')
Discover the full range at {{ config('app.name') }} — silicone moulds, resin moulds, candle moulds, DIY painting kits, concrete casting materials, T-light holders, flower pots and craft supplies. Durable, non-toxic materials for hobbyists and professionals, fast delivery across India, and easy 14-day returns.
@endsection

@section('structured_data')
{!! \App\Helper\SeoHelper::breadcrumbsJsonLd([
    ['name' => 'Home', 'url' => route('client.home')],
    ['name' => 'Shop', 'url' => route('client.shop')],
]) !!}
@endsection

@section('content')
    <!-- page-hero -->
    @include('client.partials.page-hero', [
        'title'  => 'Shop',
        'crumbs' => array_filter([
            ['label' => 'Shop'],
            ! empty($data['search'])
                ? ['label' => 'Search: “' . $data['search'] . '”']
                : (request('category') ? ['label' => request('category')] : null),
        ]),
    ])
    <!-- /page-hero -->
    <!-- Section product -->
    <section class="flat-spacing">
        <div class="container">
            <div class="tf-shop-control">
                <div class="tf-control-filter">
                    <button id="filterShop" class="filterShop tf-btn-filter">
                        <span class="icon icon-filter"></span>
                        <span class="text">Filters</span>
                    </button>
                    <div class="d-none d-lg-flex shop-sale-text">
                        <i class="icon icon-checkCircle"></i>
                        <p class="text-caption-1">Shop sale items only</p>
                    </div>
                </div>
                <ul class="tf-control-layout">
                    <li class="tf-view-layout-switch sw-layout-2" data-value-layout="tf-col-2">
                        <div class="item">
                            <svg class="icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="6" cy="6" r="2.5" stroke="#181818"/>
                                <circle cx="14" cy="6" r="2.5" stroke="#181818"/>
                                <circle cx="6" cy="14" r="2.5" stroke="#181818"/>
                                <circle cx="14" cy="14" r="2.5" stroke="#181818"/>
                            </svg>   
                        </div>
                    </li>
                    <li class="tf-view-layout-switch sw-layout-3 active" data-value-layout="tf-col-3">
                        <div class="item">
                            <svg class="icon" width="22" height="20" viewBox="0 0 22 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="3" cy="6" r="2.5" stroke="#181818"/>
                                <circle cx="11" cy="6" r="2.5" stroke="#181818"/>
                                <circle cx="19" cy="6" r="2.5" stroke="#181818"/>
                                <circle cx="3" cy="14" r="2.5" stroke="#181818"/>
                                <circle cx="11" cy="14" r="2.5" stroke="#181818"/>
                                <circle cx="19" cy="14" r="2.5" stroke="#181818"/>
                            </svg>                                    
                        </div>
                    </li>
                    <li class="tf-view-layout-switch sw-layout-4" data-value-layout="tf-col-4">
                        <div class="item">
                            <svg class="icon" width="30" height="20" viewBox="0 0 30 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="3" cy="6" r="2.5" stroke="#181818"/>
                                <circle cx="11" cy="6" r="2.5" stroke="#181818"/>
                                <circle cx="19" cy="6" r="2.5" stroke="#181818"/>
                                <circle cx="27" cy="6" r="2.5" stroke="#181818"/>
                                <circle cx="3" cy="14" r="2.5" stroke="#181818"/>
                                <circle cx="11" cy="14" r="2.5" stroke="#181818"/>
                                <circle cx="19" cy="14" r="2.5" stroke="#181818"/>
                                <circle cx="27" cy="14" r="2.5" stroke="#181818"/>
                            </svg>
                        </div>
                    </li>
                </ul>
                <div class="tf-control-sorting">
                    <p class="d-none d-lg-block text-caption-1">Sort by:</p>
                    <div class="tf-dropdown-sort" data-bs-toggle="dropdown">
                        <div class="btn-select">
                            <span class="text-sort-value">Best selling</span>
                            <span class="icon icon-arrow-down"></span>
                        </div>
                        <div class="dropdown-menu">
                            <div class="select-item" data-sort-value="best-selling">
                                <span class="text-value-item">Best selling</span>
                            </div>
                            <div class="select-item" data-sort-value="a-z">
                                <span class="text-value-item">Alphabetically, A-Z</span>
                            </div>
                            <div class="select-item" data-sort-value="z-a">
                                <span class="text-value-item">Alphabetically, Z-A</span>
                            </div>
                            <div class="select-item" data-sort-value="price-low-high">
                                <span class="text-value-item">Price, low to high</span>
                            </div>
                            <div class="select-item" data-sort-value="price-high-low">
                                <span class="text-value-item">Price, high to low</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="wrapper-control-shop">
                <div class="meta-filter-shop">
                    <div id="product-count-grid" class="count-text"></div>
                    <div id="applied-filters"></div>
                    <button id="remove-all" class="remove-all-filters text-btn-uppercase" style="display: none;">
                        REMOVE ALL <i class="icon icon-close"></i>
                    </button>
                </div>
                <div class="row">
                    <div class="col-xl-3">
                        <div class="sidebar-filter canvas-filter left">
                            @include('components.filter')
                        </div>
                    </div>
                    <div class="col-xl-9">
                        <div class="tf-grid-layout wrapper-shop tf-col-3" id="gridLayout">
                            @if(!empty($data['products']) && !empty($data['products']['data']))
                                @foreach($data['products']['data'] as $product)
                                    <!-- card product -->
                                    <div class="card-product grid">
                                        {{-- Top row is above the fold and carries the LCP element. --}}
                                        @include('components.product-card', ['eagerImage' => $loop->index < 3])
                                    </div>
                                @endforeach
                            @else
                                {{-- Was a bare emoji and an <h5>; now the same partial the
                                     category grid uses, so an empty result looks identical
                                     wherever a shopper lands on one. --}}
                                @include('client.partials.empty-state', [
                                    'title' => 'No products found',
                                    'message' => ! empty($data['search'])
                                        ? 'Nothing matched “' . $data['search'] . '”. Try a different keyword, or browse everything.'
                                        : 'No products available in this category yet.',
                                    'ctaLabel' => 'Browse all products',
                                    'ctaUrl' => route('client.shop'),
                                ])
                            @endif
                            @if(!empty($data['products']['data']) && !empty($data['products']['last_page']) && $data['products']['last_page'] > 1)
                            <!-- pagination -->
                            <ul class="wg-pagination justify-content-center">
                                @if(!empty($data['products']['prev_page_url']))
                                <li>
                                    <a href="{{ $data['products']['prev_page_url'] }}" class="pagination-item text-button" aria-label="Previous page">
                                        <i class="icon-arrLeft"></i>
                                    </a>
                                </li>
                                @endif
                                @foreach ($data['products']['links'] as $link)
                                    @if ($link['url'] && ($link['label'] != 'Next &raquo;' && $link['label'] != '&laquo; Previous'))
                                        <li class="{{ $link['active'] ? 'active' : ''}}">
                                            <a href="{{$link['url']}}" class="pagination-item text-button" @if($link['active']) aria-current="page" @endif>
                                                {{$link['label']}}
                                            </a>
                                        </li>
                                    @endif
                                @endforeach
                                @if(!empty($data['products']['next_page_url']))
                                <li>
                                    <a href="{{ $data['products']['next_page_url'] }}" class="pagination-item text-button" aria-label="Next page">
                                        <i class="icon-arrRight"></i>
                                    </a>
                                </li>
                                @endif
                            </ul>
                            @endif
                        </div>
                    </div>
                </div>  
            </div>
        </div>
    </section>
    <!-- /Section product -->
@endsection

@section('scripts')
    {{-- shop.min.js drives the price range filter, which is the only thing on the
         storefront that needs noUiSlider. It used to load site-wide from the
         layout; it belongs here, next to its one consumer. --}}
    <script type="text/javascript" src="{{asset('client/js/min/nouislider.min.js')}}"></script>
    <script type="text/javascript" src="{{ \App\Helper\CommonHelper::assetV('client/js/min/shop.min.js') }}"></script>
@endsection
