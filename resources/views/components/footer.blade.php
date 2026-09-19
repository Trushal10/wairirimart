<!-- Footer -->
<footer id="footer" class="footer">
    <div class="footer-wrap border-0">
        <div class="footer-body">
            <div class="container">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="footer-infor">
                            <div class="footer-logo">
                                <a href="{{route('client.home')}}">
                                    @php $__footerLogo = !empty($settings->image) ? asset('storage/setting/' . $settings->image) : asset('client/images/logo/logo.svg'); @endphp
                                    <img src="{{ $__footerLogo }}" alt="{{ config('app.name') }} logo">
                                </a>
                            </div>
                            <div class="footer-address">
                                <p>{{$settings->address ?? ''}}</p>
                                @if (! empty($settings?->address))
                                    <a target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps/search/?api=1&query={{ urlencode($settings->address) }}" class="tf-btn-default fw-6">GET DIRECTION<i class="icon-arrowUpRight"></i></a>
                                @endif
                            </div>
                            <ul class="footer-info">
                                <li>
                                    <i class="icon-mail"></i>
                                    <p>{{$settings->email ?? ''}}</p>
                                </li>
                                <li>
                                    <i class="icon-phone"></i>
                                    <p>{{$settings->phone ?? ''}}</p>
                                </li>
                            </ul>
                            <ul class="tf-social-icon">
                                @if(!empty($settings['social_links']['facebook']))
                                <li>
                                    <a href="{{$settings['social_links']['facebook']}}" target="_blank" rel="noopener" class="social-facebook" aria-label="Facebook">
                                        <i class="icon icon-fb"></i>
                                    </a>
                                </li>
                                @endif
                                @if(!empty($settings['social_links']['instagram']))
                                <li>
                                    <a href="{{$settings['social_links']['instagram']}}" target="_blank" rel="noopener" class="social-facebook" aria-label="Instagram">
                                        <i class="icon icon-instagram"></i>
                                    </a>
                                </li>
                                @endif
                                {{-- Admin's Settings form saves `twitter`; older data may still use `twiter`. Check both. --}}
                                @php $__twitter = $settings['social_links']['twitter'] ?? $settings['social_links']['twiter'] ?? null; @endphp
                                @if(!empty($__twitter))
                                <li>
                                    <a href="{{ $__twitter }}" target="_blank" rel="noopener" class="social-facebook" aria-label="Twitter / X">
                                        <i class="icon icon-x"></i>
                                    </a>
                                </li>
                                @endif
                                @if(!empty($settings['social_links']['linkedin']))
                                <li>
                                    <a href="{{$settings['social_links']['linkedin']}}" target="_blank" rel="noopener" class="social-facebook" aria-label="LinkedIn">
                                        <i class="icon icon-in"></i>
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="footer-menu">
                            <div class="footer-col-block">
                                <div class="footer-heading text-button footer-heading-mobile">
                                    Information
                                </div>
                                <div class="tf-collapse-content">
                                    <ul class="footer-menu-list">
                                        <li class="text-caption-1">
                                            <a href="{{route('client.about')}}" class="footer-menu_item">About Us</a>
                                        </li>
                                        <li class="text-caption-1">
                                            <a href="{{route('client.contact')}}" class="footer-menu_item">Contact Us</a>
                                        </li>
                                        <li class="text-caption-1">
                                            <a href="{{route('client.category')}}" class="footer-menu_item">Shop by Category</a>
                                        </li>
                                        <li class="text-caption-1">
                                            <a href="{{route('client.blog.index')}}" class="footer-menu_item">Blog</a>
                                        </li>
                                        @if (\App\Helper\OrderStatusHelper::publicTrackingVisible($settings ?? null))
                                            <li class="text-caption-1">
                                                <a href="{{route('client.track.form')}}" class="footer-menu_item">Track Your Order</a>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            </div>
                            <div class="footer-col-block">
                                <div class="footer-heading text-button footer-heading-mobile">
                                    Need help?
                                </div>
                                <div class="tf-collapse-content">
                                    <ul class="footer-menu-list">
                                        <li class="text-caption-1">
                                            <a href="{{route('client.contact')}}" class="footer-menu_item">Contact support</a>
                                        </li>
                                        @if (\App\Helper\OrderStatusHelper::publicTrackingVisible($settings ?? null))
                                            <li class="text-caption-1">
                                                <a href="{{route('client.track.form')}}" class="footer-menu_item">Track my order</a>
                                            </li>
                                        @endif
                                        @if (!empty($settings->email))
                                            <li class="text-caption-1">
                                                <a href="mailto:{{ $settings->email }}" class="footer-menu_item">Email us</a>
                                            </li>
                                        @endif
                                        @if (!empty($settings->phone))
                                            <li class="text-caption-1">
                                                <a href="tel:{{ preg_replace('/[^\d+]/', '', $settings->phone) }}" class="footer-menu_item">Call us</a>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="footer-bottom-wrap">
                            <div class="left">
                                <p class="text-caption-1">© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
                                <div class="tf-cur justify-content-end">
                                </div>
                            </div>
                            @php
                                // Driven by which payment gateways the admin has activated
                                // (Razorpay implies Visa/MC/RuPay/Amex/UPI/Net-banking; COD
                                // renders as a text chip). See CommonHelper::activePaymentBadges().
                                $__paymentBadges = $paymentBadges ?? [];
                            @endphp
                            @if (! empty($__paymentBadges))
                            <div class="tf-payment">
                                <p class="text-caption-1">Payment:</p>
                                <ul>
                                    @foreach ($__paymentBadges as $__pm)
                                        <li>
                                            @if (! empty($__pm['image']))
                                                <img src="{{ asset('client/images/payment/' . $__pm['image']) }}"
                                                    alt="{{ $__pm['label'] ?? '' }}">
                                            @elseif (! empty($__pm['chip']))
                                                <span class="pay-chip {{ ! empty($__pm['is_cod']) ? 'pay-chip--cod' : '' }}"
                                                    title="{{ $__pm['label'] ?? '' }}">{{ $__pm['chip'] }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
<!-- /Footer -->
<!-- toolbar-bottom -->
<div class="tf-toolbar-bottom">
    <div class="toolbar-item">
        <a href="{{route('client.shop')}}">
            <div class="toolbar-icon">
                <svg class="icon" width="20" height="20" viewBox="0 0 20 20" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path d="M8.125 3.125H4.375C4.04348 3.125 3.72554 3.2567 3.49112 3.49112C3.2567 3.72554 3.125 4.04348 3.125 4.375V8.125C3.125 8.45652 3.2567 8.77446 3.49112 9.00888C3.72554 9.2433 4.04348 9.375 4.375 9.375H8.125C8.45652 9.375 8.77446 9.2433 9.00888 9.00888C9.2433 8.77446 9.375 8.45652 9.375 8.125V4.375C9.375 4.04348 9.2433 3.72554 9.00888 3.49112C8.77446 3.2567 8.45652 3.125 8.125 3.125ZM8.125 8.125H4.375V4.375H8.125V8.125ZM15.625 3.125H11.875C11.5435 3.125 11.2255 3.2567 10.9911 3.49112C10.7567 3.72554 10.625 4.04348 10.625 4.375V8.125C10.625 8.45652 10.7567 8.77446 10.9911 9.00888C11.2255 9.2433 11.5435 9.375 11.875 9.375H15.625C15.9565 9.375 16.2745 9.2433 16.5089 9.00888C16.7433 8.77446 16.875 8.45652 16.875 8.125V4.375C16.875 4.04348 16.7433 3.72554 16.5089 3.49112C16.2745 3.2567 15.9565 3.125 15.625 3.125ZM15.625 8.125H11.875V4.375H15.625V8.125ZM8.125 10.625H4.375C4.04348 10.625 3.72554 10.7567 3.49112 10.9911C3.2567 11.2255 3.125 11.5435 3.125 11.875V15.625C3.125 15.9565 3.2567 16.2745 3.49112 16.5089C3.72554 16.7433 4.04348 16.875 4.375 16.875H8.125C8.45652 16.875 8.77446 16.7433 9.00888 16.5089C9.2433 16.2745 9.375 15.9565 9.375 15.625V11.875C9.375 11.5435 9.2433 11.2255 9.00888 10.9911C8.77446 10.7567 8.45652 10.625 8.125 10.625ZM8.125 15.625H4.375V11.875H8.125V15.625ZM15.625 10.625H11.875C11.5435 10.625 11.2255 10.7567 10.9911 10.9911C10.7567 11.2255 10.625 11.5435 10.625 11.875V15.625C10.625 15.9565 10.7567 16.2745 10.9911 16.5089C11.2255 16.7433 11.5435 16.875 11.875 16.875H15.625C15.9565 16.875 16.2745 16.7433 16.5089 16.5089C16.7433 16.2745 16.875 15.9565 16.875 15.625V11.875C16.875 11.5435 16.7433 11.2255 16.5089 10.9911C16.2745 10.7567 15.9565 10.625 15.625 10.625ZM15.625 15.625H11.875V11.875H15.625V15.625Z" fill="#4D4E4F" />
                </svg>
            </div>
            <div class="toolbar-label">Shop</div>
        </a>
    </div>
    <div class="toolbar-item">
        <a href="{{route('client.category')}}">
            <div class="toolbar-icon">
                <svg class="icon" width="20" height="20" viewBox="0 0 20 20" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path d="M17.5 10C17.5 10.1658 17.4342 10.3247 17.3169 10.4419C17.1997 10.5592 17.0408 10.625 16.875 10.625H3.125C2.95924 10.625 2.80027 10.5592 2.68306 10.4419C2.56585 10.3247 2.5 10.1658 2.5 10C2.5 9.83424 2.56585 9.67527 2.68306 9.55806C2.80027 9.44085 2.95924 9.375 3.125 9.375H16.875C17.0408 9.375 17.1997 9.44085 17.3169 9.55806C17.4342 9.67527 17.5 9.83424 17.5 10ZM3.125 5.625H16.875C17.0408 5.625 17.1997 5.55915 17.3169 5.44194C17.4342 5.32473 17.5 5.16576 17.5 5C17.5 4.83424 17.4342 4.67527 17.3169 4.55806C17.1997 4.44085 17.0408 4.375 16.875 4.375H3.125C2.95924 4.375 2.80027 4.44085 2.68306 4.55806C2.56585 4.67527 2.5 4.83424 2.5 5C2.5 5.16576 2.56585 5.32473 2.68306 5.44194C2.80027 5.55915 2.95924 5.625 3.125 5.625ZM16.875 14.375H3.125C2.95924 14.375 2.80027 14.4408 2.68306 14.5581C2.56585 14.6753 2.5 14.8342 2.5 15C2.5 15.1658 2.56585 15.3247 2.68306 15.4419C2.80027 15.5592 2.95924 15.625 3.125 15.625H16.875C17.0408 15.625 17.1997 15.5592 17.3169 15.4419C17.4342 15.3247 17.5 15.1658 17.5 15C17.5 14.8342 17.4342 14.6753 17.3169 14.5581C17.1997 14.4408 17.0408 14.375 16.875 14.375Z" fill="#4D4E4F" />
                </svg>
            </div>
            <div class="toolbar-label">Categories</div>
        </a>
    </div>
    <div class="toolbar-item">
        <a href="#search" data-bs-toggle="modal" aria-label="Search">
            <div class="toolbar-icon">
                <svg class="icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M17.9419 17.058L14.0302 13.1471C15.1639 11.7859 15.7293 10.04 15.6086 8.27263C15.488 6.50524 14.6906 4.85241 13.3823 3.65797C12.074 2.46353 10.3557 1.81944 8.58462 1.85969C6.81357 1.89994 5.12622 2.62143 3.87358 3.87407C2.62094 5.12671 1.89945 6.81406 1.8592 8.5851C1.81895 10.3561 2.46304 12.0745 3.65748 13.3828C4.85192 14.691 6.50475 15.4884 8.27214 15.6091C10.0395 15.7298 11.7854 15.1644 13.1466 14.0306L17.0575 17.9424C17.1156 18.0004 17.1845 18.0465 17.2604 18.0779C17.3363 18.1094 17.4176 18.1255 17.4997 18.1255C17.5818 18.1255 17.6631 18.1094 17.739 18.0779C17.8149 18.0465 17.8838 18.0004 17.9419 17.9424C17.9999 17.8843 18.046 17.8154 18.0774 17.7395C18.1089 17.6636 18.125 17.5823 18.125 17.5002C18.125 17.4181 18.1089 17.3367 18.0774 17.2609C18.046 17.185 17.9999 17.1161 17.9419 17.058ZM3.12469 8.75018C3.12469 7.63766 3.45459 6.55012 4.07267 5.6251C4.69076 4.70007 5.56926 3.9791 6.5971 3.55336C7.62493 3.12761 8.75593 3.01622 9.84707 3.23326C10.9382 3.4503 11.9405 3.98603 12.7272 4.7727C13.5138 5.55937 14.0496 6.56165 14.2666 7.6528C14.4837 8.74394 14.3723 9.87494 13.9465 10.9028C13.5208 11.9306 12.7998 12.8091 11.8748 13.4272C10.9497 14.0453 9.86221 14.3752 8.74969 14.3752C7.25836 14.3735 5.82858 13.7804 4.77404 12.7258C3.71951 11.6713 3.12634 10.2415 3.12469 8.75018Z" fill="#4D4E4F" />
                </svg>
            </div>
            <div class="toolbar-label">Search</div>
        </a>
    </div>
    <div class="toolbar-item">
        <a href="{{ route('client.wishlist') }}" aria-label="Wishlist">
            <div class="toolbar-icon">
                <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4D4E4F" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
                @if (($wishlistCount ?? 0) > 0)
                    <div class="toolbar-count count-box wishlist-count">{{ $wishlistCount }}</div>
                @else
                    <div class="toolbar-count count-box wishlist-count" style="display:none">0</div>
                @endif
            </div>
            <div class="toolbar-label">Wishlist</div>
        </a>
    </div>
    <div class="toolbar-item">
        {{-- The badge is decorative duplication for sighted users; excluding it
             from the accessible name leaves the visible label ("Cart") matching
             the announced one, while the count still reaches screen readers via
             aria-label. --}}
        <a class="get-cart-details" data-url="{{route('client.cartList')}}" href="{{ route('client.shoppingcart') }}" aria-label="Cart">
            <div class="toolbar-icon">
                <svg class="icon" width="20" height="20" viewBox="0 0 20 20" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path d="M13.75 8.23389V4.48389C13.75 3.48932 13.3549 2.5355 12.6517 1.83224C11.9484 1.12897 10.9946 0.733887 10 0.733887C9.00544 0.733887 8.05161 1.12897 7.34835 1.83224C6.64509 2.5355 6.25 3.48932 6.25 4.48389V8.23389M3.4375 6.35889H16.5625L17.5 17.6089H2.5L3.4375 6.35889Z" stroke="#4D4E4F" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
               <div class="toolbar-count count-box" aria-hidden="true">{{ $cartCount }}</div>
            </div>
            <div class="toolbar-label">Cart</div>
        </a>
    </div>
    <div class="toolbar-item">
        {{-- The mobile menu no longer carries its own Profile/Login button (it was
             the same door as this one), so on a phone this is the only way in.
             Point guests straight at the login page instead of letting the
             client_auth middleware bounce them there. --}}
        @php $__mbSignedIn = auth('customer')->check(); @endphp
        <a href="{{ $__mbSignedIn ? route('client.profile') : route('client.login') }}" aria-label="{{ $__mbSignedIn ? 'Profile' : 'Sign in' }}">
            <div class="toolbar-icon">
                <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4D4E4F" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            </div>
            <div class="toolbar-label">{{ $__mbSignedIn ? 'Profile' : 'Login' }}</div>
        </a>
    </div>
</div>
<!-- /toolbar-bottom -->