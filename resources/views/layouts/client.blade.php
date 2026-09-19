<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-US" lang="en-US">
<head>
    <meta charset="utf-8">
    <title>@yield('title', config('app.name') . ' — Silicone Moulds, DIY Painting Kits & Craft Supplies')</title>

    <meta name="author" content="{{ config('app.name') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('meta_description')">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- SEO / Open Graph --}}
    <link rel="canonical" href="@yield('canonical_url', url()->current())">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="@yield('title', config('app.name'))">
    <meta property="og:description" content="@yield('meta_description')">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="@yield('og_url', url()->current())">
    @php $__ogImage = !empty($settings->image) ? asset('storage/setting/' . $settings->image) : asset('client/images/logo/logo.svg'); @endphp
    <meta property="og:image" content="@yield('og_image', $__ogImage)">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', config('app.name'))">
    <meta name="twitter:description" content="@yield('meta_description')">
    <meta name="twitter:image" content="@yield('og_image', $__ogImage)">

    {{-- Site-wide structured data (Organization + WebSite) --}}
    {!! \App\Helper\SeoHelper::organizationJsonLd($settings ?? null) !!}
    {!! \App\Helper\SeoHelper::websiteJsonLd() !!}

    {{-- LCP image preload, pushed by the page that knows which image it is.
         This has to sit above the stylesheets: the hero <img> is discoverable
         in the markup, but its request still queues behind ~140KB of
         render-blocking CSS, which is where most of the LCP "load delay" went.
         Preloading here starts it on the first free connection instead. --}}
    @stack('preload')

    {{-- Preconnects for third-party assets speed up first paint.
         fonts.css is served from our own origin, but the @font-face rules
         inside it pull the actual woff2 files from fonts.gstatic.com — so the
         browser only discovers that origin after it has downloaded and parsed
         the stylesheet, then pays for DNS + TCP + TLS before a single glyph
         arrives. Warming the connection here is worth ~300ms of that wait. --}}
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    @if (request()->routeIs('client.checkout'))
        <link rel="preconnect" href="https://checkout.razorpay.com" crossorigin>
    @endif

    @hasSection('structured_data')@yield('structured_data')@endif

   {{-- fonts.css is only @font-face rules pointing at absolute fonts.gstatic.com
        URLs, so it inlines safely — and doing so means the browser can start
        fetching fonts from the first response instead of waiting on a separate
        render-blocking stylesheet to arrive first.
        font-icons.css stays a <link>: its url()s are relative to
        /client/fonts/, and inlined here they would resolve against the page. --}}
   <style>{!! \App\Helper\CommonHelper::inlineCss(['client/fonts/fonts.css']) !!}</style>
   <link rel="stylesheet" href="{{asset('client/fonts/font-icons.css')}}">
   <link rel="stylesheet" href="{{asset('client/css/min/bootstrap.min.css')}}">
   {{-- Drift (image zoom) and PhotoSwipe (lightbox) are only ever used on the
        product detail page, but were render-blocking on every page. --}}
   @if (request()->routeIs('client.product'))
      <link rel="stylesheet" href="{{asset('client/css/min/drift-basic.min.css')}}">
      <link rel="stylesheet" href="{{asset('client/css/min/photoswipe.min.css')}}">
   @endif
   <link rel="stylesheet" href="{{asset('client/css/min/swiper-bundle.min.css')}}">
   {{-- Animation keyframes only. wow.js toggles visibility inline, so nothing
        stays hidden if these arrive after first paint — load them without
        blocking the render and swap the media back once they land. --}}
   <link rel="stylesheet" href="{{asset('client/css/min/animate.min.css')}}" media="print" onload="this.media='all';this.onload=null">
   <noscript><link rel="stylesheet" href="{{asset('client/css/min/animate.min.css')}}"></noscript>
   @php $__stylesCss = public_path('client/css/styles.css'); @endphp
   <link rel="stylesheet" href="{{asset('client/css/styles.css')}}{{ is_file($__stylesCss) ? '?v='.filemtime($__stylesCss) : '' }}">
   {{-- Mobile responsive fixes only (no design/palette changes).
        Versioned by mtime so phones don't keep serving a stale copy. Kept as a
        file rather than inlined: at ~21KB it is big enough that a cacheable
        request beats repeating it in every HTML response. --}}
   @php $__mobileCss = public_path('client/css/mobile-fixes.css'); @endphp
   <link rel="stylesheet" href="{{asset('client/css/mobile-fixes.css')}}{{ is_file($__mobileCss) ? '?v='.filemtime($__mobileCss) : '' }}">
   {{-- brand-theme (palette token overrides) and page-hero are a couple of KB
        each, and both have to apply after styles.css — which, as separate
        <link>s, made them two more render-blocking round trips for less CSS
        than the request headers cost. Inlined here, in the same order. --}}
   <style>{!! \App\Helper\CommonHelper::inlineCss([
       'client/css/brand-theme.css',
       'client/css/page-hero.css',
       'client/css/components.css',
   ]) !!}</style>

    {{-- Account dropdown menu — clean themed menu, replaces old bulky black buttons --}}
    <style>
        .dropdown-account.account-menu {
            width: 280px !important;
            padding: 0 !important;
            background: #fff;
            border: 1px solid #eee;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,.08);
            overflow: hidden;
        }
        .account-menu__header {
            display: flex; align-items: center; gap: 12px;
            padding: 16px 18px;
            background: #fafafa;
            border-bottom: 1px solid #eee;
        }
        .account-menu__avatar {
            flex-shrink: 0;
            width: 40px; height: 40px;
            border-radius: 50%;
            background: #111;
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 600;
            overflow: hidden;
        }
        .account-menu__avatar img { width: 100%; height: 100%; object-fit: cover; }
        .account-menu__info { min-width: 0; flex: 1; }
        .account-menu__name {
            font-size: 14px; font-weight: 600; color: #111;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            margin-bottom: 2px;
        }
        .account-menu__email {
            font-size: 12px; color: #888;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .account-menu__list {
            list-style: none; margin: 0; padding: 6px 0;
        }
        .account-menu__list li a {
            display: flex; align-items: center; gap: 12px;
            padding: 9px 18px;
            font-size: 13px; color: #333;
            text-decoration: none;
            transition: background .15s ease, color .15s ease;
        }
        .account-menu__list li a:hover {
            background: #f5f5f5; color: #111;
        }
        .account-menu__list li a svg { flex-shrink: 0; opacity: .6; }
        .account-menu__list li a:hover svg { opacity: 1; }
        .account-menu__divider {
            height: 1px; background: #eee; margin: 4px 0;
        }
        .account-menu__logout button {
            width: 100%;
            display: flex; align-items: center; gap: 12px;
            background: transparent; border: 0;
            padding: 11px 18px;
            font-size: 13px; font-weight: 500;
            color: #a52323; cursor: pointer; text-align: left;
            transition: background .15s ease;
        }
        .account-menu__logout button:hover { background: #fdecec; }
        .account-menu__logout svg { opacity: .8; }

        /* Guest state */
        .account-menu__guest { padding: 18px; text-align: center; }
        .account-menu__guest-title { font-size: 15px; font-weight: 600; color: #111; margin-bottom: 4px; }
        .account-menu__guest-sub { font-size: 12px; color: #888; line-height: 1.5; margin: 0 0 14px; }
        .account-menu__signin {
            display: block;
            padding: 10px 16px;
            background: #111; color: #fff !important;
            border-radius: 8px;
            font-size: 13px; font-weight: 600;
            text-decoration: none; text-transform: none;
            transition: background .15s ease;
        }
        .account-menu__signin:hover { background: #333; color: #fff; }
        .account-menu__guest-foot {
            margin-top: 12px; font-size: 12px; color: #888;
        }
        .account-menu__guest-foot a { color: #0d6efd; font-weight: 600; text-decoration: none; }
        .account-menu__guest-foot a:hover { text-decoration: underline; }
    </style>

    <!-- Favicon and Touch Icons — driven by Admin > Settings -->
    @php $__favicon = !empty($settings->icon) ? asset('storage/setting/' . $settings->icon) : asset('client/images/logo/favicon.webp'); @endphp
    <link rel="shortcut icon" href="{{ $__favicon }}">
    <link rel="apple-touch-icon-precomposed" href="{{ $__favicon }}">
    @yield('style')
</head>
@php
    $carts = session('cart', []);
    $cartCount = count($carts);
    // Header wishlist badge — only meaningful when the customer is signed in.
    $wishlistCount = auth('customer')->check()
        ? \App\Models\Wishlist::where('customer_id', auth('customer')->id())->count()
        : 0;
@endphp
<body>
    <!-- Scroll Top -->
    <button id="scroll-top">
        <svg width="24" height="25" viewBox="0 0 24 25" fill="none" xmlns="http://www.w3.org/2000/svg">
            <g clip-path="url(#clip0_15741_24194)">
            <path d="M3 11.9175L12 2.91748L21 11.9175H16.5V20.1675C16.5 20.3664 16.421 20.5572 16.2803 20.6978C16.1397 20.8385 15.9489 20.9175 15.75 20.9175H8.25C8.05109 20.9175 7.86032 20.8385 7.71967 20.6978C7.57902 20.5572 7.5 20.3664 7.5 20.1675V11.9175H3Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </g>
            <defs>
            <clipPath id="clip0_15741_24194">
            <rect width="24" height="24" fill="white" transform="translate(0 0.66748)"/>
            </clipPath>
            </defs>
        </svg> 
    </button>
    
    <div id="wrapper">
        <!-- header -->
        @include('components.header')

        @yield('content')

        <!-- footer -->
        @include('components.footer')
    </div>

    <!-- search -->
    <div class="modal fade modal-search" id="search">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="d-flex justify-content-between align-items-center">
                    <h5>Search</h5>
                    <span class="icon-close icon-close-popup" data-bs-dismiss="modal"></span>
                </div>
                <form class="form-search" action="{{ route('client.category') }}" method="GET">
                    <fieldset class="text">
                        <input type="text" placeholder="Searching..." class="" name="text" tabindex="0" value="" aria-required="true" required="">
                    </fieldset>
                    <button class="" type="submit">
                        <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M11 19C15.4183 19 19 15.4183 19 11C19 6.58172 15.4183 3 11 3C6.58172 3 3 6.58172 3 11C3 15.4183 6.58172 19 11 19Z" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M21.35 21.0004L17 16.6504" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <!-- /search -->

    <!-- shoppingCart -->
    <div class="modal fullRight fade modal-shopping-cart" id="shoppingCart">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="d-flex flex-column flex-grow-1 h-100">
                    <div class="header">
                        <h5 class="title">Shopping Cart</h5>
                        <span class="icon-close icon-close-popup" data-bs-dismiss="modal"></span>
                    </div>
                    <div class="wrap">
                        <div class="tf-mini-cart-threshold d-none">
                            <div class="tf-progress-bar">
                                <div class="value" style="width: 0%;" data-progress="75">
                                    <i class="icon icon-shipping"></i>
                                </div>
                            </div>
                            <div class="text-caption-1">
                                Congratulations! You've got free shipping!
                            </div>
                        </div>
                        <div class="tf-mini-cart-wrap">
                            <div class="tf-mini-cart-main">
                                <div class="tf-mini-cart-sroll">
                                    <div class="tf-mini-cart-items">
                                        {{-- append the cart product --}}
                                    </div>
                                </div>
                            </div>
                            <div class="tf-mini-cart-bottom">
                                <div class="tf-mini-cart-bottom-wrap">
                                    <div class="tf-cart-totals-discounts">
                                        <h5>Subtotal</h5>
                                        <h5 class="tf-totals-total-value">₹0</h5>
                                    </div>
                                    <div class="tf-cart-checkbox" style="display: none">
                                        <div class="tf-checkbox-wrapp">
                                            <input class="" type="checkbox" id="CartDrawer-Form_agree" name="agree_checkbox">
                                            <div>
                                                <i class="icon-check"></i>
                                            </div>
                                        </div>
                                        <label for="CartDrawer-Form_agree">
                                            I agree to the store's terms and conditions.
                                        </label>
                                    </div>
                                    <div class="tf-mini-cart-view-checkout">
                                        <a href="{{route('client.shoppingcart')}}" class="tf-btn w-100 btn-white radius-4 has-border">
                                            <span class="text">View cart</span>
                                        </a>
                                        <a href="{{route('client.checkout')}}" class="tf-btn w-100 btn-fill radius-4">
                                            <span class="text">Check Out</span>
                                        </a>
                                    </div>
                                    <div class="text-center">
                                        <a class="link text-btn-uppercase" href="{{route('client.shop')}}">Or continue shopping</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /shoppingCart -->

    <template id="cart-row">
        <div class="tf-mini-cart-item file-delete">
            <div class="tf-mini-cart-image">
                <img class="lazyload" data-src="{{asset('storage/product/{image}')}}" src="{{asset('storage/product/{image}')}}" alt="{name}">
            </div>
            <div class="tf-mini-cart-info flex-grow-1">
                <div class="mb_12 d-flex align-items-center justify-content-between flex-wrap gap-12">
                    <div class="text-title">
                        <a href="{{route('client.product')}}/{slug}" class="link text-line-clamp-1">{name}</a>
                    </div>
                    <div class="text-button tf-btn-remove remove remove-cart-item" data-product-id="{id}" data-variant-id="{variant_id}" data-cart-key="{cart_key}" data-url="{{route('client.removeCartItem')}}">Remove</div>
                </div>
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-12">
                    <div>
                        <div class="option">{option_label}</div>
                        <div class="names"></div>
                    </div>
                    <div class="text-button">{quantity} X {price}</div>
                </div>
            </div>
        </div>
    </template>
    
    <!-- quickAdd -->
    <div class="modal fade modal-quick-add" id="quickAdd">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="header">
                    <span class="icon-close icon-close-popup" data-bs-dismiss="modal"></span>
                </div>
                <div id="quick-product-info">
                </div>
            </div>
        </div>
    </div>
    <!-- /quickAdd -->

    <!-- sidebar account-->
    <div class="offcanvas offcanvas-start canvas-sidebar" id="mbAccount">
        <div class="canvas-wrapper">
            <header class="canvas-header">
                <span class="text-btn-uppercase">SIDEBAR ACCOUNT</span>
                <span class="icon-close icon-close-popup" data-bs-dismiss="offcanvas" aria-label="Close"></span>
            </header>
            <div class="canvas-body sidebar-mobile-append"></div>
        </div>
    </div>
    <!-- End sidebar account -->

    <!-- Javascript -->
    <script type="text/javascript" src="{{asset('client/js/min/bootstrap.min.js')}}"></script>
    <script type="text/javascript" src="{{asset('client/js/min/jquery.min.js')}}"></script>
    <script type="text/javascript" src="{{asset('client/js/min/swiper-bundle.min.js')}}"></script>
    <script type="text/javascript" src="{{asset('client/js/min/carousel.min.js')}}"></script>
    <script type="text/javascript" src="{{asset('client/js/min/lazysize.min.js')}}"></script>
    <script type="text/javascript" src="{{asset('client/js/min/wow.min.js')}}"></script>
    <script type="text/javascript" src="{{asset('client/js/min/multiple-modal.min.js')}}"></script>
    {{-- Three libraries used to load on all 20-odd storefront pages to serve one
         or two of them, costing every visitor the download plus the parse/compile:
           bootstrap-select (100KB) — main.js only calls .selectpicker() behind an
             .image-select guard, and no view in the app renders that class at all.
           nouislider (41KB)        — only shop.min.js uses it, so shop/category
             now pull it in beside that file.
           count-down (5KB)         — only the cart's "your cart expires in" timer.
         Nothing here is referenced by main.js/carousel.js at load time, so they
         can leave without a load-order problem. --}}
    {{-- <script type="text/javascript" src="{{asset('client/js/min/main.min.js')}}"></script> --}}
    {{-- Versioned by mtime, same as the stylesheets above: these three change with
         cart/variant behaviour, and a browser serving a stale copy against fresh
         markup silently reintroduces the bug it was cached before. --}}
    @php
        $__mainJs = public_path('client/js/main.js');
        $__pickerJs = public_path('client/js/variant-picker.js');
        $__commonJs = public_path('client/js/custom-common.js');
    @endphp
    <script type="text/javascript" src="{{asset('client/js/main.js')}}{{ is_file($__mainJs) ? '?v='.filemtime($__mainJs) : '' }}"></script>
    <script type="text/javascript" src="{{asset('client/js/variant-picker.js')}}{{ is_file($__pickerJs) ? '?v='.filemtime($__pickerJs) : '' }}"></script>
    <script type="text/javascript" src="{{asset('client/js/custom-common.js')}}{{ is_file($__commonJs) ? '?v='.filemtime($__commonJs) : '' }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
    <script>
        $(document).ready(function() {
            const flashSuccess = @json(Session::get('success'));
            const flashError   = @json(Session::get('error'));
            const flashInfo    = @json(Session::get('info'));
            if (flashSuccess) {
                showSweetAlert('success', flashSuccess);
            } else if (flashError) {
                showSweetAlert('error', flashError);
            } else if (flashInfo) {
                showSweetAlert('info', flashInfo);
            }

            const $searchForm  = $('.form-search');
            const $searchInput = $('.form-search input[name="text"]');


            // Auto search redirect after typing delay
            let typingTimer;
            const typingDelay = 2000; // 2 seconds

             $searchInput.on('keyup', function () {
                clearTimeout(typingTimer);
                typingTimer = setTimeout(function () {
                    const query = $.trim($searchInput.val());
                    if (query.length > 2) {
                        window.location.href = `${$searchForm.attr('action')}?query=${encodeURIComponent(query)}`;
                    }
                }, typingDelay);
            });

            $searchInput.on('keydown', function () {
                clearTimeout(typingTimer);
            });
        })
    </script>
    {{-- Razorpay's SDK used to load on every page. It is only ever constructed
         on the checkout page, but it still cost a third-party connection and
         ~0.8s of script evaluation on the storefront. Checkout pulls it in
         itself via @push('head') / @section('scripts'). --}}

    {{-- Global wishlist toggle handler (works on every page that uses product-card) --}}
    <script>
    (function () {
        var isLoggedIn = {{ Auth::guard('customer')->check() ? 'true' : 'false' }};
        var wishlistIds = new Set();

        function applyWishlistState() {
            document.querySelectorAll('.wishlist-toggle-btn').forEach(function (btn) {
                var pid = parseInt(btn.getAttribute('data-product-id'));
                var on = wishlistIds.has(pid);
                btn.classList.toggle('active', on);
                btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                btn.setAttribute('aria-label', on ? 'Remove from wishlist' : 'Add to wishlist');
                btn.setAttribute('title', on ? 'Remove from wishlist' : 'Add to wishlist');
            });
        }

        // Live-updates the wishlist badge in the header when the count changes.
        // Exposed globally so other scripts (wishlist page, product page) can
        // call it after their own AJAX toggles.
        window.updateWishlistCount = function (count) {
            count = parseInt(count, 10) || 0;
            var $badge = $('.wishlist-count');
            $badge.text(count).toggle(count > 0);
        };

        if (isLoggedIn) {
            $.get('{{ route('client.wishlist.ids') }}', function (res) {
                if (res && res.ids) {
                    res.ids.forEach(function (id) { wishlistIds.add(id); });
                    applyWishlistState();
                }
                if (res && typeof res.count !== 'undefined') {
                    window.updateWishlistCount(res.count);
                }
            });
        }

        $(document).on('click', '.wishlist-toggle-btn', function (e) {
            e.preventDefault();
            if (!isLoggedIn) {
                if (typeof showSweetAlert === 'function') {
                    showSweetAlert('info', 'Please sign in to save items to your wishlist.');
                } else {
                    alert('Please sign in to save items to your wishlist.');
                }
                return;
            }
            var btn = $(this);
            var productId = parseInt(btn.data('product-id'));
            var url = btn.data('url');
            btn.prop('disabled', true);
            $.ajax({
                url: url, type: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { product_id: productId },
                success: function (res) {
                    btn.prop('disabled', false);
                    if (res.wishlisted) {
                        wishlistIds.add(productId);
                    } else {
                        wishlistIds.delete(productId);
                    }
                    applyWishlistState();
                    if (typeof res.count !== 'undefined') window.updateWishlistCount(res.count);
                    if (typeof showSweetAlert === 'function') {
                        showSweetAlert(res.wishlisted ? 'success' : 'info', res.message);
                    }
                },
                error: function () {
                    btn.prop('disabled', false);
                }
            });
        });
    })();
    </script>

    @yield('scripts')
    @stack('page-scripts')

    @include('components.whatsapp-button')
</body>
</html>