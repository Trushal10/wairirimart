@extends('layouts.client')

@section('title')
    Checkout | {{ config('app.name') }}
@endsection

@section('meta_description')
Complete your order at {{ config('app.name') }}. Fast delivery across India, secure checkout with UPI, cards, wallets and Cash on Delivery, and easy 14-day returns on unused items.
@endsection

@section('style')
<style>
    /* -------- Stepper -------- */
    .co-stepper { display:flex; gap:8px; margin-bottom:24px; align-items:center; justify-content:center; flex-wrap:wrap; }
    .co-step { display:flex; align-items:center; gap:10px; padding:10px 16px; border-radius:999px; background:#f5f5f5; color:#666; font-size:13px; font-weight:600; transition:.25s; white-space:nowrap; }
    .co-step .num { width:24px; height:24px; border-radius:50%; background:#dcdcdc; color:#fff; display:flex; align-items:center; justify-content:center; font-size:12px; flex:0 0 24px; }
    .co-step.active { background:#2A282B; color:#fff; }
    .co-step.active .num { background:#fff; color:#111; }
    .co-step.done { background:#e6f4ea; color:#177a3b; }
    .co-step.done .num { background:#177a3b; color:#fff; }
    .co-step .num svg { width:12px; height:12px; }
    .co-step-sep { flex:0 0 22px; height:1px; background:#dcdcdc; }
    @media (max-width:576px){
        .co-stepper { gap:6px; margin-bottom:16px; }
        .co-step { padding:6px 10px; font-size:11px; gap:6px; }
        .co-step .num { width:18px; height:18px; flex-basis:18px; font-size:10px; }
        .co-step-sep { display:none; }
    }

    /* -------- Panels -------- */
    .co-panel { display:none; background:#fff; border:1px solid #eee; border-radius:12px; padding:24px; margin-bottom:20px; }
    .co-panel.active { display:block; animation:fade .25s ease; }
    .co-panel-title { font-size:18px; font-weight:600; margin:0 0 4px 0; color:#111; }
    .co-panel-sub { font-size:13px; color:#888; margin-bottom:18px; }
    @media (max-width:576px){
        .co-panel { padding:16px; margin-bottom:14px; border-radius:10px; }
        .co-panel-title { font-size:16px; }
        .co-panel-sub { font-size:12px; margin-bottom:14px; }
    }
    @keyframes fade { from{opacity:.4;transform:translateY(4px)} to{opacity:1;transform:none} }

    /* -------- Address & payment cards -------- */
    .addr-card { border:1px solid #eee; border-radius:10px; padding:16px; margin-bottom:10px; cursor:pointer; transition:.15s; position:relative; }
    .addr-card:hover { border-color:#bbb; }
    .addr-card.selected { border-color:#2A282B; background:#fafafa; }
    .addr-card .badge-default { position:absolute; top:12px; right:12px; font-size:10px; text-transform:uppercase; letter-spacing:.5px; background:#eee; color:#666; border-radius:999px; padding:2px 10px; }
    .addr-card input[type="radio"] { accent-color:#111; }
    .addr-title { font-weight:600; color:#111; margin-bottom:2px; }
    .addr-lines { color:#666; font-size:13px; line-height:1.5; }
    .addr-actions { display:flex; gap:12px; margin-top:8px; font-size:12px; }
    .addr-actions a { color:#0d6efd; cursor:pointer; }
    .add-new-addr { border:1.5px dashed #ccc; border-radius:10px; padding:16px; margin-bottom:10px; text-align:center; cursor:pointer; color:#111; font-weight:600; }
    .add-new-addr:hover { border-color:#2A282B; background:#fafafa; }

    /* Empty-address hint shown when the user has no saved addresses; the
       form auto-opens beneath it so they can start typing straight away. */
    .addr-empty-hint {
        background:#f9fafb; border:1px solid #eef0f3; border-radius:10px;
        padding:14px 16px; margin-bottom:12px; font-size:13px; color:#475467;
        display:flex; align-items:flex-start; gap:10px;
    }
    .addr-empty-hint .icon {
        flex:0 0 20px; width:20px; height:20px; color:#0d6efd; margin-top:2px;
    }
    .addr-empty-hint strong { color:#101828; display:block; margin-bottom:2px; font-weight:600; }

    /* Address form actions row — stacks safely on mobile */
    .addr-form-actions { display:flex; gap:10px; margin-top:6px; flex-wrap:wrap; }
    .addr-form-actions .tf-btn { flex:1 1 auto; min-width:0; }
    @media (max-width:576px){
        .addr-form-actions { flex-direction:column-reverse; }
        .addr-form-actions .tf-btn { width:100%; white-space:normal; }
        .addr-card { padding:12px 14px; }
        .add-new-addr { padding:12px; font-size:13px; }
        .addr-title { font-size:14px; }
        .addr-lines { font-size:12px; }
        /* Stack the 6-col address fields at slightly larger widths so the
           form doesn't feel cramped on phones. */
        #address-form .col-md-6 { margin-bottom:12px !important; }
    }

    .pay-card { border:1.5px solid #eee; border-radius:10px; padding:14px 16px; margin-bottom:10px; cursor:pointer; transition:.15s; display:flex; gap:14px; align-items:center; }
    .pay-card:hover { border-color:#bbb; }
    .pay-card.selected { border-color:#2A282B; background:#fafafa; }
    .pay-icon { width:40px; height:40px; border-radius:8px; background:#f5f5f5; display:flex; align-items:center; justify-content:center; font-size:18px; flex:0 0 40px; }
    .pay-label { flex:1; }
    .pay-title { font-weight:600; color:#111; font-size:14px; }
    .pay-sub { font-size:12px; color:#888; }

    /* -------- T&C agree row --------
       The old inline `display:flex` on the <label> made every text chunk
       between the <a> tags a separate flex item, so on mobile the sentence
       broke into 5 side-by-side columns. Wrap the sentence in a single
       <span> so it flows normally, and keep the checkbox aligned. */
    .agree-tos-row { margin: 16px 0; }
    .agree-tos-label {
        display: flex; align-items: flex-start; gap: 10px;
        font-size: 13px; color: #555; line-height: 1.5;
        cursor: pointer; margin: 0;
    }
    .agree-tos-label input[type="checkbox"] {
        flex: 0 0 auto; width: 16px; height: 16px;
        margin: 3px 0 0; accent-color: #2A282B;
        cursor: pointer;
    }
    .agree-tos-text { flex: 1; min-width: 0; }
    .agree-tos-text a { color: #2A282B; font-weight: 600; text-decoration: underline; text-underline-offset: 2px; }
    .agree-tos-text a:hover { color: #0d6efd; }

    /* -------- Social sign-in --------
       Deliberately NOT the theme's .tf-btn: that class carries a skewY(9.3deg)
       ::after wipe at z-index 1, and it only lifts `.text`/`.icon` children
       above it. A Google button is a brand mark plus a bare label, so both got
       painted over by the copper sweep and the button rendered as a solid
       diagonal block. These are the same tokens the login page uses, so the
       two Google buttons are identical wherever a customer meets them. */
    .google-btn { display:flex; align-items:center; justify-content:center; gap:10px; width:100%; padding:11px 14px; background:#fff; border:1px solid #dadce0; border-radius:8px; font-weight:500; color:#3c4043; text-decoration:none; transition:.2s; cursor:pointer; }
    .google-btn:hover { background:#f8f9fa; border-color:#c6c8ca; color:#3c4043; }
    .google-btn:focus-visible { outline:none; box-shadow:0 0 0 3px rgba(66,133,244,.25); }
    .divider-or { display:flex; align-items:center; text-align:center; gap:12px; margin:22px 0; color:#999; font-size:12px; text-transform:uppercase; letter-spacing:1px; }
    .divider-or::before, .divider-or::after { content:''; flex:1; height:1px; background:#eee; }

    /* -------- Form-level error --------
       login-js.js prepends .auth-form-error to a form when the server rejects
       a sign-in: 401 bad credentials, 403 blocked, 429 throttled. It replaces
       a SweetAlert modal, so it has to carry the weight of one on its own —
       hence the tinted panel and the marker, rather than a line of red text
       that reads as a field hint. */
    .auth-form-error {
        display:flex; align-items:flex-start; gap:9px;
        padding:11px 13px; margin:0 0 16px;
        background:#fef3f2; border:1px solid #fecdca; border-left:3px solid #f04438;
        border-radius:8px;
        color:#b42318; font-size:13px; font-weight:500; line-height:1.45;
    }
    .auth-form-error:empty { display:none; }
    .auth-form-error::before {
        content:'!';
        flex:0 0 16px; width:16px; height:16px; margin-top:1px;
        background:#f04438; color:#fff; border-radius:50%;
        font-size:11px; font-weight:700; line-height:16px; text-align:center;
    }

    /* -------- Nav buttons -------- */
    .co-nav { display:flex; justify-content:space-between; gap:10px; margin-top:8px; flex-wrap:wrap; }
    /* Neutralise the global `button { ... }` rule (transitions/padding/flex/space-between/capitalise/pill radius)
       so our checkout buttons render as their own token set. */
    .co-btn {
        display:inline-flex; align-items:center; justify-content:center; gap:6px;
        padding:12px 22px;
        border-radius:10px;
        border:1px solid transparent;
        font-weight:600; font-size:14px; line-height:1.4;
        text-transform:none; letter-spacing:.01em;
        cursor:pointer;
        white-space:nowrap;
        transition:background .15s ease, color .15s ease, box-shadow .15s ease, transform .15s ease;
    }
    .co-btn:hover { transform:translateY(-1px); }
    .co-btn:active { transform:translateY(0); }
    .co-btn:focus-visible { outline:none; box-shadow:0 0 0 4px rgba(17,17,17,0.15); }
    .co-btn-primary,
    .co-btn-primary:hover,
    .co-btn-primary:focus,
    .co-btn-primary:active { background:#2A282B; color:#fff; border-color:#2A282B; }
    .co-btn-primary:hover { background:#1f1f1f; box-shadow:0 8px 18px rgba(17,17,17,.18); }
    .co-btn-primary:disabled,
    .co-btn-primary[disabled] { opacity:.6; cursor:not-allowed; background:#2A282B; color:#fff; transform:none; box-shadow:none; }
    .co-btn-ghost,
    .co-btn-ghost:hover,
    .co-btn-ghost:focus,
    .co-btn-ghost:active { background:transparent; color:#475467; border-color:transparent; }
    .co-btn-ghost:hover { color:#111; background:#f2f4f7; transform:translateY(-1px); }
    @media (max-width:576px){
        .co-nav { gap:8px; }
        .co-btn { padding:11px 16px; font-size:13px; flex:1 1 auto; text-align:center; min-width:0; }
        .co-nav .co-btn-primary { flex:2 1 auto; }
    }

    /* -------- Order summary -------- */
    .co-summary { position:sticky; top:20px; background:#fafafa; border:1px solid #eee; border-radius:14px; padding:22px; }
    .co-summary h5 { font-size:16px; margin:0 0 18px; font-weight:700; color:#111; letter-spacing:-0.01em; }
    .co-summary-item { display:flex; gap:14px; padding:14px 0; border-bottom:1px solid #ececec; align-items:flex-start; }
    .co-summary-item:first-of-type { padding-top:0; }
    .co-summary-item:last-of-type { border-bottom:0; padding-bottom:2px; }
    /* Image wrapper stays visible so the qty badge can peek outside;
       radius + clipping move onto the <img> itself. */
    .co-summary-img {
        width:60px; height:60px; flex:0 0 60px;
        position:relative; overflow:visible;
    }
    .co-summary-img img {
        width:100%; height:100%; object-fit:cover;
        border-radius:10px; background:#eee;
        display:block;
    }
    .co-summary-img .qty {
        position:absolute; top:-8px; right:-8px;
        background:#2A282B; color:#fff;
        font-size:11px; font-weight:700; line-height:1;
        min-width:22px; height:22px; padding:0 7px;
        border-radius:999px;
        display:inline-flex; align-items:center; justify-content:center;
        border:2px solid #fff;
        box-shadow:0 2px 6px rgba(0,0,0,.15);
        z-index:2;
    }
    .co-summary-info { flex:1; min-width:0; font-size:13px; display:flex; flex-direction:column; gap:6px; }
    .co-summary-info .name-row { display:flex; justify-content:space-between; gap:10px; align-items:flex-start; }
    .co-summary-info .name { font-weight:600; color:#111; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; line-height:1.35; font-size:14px; }
    .co-summary-info .price { color:#111; font-weight:700; font-size:14px; white-space:nowrap; letter-spacing:-0.01em; }
    .co-summary-info .variant-chips { display:flex; flex-wrap:wrap; gap:4px; margin:0; padding:0; list-style:none; }
    .co-summary-info .variant-chips .chip {
        display:inline-flex; align-items:baseline; gap:3px;
        padding:2px 8px; border-radius:999px;
        background:#eef0f3; color:#344054;
        font-size:11px; font-weight:500; line-height:1.4;
    }
    .co-summary-info .variant-chips .chip .k { color:#98a2b3; font-weight:500; text-transform:capitalize; }
    .co-summary-info .variant-chips .chip .v { color:#111; font-weight:600; }
    .co-summary-info .qty-line { color:#98a2b3; font-size:12px; font-weight:500; }
    .co-summary-info .qty-line .qty-multi { color:#667085; }
    .co-summary-totals { padding-top:8px; }
    .co-summary-totals .co-line { display:flex; justify-content:space-between; align-items:baseline; color:#555; font-size:13px; margin-bottom:8px; padding:0; border:0; }
    .co-summary-totals .co-line .val { font-weight:500; color:#111; }
    .co-summary-totals .grand { font-size:16px; font-weight:700; color:#111; padding-top:12px; border-top:1px solid #ddd; margin-top:10px; margin-bottom:0; }
    .co-summary-totals .grand .val { font-weight:700; color:#111; }

    @media (max-width:991.98px){
        /* Below the desktop breakpoint, the summary lives BELOW the flow —
           un-stick it so it doesn't fight the natural scroll. */
        .co-summary { position:static; margin-top:20px; }
    }
    @media (max-width:576px){
        .co-summary { padding:16px; border-radius:10px; }
        .co-summary-img { width:48px; height:48px; flex-basis:48px; }
    }

    .co-review { background:#fafafa; border-radius:10px; padding:14px; margin-bottom:14px; font-size:13px; }
    .co-review .k { color:#888; font-size:11px; text-transform:uppercase; letter-spacing:.5px; margin-bottom:4px; }
    .co-review .v { color:#111; font-weight:500; }
    .co-review-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; }
    .co-review-edit { font-size:12px; color:#0d6efd; cursor:pointer; }

    /* -------- Coupon -------- */
    .co-coupon { border-top:1px dashed #e5e5e5; padding-top:12px; margin-bottom:12px; }
    .coupon-input-row { display:flex; gap:8px; align-items:center; }
    .co-coupon-applied { display:flex; justify-content:space-between; align-items:center; background:#e8f5e9; border:1px solid #c8e6c9; border-radius:8px; padding:8px 12px; font-size:13px; }
    .coupon-tag { color:#1b5e20; font-size:13px; }
    .coupon-remove-btn { background:none; border:none; color:#c62828; font-size:12px; cursor:pointer; text-decoration:underline; padding:0; }
</style>
@endsection

@section('content')
    @include('client.partials.page-hero', [
        'title'  => 'Checkout',
        'crumbs' => [
            ['label' => 'Cart', 'url' => route('client.shoppingcart')],
            ['label' => 'Checkout'],
        ],
    ])

    <section class="flat-spacing">
        <div class="container">
            @php
                use App\Models\CustomerAddress;
                $isGuest = ! Auth::guard('customer')->check();
                $user = ! $isGuest ? Auth::guard('customer')->user() : null;

                $subTotal = 0;
                foreach (($cart ?? []) as $c) {
                    $subTotal += (float) $c['price'] * (int) $c['quantity'];
                }
                $shipping = 0;
                $grand = $subTotal + $shipping;
            @endphp

            <div class="row" id="co-root" data-is-guest="{{ $isGuest ? '1' : '0' }}">
                <div class="col-xl-7 col-lg-7">

                    {{-- Stepper --}}
                    <div class="co-stepper">
                        @if ($isGuest)
                        <div class="co-step active" data-step="signin"><span class="num">0</span> Sign in</div>
                        <div class="co-step-sep"></div>
                        @endif
                        <div class="co-step {{ $isGuest ? '' : 'active' }}" data-step="cart"><span class="num">1</span> Cart</div>
                        <div class="co-step-sep"></div>
                        <div class="co-step" data-step="address"><span class="num">2</span> Address</div>
                        <div class="co-step-sep"></div>
                        <div class="co-step" data-step="payment"><span class="num">3</span> Payment</div>
                    </div>

                    {{-- STEP 0 — Guest login/register --}}
                    @if ($isGuest)
                    <div class="co-panel active" data-panel="signin">
                        <h4 class="co-panel-title">Sign in to continue</h4>
                        <p class="co-panel-sub">Log in with your account or continue with Google — your order and delivery address will be saved to your profile.</p>

                        <a href="{{ route('client.social.redirect', 'google') }}" class="google-btn">
                            <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.6 33 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.1 8 3l5.7-5.7C34.1 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.2-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.4 19 12 24 12c3.1 0 5.9 1.1 8 3l5.7-5.7C34.1 6.1 29.3 4 24 4 16.3 4 9.7 8.4 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35 26.7 36 24 36c-5.2 0-9.6-3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.1-4.1 5.6l6.2 5.2C41.4 34.5 44 29.6 44 24c0-1.2-.1-2.4-.4-3.5z"/></svg>
                            Continue with Google
                        </a>

                        <div class="divider-or">or</div>

                        <form action="{{ route('client.login.user') }}" class="login-box" method="post" id="co-login-form">
                            <input type="text" name="identifier" class="mb-2" placeholder="Email or phone" autocomplete="username">
                            <div class="text-danger mb-2 error" id="error-identifier"></div>

                            <input type="password" class="mb-2" name="password" placeholder="Password" autocomplete="current-password">
                            <div class="text-danger mb-2 error" id="error-password"></div>

                            <div class="co-nav">
                                <a href="{{ route('client.login') }}" class="co-btn co-btn-ghost">Create account →</a>
                                <button type="button" class="tf-btn btn-fill radius-4 login-form-button"><span class="text">Sign in &amp; continue</span></button>
                            </div>
                        </form>

                        {{-- OTP verify --}}
                        <div class="d-none mt-3" id="otp-form">
                            <div class="co-panel-sub">Enter the 6-digit code we sent you.</div>
                            <form action="{{ route('client.verify-otp') }}" method="post">
                                <input type="hidden" name="token" value="">
                                <input type="number" class="mb-2" name="otp" placeholder="OTP" autocomplete="one-time-code" maxlength="6">
                                <div class="text-danger mb-2 error" id="error-otp"></div>
                                <button type="button" class="tf-btn btn-fill radius-4 w-100 otp-form-button"><span class="text">Verify &amp; continue</span></button>
                            </form>
                        </div>
                    </div>
                    @endif

                    {{-- STEP 1 — Cart --}}
                    <div class="co-panel {{ $isGuest ? '' : 'active' }}" data-panel="cart">
                        <h4 class="co-panel-title">Review your cart</h4>
                        <p class="co-panel-sub">Check quantities before you continue. You can still edit the cart if needed.</p>

                        @if (! empty($cart))
                            @foreach ($cart as $product)
                                @php
                                    $variantBits = [];
                                    if (! empty($product['options']) && is_array($product['options'])) {
                                        foreach ($product['options'] as $k => $v) { $variantBits[] = $k . ': ' . $v; }
                                    } else {
                                        if (! empty($product['color'])) { $variantBits[] = $product['color']; }
                                        if (! empty($product['size']))  { $variantBits[] = $product['size']; }
                                    }
                                @endphp
                                <div class="d-flex align-items-center gap-3 py-3 border-bottom">
                                    <img src="{{ asset('storage/product/' . $product['image']) }}"
                                        alt="{{ $product['name'] }}"
                                        onerror="this.src='{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}'"
                                        style="width:64px;height:64px;object-fit:cover;border-radius:8px;flex:0 0 64px">
                                    <div class="flex-grow-1 min-w-0">
                                        <div style="font-weight:600;color:#111;line-height:1.35">{{ $product['name'] }}</div>
                                        <div style="font-size:12px;color:#888;line-height:1.5">
                                            @if (! empty($variantBits)) {{ implode(' · ', $variantBits) }} · @endif
                                            Qty {{ $product['quantity'] }}
                                        </div>
                                    </div>
                                    <div style="font-weight:700;color:#111;white-space:nowrap">₹{{ number_format($product['price'] * $product['quantity'], 2) }}</div>
                                </div>
                            @endforeach
                        @else
                            <p class="text-secondary">Your cart is empty. <a href="{{ route('client.shop') }}">Continue shopping →</a></p>
                        @endif

                        <div class="co-nav mt-4">
                            <a href="{{ route('client.shoppingcart') }}" class="tf-btn btn-white has-border radius-4"><span class="text">← Edit cart</span></a>
                            <button type="button" class="tf-btn btn-fill radius-4" data-next="address" {{ empty($cart) ? "disabled" : "" }}>
                                <span class="text">Continue to address →</span>
                            </button>
                        </div>
                    </div>

                    {{-- STEP 2 — Address (only for logged-in) --}}
                    <div class="co-panel" data-panel="address">
                        <h4 class="co-panel-title">Where should we ship this?</h4>
                        <p class="co-panel-sub">Pick a saved address or add a new one.</p>

                        @if (! $isGuest)
                            @php $__hasAddresses = ! empty($addresses); @endphp
                            <div id="address-list">
                                @if (! $__hasAddresses)
                                    <div class="addr-empty-hint">
                                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                                        <div>
                                            <strong>Let's add your first delivery address</strong>
                                            Fill in the form below so we know where to ship your order. We'll save it to your account for faster checkout next time.
                                        </div>
                                    </div>
                                @endif

                                @foreach (($addresses ?? []) as $index => $address)
                                    <label class="addr-card {{ $index === 0 ? 'selected' : '' }}" data-address-id="{{ $address['id'] }}">
                                        <input type="radio" name="address" value="{{ $address['id'] }}" {{ $index === 0 ? 'checked' : '' }} class="me-2">
                                        <div class="addr-title">{{ $address['name'] }} · <small class="text-secondary">{{ ucfirst($address['type'] ?? 'Home') }}</small></div>
                                        <div class="addr-lines">
                                            {{ $address['address'] }}<br>
                                            {{ $address['city'] }}, {{ $address['state'] }} — {{ $address['pincode'] }}<br>
                                            {{ $address['phone'] }} · {{ $address['email'] }}
                                        </div>
                                        <div class="addr-actions">
                                            <a class="edit-address" data-url="{{ route('client.userAddress.edit', ['id' => $address['id']]) }}">Edit</a>
                                        </div>
                                    </label>
                                @endforeach

                                @if ($__hasAddresses)
                                    <div class="add-new-addr" id="show-new-addr">+ Add a new address</div>
                                @endif
                            </div>

                            <div class="collapse {{ $__hasAddresses ? 'mt-3' : 'show' }}" id="address-form" data-auto-open="{{ $__hasAddresses ? '0' : '1' }}">
                                <form class="info-box" action="{{ route('client.userAddress.save') }}" method="POST" onsubmit="return false;">
                                    @csrf
                                    <input type="hidden" name="id" value="">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <input type="text" name="name" placeholder="Full name*" required autocomplete="name" maxlength="50">
                                            <div class="text-danger small error" id="error-name"></div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <input type="email" name="email" placeholder="Email*" required autocomplete="email" maxlength="100">
                                            <div class="text-danger small error" id="error-email"></div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <input type="tel" name="phone" placeholder="Phone*" required autocomplete="tel" pattern="[0-9]{10,15}" inputmode="numeric" maxlength="15">
                                            <div class="text-danger small error" id="error-phone"></div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <input type="text" name="city" placeholder="City*" required autocomplete="address-level2" maxlength="50">
                                            <div class="text-danger small error" id="error-city"></div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <input type="text" name="pincode" placeholder="Pincode*" required autocomplete="postal-code" pattern="[0-9]{4,10}" inputmode="numeric" maxlength="10">
                                            <div class="text-danger small error" id="error-pincode"></div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <input type="text" name="state" placeholder="State*" required autocomplete="address-level1" maxlength="50">
                                            <div class="text-danger small error" id="error-state"></div>
                                        </div>
                                        <div class="col-12 mb-3">
                                            <textarea name="address" placeholder="Complete address*" required maxlength="255" rows="2"></textarea>
                                            <div class="text-danger small error" id="error-address"></div>
                                        </div>
                                        <div class="col-12 mb-2" style="font-size:13px">
                                            Address type:
                                            @foreach (CustomerAddress::TYPES as $key => $type)
                                                <input type="radio" name="type" id="{{ $type }}" value="{{ $type }}" {{ $loop->first ? 'checked' : '' }} class="ms-2 me-1">
                                                <label for="{{ $type }}">{{ $key }}</label>
                                            @endforeach
                                            <div class="text-danger small error" id="error-type"></div>
                                        </div>
                                    </div>
                                    <div class="addr-form-actions">
                                        @if ($__hasAddresses)
                                            <button type="button" class="tf-btn btn-white has-border radius-4" id="cancel-addr"><span class="text">Cancel</span></button>
                                        @endif
                                        <button class="tf-btn btn-fill radius-4 address-form-button" type="submit"><span class="text">Save address &amp; continue</span></button>
                                    </div>
                                </form>
                            </div>
                        @endif

                        <div class="co-nav mt-4">
                            <button type="button" class="tf-btn btn-white has-border radius-4" data-prev="cart"><span class="text">← Back to cart</span></button>
                            <button type="button" class="tf-btn btn-fill radius-4" data-next="payment"><span class="text">Continue to payment →</span></button>
                        </div>
                    </div>

                    {{-- STEP 3 — Payment --}}
                    <div class="co-panel" data-panel="payment">
                        <h4 class="co-panel-title">Payment method</h4>
                        <p class="co-panel-sub">Pick how you'd like to pay. You'll be able to review before placing the order.</p>

                        <div class="co-review">
                            <div class="co-review-header">
                                <div class="k">Delivering to</div>
                                <span class="co-review-edit" data-prev="address">Change</span>
                            </div>
                            <div class="v" id="review-address">—</div>
                        </div>

                        <form class="form-payment">
                            <label class="pay-card selected" data-method="cod">
                                <div class="pay-icon">💵</div>
                                <div class="pay-label">
                                    <div class="pay-title">Cash on Delivery</div>
                                    <div class="pay-sub">Pay in cash when your order arrives.</div>
                                </div>
                                <input type="radio" name="payment-method" value="cod" checked>
                            </label>

                            <label class="pay-card" data-method="razorpay">
                                <div class="pay-icon">💳</div>
                                <div class="pay-label">
                                    <div class="pay-title">UPI / Cards / Netbanking (Razorpay)</div>
                                    <div class="pay-sub">Pay online — secure & instant.</div>
                                </div>
                                <input type="radio" name="payment-method" value="razorpay">
                            </label>

                            <div class="agree-tos-row">
                                <label class="agree-tos-label" for="agree-tos">
                                    <input type="checkbox" id="agree-tos" required>
                                    <span class="agree-tos-text">
                                        I agree to the <a href="{{ route('client.contact') }}">terms &amp; conditions</a> and the <a href="{{ route('client.contact') }}">return policy</a>.
                                    </span>
                                </label>
                            </div>

                            <div class="co-nav">
                                <button type="button" class="tf-btn btn-white has-border radius-4" data-prev="address"><span class="text">← Back</span></button>
                                <button type="button" class="tf-btn btn-fill radius-4 w-100 payment-form-button" data-url="{{ route('client.order.save') }}">
                                    <span class="text">Place order · ₹{{ number_format($grand, 2) }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Sticky order summary --}}
                <div class="col-xl-5 col-lg-5">
                    <div class="co-summary">
                        <h5>Order summary</h5>
                        @if (! empty($cart))
                            @foreach ($cart as $product)
                                @php
                                    // Variant options snapshot (dict) takes precedence over legacy size/color strings.
                                    // Rendered as key/value chips so "test: 1 kg" reads as "Test: 1 kg".
                                    $variantPairs = [];
                                    if (! empty($product['options']) && is_array($product['options'])) {
                                        foreach ($product['options'] as $k => $v) {
                                            $variantPairs[] = ['k' => ucfirst((string) $k), 'v' => (string) $v];
                                        }
                                    } else {
                                        if (! empty($product['color'])) { $variantPairs[] = ['k' => 'Color', 'v' => $product['color']]; }
                                        if (! empty($product['size']))  { $variantPairs[] = ['k' => 'Size',  'v' => $product['size']]; }
                                    }
                                @endphp
                                <div class="co-summary-item">
                                    <div class="co-summary-img">
                                        <img src="{{ asset('storage/product/' . $product['image']) }}"
                                            alt="{{ $product['name'] }}"
                                            onerror="this.src='{{ \App\Helper\CommonHelper::assetV('client/images/home/product-placeholder.webp') }}'">
                                        <span class="qty">{{ $product['quantity'] }}</span>
                                    </div>
                                    <div class="co-summary-info">
                                        <div class="name-row">
                                            <div class="name">{{ $product['name'] }}</div>
                                            <div class="price">₹{{ number_format($product['price'] * $product['quantity'], 2) }}</div>
                                        </div>
                                        @if (! empty($variantPairs))
                                            <ul class="variant-chips">
                                                @foreach ($variantPairs as $pair)
                                                    <li class="chip"><span class="k">{{ $pair['k'] }}:</span> <span class="v">{{ $pair['v'] }}</span></li>
                                                @endforeach
                                            </ul>
                                        @endif
                                        <div class="qty-line">
                                            Qty {{ $product['quantity'] }} <span class="qty-multi">×</span> ₹{{ number_format((float) $product['price'], 2) }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <p class="text-secondary">No items.</p>
                        @endif

                        {{-- Coupon input --}}
                        @php $appliedCoupon = session('coupon'); @endphp
                        <div class="co-coupon mt-3" id="coupon-box">
                            @if ($appliedCoupon)
                                <div class="co-coupon-applied" id="coupon-applied-row">
                                    <span class="coupon-tag">
                                        🏷️ <strong>{{ $appliedCoupon['code'] }}</strong>
                                        — Save ₹{{ number_format($appliedCoupon['discount'], 2) }}
                                    </span>
                                    <button type="button" id="remove-coupon-btn" class="coupon-remove-btn">Remove</button>
                                </div>
                            @else
                                <div class="coupon-input-row" id="coupon-input-row">
                                    <input type="text" id="coupon-code-input" placeholder="Promo / coupon code" maxlength="50" style="flex:1;padding:9px 12px;border:1px solid #ddd;border-radius:8px;font-size:13px;outline:none">
                                    <button type="button" id="apply-coupon-btn" class="tf-btn btn-fill radius-4" style="padding:9px 16px;font-size:13px"><span class="text">Apply</span></button>
                                </div>
                                <div id="coupon-msg" style="font-size:12px;margin-top:6px"></div>
                            @endif
                        </div>

                        <div class="co-summary-totals">
                            <div class="co-line"><span>Subtotal</span><span class="val subtotal-display">₹{{ number_format($subTotal, 2) }}</span></div>
                            @if ($appliedCoupon)
                                <div class="co-line" id="discount-line" style="color:#177a3b">
                                    <span>Discount ({{ $appliedCoupon['code'] }})</span>
                                    <span class="val">-₹<span id="discount-value">{{ number_format($appliedCoupon['discount'], 2) }}</span></span>
                                </div>
                            @else
                                <div class="co-line d-none" id="discount-line" style="color:#177a3b">
                                    <span>Discount (<span id="discount-label"></span>)</span>
                                    <span class="val">-₹<span id="discount-value">0.00</span></span>
                                </div>
                            @endif
                            <div class="co-line"><span>Shipping</span><span class="val">{{ $shipping > 0 ? '₹' . number_format($shipping, 2) : 'Free' }}</span></div>
                            <div class="co-line grand"><span>Total</span><span class="val total-price-checkout">₹{{ number_format($grand - ($appliedCoupon['discount'] ?? 0), 2) }}</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
{{-- Razorpay's SDK is loaded here rather than in the layout: checkout is the
     only page that constructs it, and it is only needed once the shopper
     submits, so it does not need to block anything. --}}
<script src="https://checkout.razorpay.com/v1/checkout.js" defer></script>
<script src="{{ \App\Helper\CommonHelper::assetV('client/js/login-js.js') }}"></script>
<script>
    (function () {
        const root = document.getElementById('co-root');
        if (!root) return;
        const isGuest = root.getAttribute('data-is-guest') === '1';

        // ---- step navigation ----
        function go(step) {
            root.querySelectorAll('.co-panel').forEach(p => {
                p.classList.toggle('active', p.getAttribute('data-panel') === step);
            });
            const order = isGuest ? ['signin','cart','address','payment'] : ['cart','address','payment'];
            const idx = order.indexOf(step);
            root.querySelectorAll('.co-step').forEach(el => {
                const s = el.getAttribute('data-step');
                const i = order.indexOf(s);
                el.classList.toggle('active', s === step);
                el.classList.toggle('done', i > -1 && i < idx);
                const num = el.querySelector('.num');
                if (i > -1 && i < idx) {
                    num.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
                } else {
                    num.textContent = (isGuest ? i : i + 1) + '';
                }
            });
            window.scrollTo({ top: 0, behavior: 'smooth' });

            // Fill review summary when landing on payment
            if (step === 'payment') fillReview();

            // When entering the address step with no saved addresses, open the
            // form automatically so the user goes straight to typing.
            if (step === 'address') {
                const af = document.getElementById('address-form');
                if (af && af.getAttribute('data-auto-open') === '1') {
                    af.classList.add('show');
                    const firstInput = af.querySelector('input[name="name"]');
                    if (firstInput) {
                        setTimeout(() => { try { firstInput.focus({ preventScroll: true }); } catch (e) {} }, 350);
                    }
                }
            }
        }

        // Honour ?step=... so post-save reloads land back on the address step.
        (function pickInitialStep() {
            const params = new URLSearchParams(window.location.search);
            const requested = params.get('step');
            const allowed = isGuest ? ['signin','cart','address','payment'] : ['cart','address','payment'];
            if (requested && allowed.includes(requested)) {
                go(requested);
            } else if (!isGuest) {
                // Non-guest with no addresses saved yet? Skip past Cart into Address so the form opens.
                const list = document.getElementById('address-list');
                const af = document.getElementById('address-form');
                // Only auto-skip if the URL wasn't explicit and the cart isn't empty.
                if (af && af.getAttribute('data-auto-open') === '1' && list && !list.querySelector('.addr-card')) {
                    // Leave the user on Cart by default — they should confirm cart first — but arm auto-open for when they hit Continue.
                }
            }
        })();

        root.querySelectorAll('[data-next]').forEach(btn => btn.addEventListener('click', () => {
            const target = btn.getAttribute('data-next');
            if (target === 'payment' && !validateAddress()) {
                if (typeof showSweetAlert === 'function') showSweetAlert('error', 'Please select or add a delivery address.');
                return;
            }
            go(target);
        }));
        root.querySelectorAll('[data-prev]').forEach(btn => btn.addEventListener('click', () => {
            go(btn.getAttribute('data-prev'));
        }));

        // ---- address card selection ----
        root.querySelectorAll('#address-list .addr-card').forEach(card => {
            card.addEventListener('click', () => {
                root.querySelectorAll('#address-list .addr-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                const r = card.querySelector('input[type="radio"]');
                if (r) r.checked = true;
            });
        });

        const showNewAddr = document.getElementById('show-new-addr');
        const addressForm = document.getElementById('address-form');
        if (showNewAddr && addressForm) {
            showNewAddr.addEventListener('click', () => {
                // Clear the form for a fresh address
                const f = addressForm.querySelector('form');
                if (f) {
                    f.reset();
                    f.querySelector('input[name="id"]').value = '';
                    f.setAttribute('action', '{{ route('client.userAddress.save') }}');
                }
                addressForm.classList.add('show');
                addressForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        }
        const cancelAddr = document.getElementById('cancel-addr');
        if (cancelAddr && addressForm) {
            cancelAddr.addEventListener('click', () => addressForm.classList.remove('show'));
        }

        // ---- payment card selection ----
        root.querySelectorAll('.pay-card').forEach(card => {
            card.addEventListener('click', () => {
                root.querySelectorAll('.pay-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
            });
        });

        // ---- validate + review helpers ----
        function validateAddress() {
            if (isGuest) return false;
            const checked = root.querySelector('input[name="address"]:checked');
            return checked && checked.value;
        }

        function fillReview() {
            const checked = root.querySelector('input[name="address"]:checked');
            if (!checked) {
                document.getElementById('review-address').textContent = 'No address selected';
                return;
            }
            const card = checked.closest('.addr-card');
            const title = card?.querySelector('.addr-title')?.innerText || '';
            const lines = card?.querySelector('.addr-lines')?.innerText || '';
            document.getElementById('review-address').innerHTML = title + '<br>' + lines.replace(/\n/g, '<br>');
        }

        // ---- Guarded checkout button — checkbox check ----
        const payBtn = root.querySelector('.payment-form-button');
        const originalClick = payBtn?.onclick;
        if (payBtn) {
            payBtn.addEventListener('click', (e) => {
                const cb = document.getElementById('agree-tos');
                if (cb && ! cb.checked) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    if (typeof showSweetAlert === 'function') {
                        showSweetAlert('error', 'Please agree to the terms & conditions.');
                    } else {
                        alert('Please agree to the terms & conditions.');
                    }
                    return false;
                }
            }, true);
        }
    })();
</script>

<script>
    // ---- Existing checkout behaviors (address save, edit, order place) ----
    let saveAddressUrl = '{{ route('client.userAddress.save') }}';
    $(document).ready(function () {
        // Save / update address
        $(document).on('click', '.address-form-button', function (e) {
            e.preventDefault();
            let $btn = $(this);
            let form = $btn.closest('form');
            let action = form.attr('action');
            let method = 'put';
            let id = form.find('input[name="id"]').val();
            if ((id == '') || (id == null)) {
                action = saveAddressUrl;
                method = 'post';
            }
            $(document).find('.error').html('');
            $btn.prop('disabled', true).find('.text').text('Saving…');
            $.ajax({
                url: action, type: method,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: form.serialize(), dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        // Reload back on the Address step so the user sees their
                        // just-saved address selected and can continue to payment.
                        const url = new URL(window.location.href);
                        url.searchParams.set('step', 'address');
                        window.location.href = url.toString();
                        return;
                    }
                    if (response.error) showSweetAlert('error', response.message);
                    $btn.prop('disabled', false).find('.text').text('Save address & continue');
                },
                error: function (xhr) {
                    $btn.prop('disabled', false).find('.text').text('Save address & continue');
                    if (xhr.status == 422) {
                        let errors = xhr.responseJSON.errors;
                        Object.keys(errors).forEach(key => { form.find(`#error-${key}`).text(errors[key]); });
                    }
                }
            });
        });

        // Edit address — populate the form and expand
        $(document).on('click', '.edit-address', function (e) {
            e.preventDefault();
            let action = $(this).data('url');
            $.ajax({
                url: action, type: 'get',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function (response) {
                    if (response.success) {
                        const a = response.address;
                        let updateUrl = `{{ route('client.userAddress.update', ['id' => 'id']) }}`.replace('id', a['id']);
                        const form = $('#address-form').find('form');
                        form.attr('action', updateUrl)
                            .find('input[name="id"]').val(a['id']).end()
                            .find('input[name="name"]').val(a['name']).end()
                            .find('input[name="email"]').val(a['email']).end()
                            .find('input[name="phone"]').val(a['phone']).end()
                            .find('input[name="city"]').val(a['city']).end()
                            .find('input[name="pincode"]').val(a['pincode']).end()
                            .find('input[name="state"]').val(a['state']).end()
                            .find('textarea[name="address"]').val(a['address']).end()
                            .find(`input[name="type"][value="${a['type']}"]`).prop('checked', true).end();
                        $('#address-form').collapse('show');
                        $('#address-form')[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            });
        });

        // ---- Coupon ----
        let appliedDiscount = {{ ($appliedCoupon['discount'] ?? 0) }};
        const baseTotal = {{ $grand }};

        function refreshTotal() {
            const grand = Math.max(0, baseTotal - appliedDiscount);
            document.querySelectorAll('.total-price-checkout').forEach(el => {
                el.textContent = '₹' + grand.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            });
        }

        $('#apply-coupon-btn').on('click', function () {
            const code = $('#coupon-code-input').val().trim();
            if (!code) return;
            const $btn = $(this);
            $btn.prop('disabled', true).text('Applying…');
            $('#coupon-msg').text('').css('color', '');
            $.ajax({
                url: '{{ route('client.coupon.apply') }}', type: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { code },
                success: function (res) {
                    $btn.prop('disabled', false).text('Apply');
                    if (res.success) {
                        appliedDiscount = res.discount;
                        $('#discount-label').text(res.code);
                        $('#discount-value').text(res.discount.toFixed(2));
                        $('#discount-line').removeClass('d-none');
                        $('#coupon-input-row').html(
                            '<div class="co-coupon-applied">' +
                            '<span class="coupon-tag">🏷️ <strong>' + res.code + '</strong> — Save ₹' + res.discount.toFixed(2) + '</span>' +
                            '<button type="button" id="remove-coupon-btn" class="coupon-remove-btn">Remove</button>' +
                            '</div>'
                        );
                        refreshTotal();
                        $('#coupon-msg').text(res.message).css('color', '#177a3b');
                    } else {
                        $('#coupon-msg').text(res.message).css('color', '#c00');
                    }
                },
                error: function (xhr) {
                    $btn.prop('disabled', false).text('Apply');
                    const msg = xhr.responseJSON?.message || 'Invalid coupon code.';
                    $('#coupon-msg').text(msg).css('color', '#c00');
                }
            });
        });

        $(document).on('click', '#remove-coupon-btn', function () {
            $.ajax({
                url: '{{ route('client.coupon.remove') }}', type: 'DELETE',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function () {
                    appliedDiscount = 0;
                    $('#discount-line').addClass('d-none');
                    refreshTotal();
                    $('#coupon-input-row, .co-coupon-applied').parent().html(
                        '<div class="coupon-input-row" id="coupon-input-row">' +
                        '<input type="text" id="coupon-code-input" placeholder="Promo / coupon code" maxlength="50" style="flex:1;padding:9px 12px;border:1px solid #ddd;border-radius:8px;font-size:13px;outline:none">' +
                        '<button type="button" id="apply-coupon-btn" class="tf-btn btn-fill radius-4" style="padding:9px 16px;font-size:13px"><span class="text">Apply</span></button>' +
                        '</div>' +
                        '<div id="coupon-msg" style="font-size:12px;margin-top:6px"></div>'
                    );
                }
            });
        });

        // Place order
        $(document).on('click', '.payment-form-button', function (e) {
            e.preventDefault();
            const button = $(this);
            const url = button.data('url');
            const paymentMethod = $('input[name="payment-method"]:checked').val();
            const addressId = $('input[name="address"]:checked').val();
            const agreeTos = $('#agree-tos').is(':checked');
            if (!paymentMethod) {
                showSweetAlert('error', 'Please select a payment method.');
                return;
            }
            if (!addressId) {
                showSweetAlert('error', 'Please select or add a delivery address.');
                return;
            }
            if (!agreeTos) {
                showSweetAlert('error', 'Please agree to the terms and conditions before placing the order.');
                return;
            }
            button.prop('disabled', true).text('Processing…');
            $.ajax({
                url: url, type: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { payment_method: paymentMethod, address_id: addressId, agree_tos: 1 },
                success: function (response) {
                    button.prop('disabled', false).html('Place order · <span class="total-price-checkout"></span>');
                    if (! response || ! response.payment_method) {
                        showSweetAlert('error', 'Invalid payment response!');
                        return;
                    }
                    if (response.payment_method === 'razorpay') {
                        const options = {
                            key: response.razorpay_key,
                            amount: response.amount,
                            currency: response.currency,
                            name: '{{ config('app.name') }}',
                            description: 'Order #' + response.order_id,
                            order_id: response.razorpay_order_id,
                            handler: function (razorResponse) {
                                $.ajax({
                                    url: response.callback_url, type: 'POST',
                                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                                    data: {
                                        order_id: response.order_id,
                                        razorpay_payment_id: razorResponse.razorpay_payment_id,
                                        razorpay_order_id: razorResponse.razorpay_order_id,
                                        razorpay_signature: razorResponse.razorpay_signature
                                    },
                                    success: function (verifyRes) {
                                        if (verifyRes.success) {
                                            showSweetAlert('success', 'Payment successful!');
                                            const orderNo = response.order_no || response.receipt || '';
                                            const redirectUrl = orderNo
                                                ? "{{ url('/order/confirmation') }}/" + encodeURIComponent(orderNo)
                                                : "{{ route('client.profile') }}";
                                            setTimeout(() => { window.location.href = redirectUrl; }, 1200);
                                        } else {
                                            showSweetAlert('error', verifyRes.message || 'Payment verification failed!');
                                        }
                                    },
                                    error: function () {
                                        showSweetAlert('error', 'Payment verification failed. Please contact support with your order id.');
                                    }
                                });
                            },
                            modal: { ondismiss: function () { button.prop('disabled', false); } },
                            theme: { color: '#111' }
                        };
                        const rzp = new Razorpay(options);
                        rzp.open();
                    } else {
                        showSweetAlert('success', response.message || 'Order placed successfully!');
                        setTimeout(() => { window.location.href = response.redirectUrl || '/'; }, 1500);
                    }
                },
                error: function (xhr) {
                    button.prop('disabled', false).text('Place order');
                    let message = 'Something went wrong!';
                    if (xhr.responseJSON && xhr.responseJSON.message) message = xhr.responseJSON.message;
                    showSweetAlert('error', message);
                }
            });
        });
    });
</script>
@endsection
