@php
    // Public tracking entry points: hidden when the operator has switched
    // tracking off, when no courier is configured, or when the admin has
    // unticked "Show track order" in Settings.
    $__showTrack = \App\Helper\OrderStatusHelper::publicTrackingVisible($settings ?? null);

    $__topbarMessages = collect($settings?->content('topbar_messages') ?? [])
        ->map(fn ($m) => trim((string) $m))
        ->filter()
        ->values()
        ->all();
@endphp
@if (! empty($__topbarMessages))
<!-- Top Bar (admin-editable via Settings → Storefront content) -->
<div class="tf-topbar style-2 line-bt">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-xl-12 col-12 text-center">
                <div class="swiper tf-sw-top_bar" data-preview="1" data-space="0" data-loop="true" data-speed="1000" data-auto-play="true" data-delay="2000">
                    <div class="swiper-wrapper">
                        @foreach ($__topbarMessages as $__msg)
                            <div class="swiper-slide">
                                <p class="top-bar-text text-line-clamp-1 text-btn-uppercase fw-semibold letter-1">{{ $__msg }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /Top Bar -->
@endif

<!-- Header -->
<header id="header" class="header-default">
    <div class="container">
        <div class="row wrapper-header align-items-center">
            <div class="col-md-4 col-3 d-xl-none">
                <a href="#mobileMenu" class="mobile-menu" data-bs-toggle="offcanvas" aria-controls="mobileMenu" aria-label="Open menu">
                    <i class="icon icon-categories"></i>
                </a>
            </div>
            <div class="col-xl-3 col-md-4 col-6">
                <a href="{{route('client.home')}}" class="logo-header">
                    @php
                        $__logo = !empty($settings->image) ? asset('storage/setting/' . $settings->image) : asset('client/images/logo/logo.svg');
                        // The uploaded logo is served at its original upload size — a 100KB+ PNG
                        // for a mark the header never paints wider than 150px. It sits above the
                        // fold and loads eagerly, so those bytes come straight out of the LCP's
                        // bandwidth budget. Hand the browser the WebP derivatives instead.
                        $__logoSrcset = !empty($settings->image)
                            ? \App\Helper\CommonHelper::srcsetFor('setting/' . $settings->image)
                            : '';
                    @endphp
                    {{-- No width/height attributes: the uploaded logo may be a wide wordmark or a
                         stacked mark, so any fixed ratio here would be wrong for half of them. The
                         .logo-header box reserves the height instead, which is what prevents CLS. --}}
                    <img src="{{ $__logo }}"
                         @if ($__logoSrcset) srcset="{{ $__logoSrcset }}" sizes="150px" @endif
                         alt="{{ config('app.name') }} logo" class="logo">
                </a>
            </div>
            <div class="col-xl-6 d-none d-xl-block">
                <nav class="box-navigation text-center">
                    <ul class="box-nav-ul d-flex align-items-center justify-content-center">
                        <li class="menu-item {{ Request::routeIs('client.home') ? 'active' : '' }}">
                            <a href="{{route('client.home')}}" class="item-link">Home</a>
                        </li>
                        <li class="menu-item {{ Request::routeIs('client.shop') ? 'active' : '' }}">
                            <a href="{{route('client.shop')}}" class="item-link">Shop</a>
                        </li>
                        <li class="menu-item {{ Request::routeIs('client.category') ? 'active' : '' }}">
                            <a href="{{route('client.category')}}" class="item-link">Categories</a>
                        </li>
                        <li class="menu-item {{ Request::routeIs('client.blog.*') ? 'active' : '' }}">
                            <a href="{{route('client.blog.index')}}" class="item-link">Blog</a>
                        </li>
                        <li class="menu-item {{ Request::routeIs('client.about') ? 'active' : '' }}">
                            <a href="{{route('client.about')}}" class="item-link">About us</a>
                        </li>
                        <li class="menu-item {{ Request::routeIs('client.contact') ? 'active' : '' }}">
                            <a href="{{route('client.contact')}}" class="item-link">Contact us</a>
                        </li>
                        @if ($__showTrack)
                        <li class="menu-item {{ Request::routeIs('client.track.*') ? 'active' : '' }}">
                            <a href="{{route('client.track.form')}}" class="item-link">Track Order</a>
                        </li>
                        @endif
                    </ul>
                </nav>
            </div>
            <div class="col-xl-3 col-md-4 col-3">
                <ul class="nav-icon d-flex justify-content-end align-items-center">
                    <li class="nav-search d-flex align-items-center gap-2">
                        <a href="#search" data-bs-toggle="modal" class="nav-icon-item">
                            <svg class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M11 19C15.4183 19 19 15.4183 19 11C19 6.58172 15.4183 3 11 3C6.58172 3 3 6.58172 3 11C3 15.4183 6.58172 19 11 19Z" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M21.35 21.0004L17 16.6504" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>    
                        </a>
                    </li>
                    <li class="nav-account">
                        @if (auth('customer')->check())
                            <a href="{{ route('client.profile') }}" class="nav-icon-item" aria-label="Account menu">
                        @else
                            <a href="{{ route('client.login') }}" class="nav-icon-item" aria-label="Sign in">
                        @endif
                            <svg class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M12 11C14.2091 11 16 9.20914 16 7C16 4.79086 14.2091 3 12 3C9.79086 3 8 4.79086 8 7C8 9.20914 9.79086 11 12 11Z" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                        <div class="dropdown-account dropdown-login account-menu">
                            @if (auth('customer')->check())
                                @php $customer = auth('customer')->user(); @endphp
                                <div class="account-menu__header">
                                    <div class="account-menu__avatar">
                                        @if (! empty($customer->image))
                                            <img src="{{ asset('storage/customer/' . $customer->image) }}" alt="{{ $customer->name }}">
                                        @else
                                            <span>{{ strtoupper(substr($customer->name ?? 'U', 0, 1)) }}</span>
                                        @endif
                                    </div>
                                    <div class="account-menu__info">
                                        <div class="account-menu__name">{{ $customer->name }}</div>
                                        <div class="account-menu__email">{{ $customer->email ?? $customer->phone }}</div>
                                    </div>
                                </div>

                                <ul class="account-menu__list">
                                    <li>
                                        <a href="{{ route('client.profile') }}">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                            My Profile
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('client.profile') }}?type=orders">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16.5 9.4 7.55 4.24"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                                            My Orders
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('client.profile') }}?type=address">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                            Addresses
                                        </a>
                                    </li>
                                    @if ($__showTrack)
                                    <li>
                                        <a href="{{ route('client.track.form') }}">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                                            Track Order
                                        </a>
                                    </li>
                                    @endif
                                </ul>

                                <div class="account-menu__divider"></div>

                                <form id="logout-form" action="{{ route('client.logout.user') }}" method="POST" class="account-menu__logout">
                                    @csrf
                                    @method('delete')
                                    <button type="submit">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                        Sign out
                                    </button>
                                </form>
                            @else
                                <div class="account-menu__guest">
                                    <div class="account-menu__guest-title">Welcome to {{ config('app.name') }}</div>
                                    <p class="account-menu__guest-sub">Sign in for a personalised experience and to track your orders.</p>
                                    <a href="{{ route('client.login') }}" class="account-menu__signin">Sign in</a>
                                    <div class="account-menu__guest-foot">
                                        New here? <a href="{{ route('client.register') }}">Create an account</a>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </li>
                    @php $wishlistCount = $wishlistCount ?? 0; @endphp
                    <li class="nav-wishlist">
                        <a
                            class="nav-icon-item nav-wishlist-link"
                            href="{{ route('client.wishlist') }}"
                            aria-label="View wishlist ({{ $wishlistCount }} items)"
                        >
                            <svg class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                            </svg>
                            <span class="count-box wishlist-count" style="{{ $wishlistCount > 0 ? '' : 'display:none' }}">{{ $wishlistCount }}</span>
                        </a>
                    </li>
                    <li class="nav-cart">
                        <a class="nav-icon-item get-cart-details" data-url="{{route('client.cartList')}}" href="{{ route('client.shoppingcart') }}" aria-label="View cart ({{ $cartCount ?? 0 }} items)">
                            <svg class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M16.5078 10.8734V6.36686C16.5078 5.17166 16.033 4.02541 15.1879 3.18028C14.3428 2.33514 13.1965 1.86035 12.0013 1.86035C10.8061 1.86035 9.65985 2.33514 8.81472 3.18028C7.96958 4.02541 7.49479 5.17166 7.49479 6.36686V10.8734M4.11491 8.62012H19.8877L21.0143 22.1396H2.98828L4.11491 8.62012Z" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="count-box">{{ $cartCount ?? 0 }}</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header>
<!-- /Header -->

<!-- mobile menu -->
    <div class="offcanvas offcanvas-start canvas-mb" id="mobileMenu">
        <span class="icon-close icon-close-popup" data-bs-dismiss="offcanvas" aria-label="Close"></span>
        <div class="mb-canvas-content">
            <div class="mb-body">
                <div class="mb-content-top">
                    {{-- Shop, Categories, Wishlist and Profile are deliberately absent:
                         the fixed bottom toolbar (components/footer) already carries
                         all four on exactly the same viewports this drawer opens on,
                         and listing them twice made the menu read as a duplicate of
                         the bar sitting under it. Content pages live here, shopping
                         actions live in the bar. --}}
                    <ul class="nav-ul-mb" id="wrapper-menu-navigation">
                        <li class="nav-mb-item active">
                            <a href="{{route('client.home')}}" class="mb-menu-link" >
                                <span>Home</span>
                            </a>
                        </li>
                        <li class="nav-mb-item">
                            <a href="{{route('client.blog.index')}}" class="mb-menu-link">
                                <span>Blog</span>
                            </a>
                        </li>
                        <li class="nav-mb-item">
                            <a href="{{route('client.about')}}" class="mb-menu-link">
                                <span>About Us</span>
                            </a>
                        </li>
                        <li class="nav-mb-item">
                            <a href="{{route('client.contact')}}" class="mb-menu-link">
                                <span>Contact Us</span>
                            </a>
                        </li>
                        @if ($__showTrack)
                        <li class="nav-mb-item">
                            <a href="{{route('client.track.form')}}" class="mb-menu-link">
                                <span>Track Order</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>
                <div class="mb-other-content">
                    <div class="mb-notice">
                        <a href="{{ route('client.contact') }}" class="text-need">Need Help?</a>
                    </div>
                    <div class="mb-contact">
                        <p class="text-caption-1">{{ $settings->address ?? '' }}</p>
                        @if (! empty($settings?->address))
                            <a href="{{ route('client.contact') }}" class="tf-btn-default text-btn-uppercase">GET DIRECTION<i class="icon-arrowUpRight"></i></a>
                        @endif
                    </div>
                    <ul class="mb-info">
                        @if (! empty($settings?->email))
                            <li>
                                <i class="icon icon-mail"></i>
                                <p>{{ $settings->email }}</p>
                            </li>
                        @endif
                        @if (! empty($settings?->phone))
                            <li>
                                <i class="icon icon-phone"></i>
                                <p>{{ $settings->phone }}</p>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>       
    </div>
<!-- /mobile menu -->