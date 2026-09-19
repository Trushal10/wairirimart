@extends('layouts.client')

@section('title')
    Sign in | {{ config('app.name') }}
@endsection

@section('style')
<style>
    .auth-card { background:#fff; border:1px solid #eee; border-radius:12px; padding:32px 28px; max-width:520px; margin:0 auto; box-shadow:0 6px 24px rgba(0,0,0,0.03) }
    .auth-tabs { display:flex; background:#f5f5f5; border-radius:8px; padding:4px; margin-bottom:22px }
    .auth-tabs button { flex:1; border:0; background:transparent; padding:8px 12px; font-weight:600; font-size:13px; color:#666; border-radius:6px; cursor:pointer; transition:.2s }
    .auth-tabs button.active { background:#fff; color:#222; box-shadow:0 1px 3px rgba(0,0,0,.08) }
    .auth-panel { display:none }
    .auth-panel.active { display:block }
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

    .divider-or { display:flex; align-items:center; text-align:center; gap:12px; margin:22px 0; color:#999; font-size:12px; text-transform:uppercase; letter-spacing:1px }
    .divider-or::before,.divider-or::after { content:''; flex:1; height:1px; background:#eee }
    .google-btn { display:flex; align-items:center; justify-content:center; gap:10px; width:100%; padding:11px 14px; background:#fff; border:1px solid #dadce0; border-radius:8px; font-weight:500; color:#3c4043; text-decoration:none; transition:.2s; cursor:pointer }
    .google-btn:hover { background:#f8f9fa; border-color:#c6c8ca; color:#3c4043 }
    .form-hint { font-size:11px; color:#999; margin-top:-6px; margin-bottom:12px }
    .auth-form input[type="text"],.auth-form input[type="email"],.auth-form input[type="tel"],.auth-form input[type="password"],.auth-form input[type="number"] { width:100%; padding:10px 12px; border:1px solid #ddd; border-radius:6px; font-size:14px; margin-bottom:6px }
    .auth-form input:focus { outline:none; border-color:#222 }
    .switch-mode-link { color:#0d6efd; font-weight:600; cursor:pointer }
    .resend-otp { font-size:12px; color:#666; margin-top:8px; text-align:center }
    .resend-otp button { background:transparent; border:0; color:#0d6efd; font-weight:600; cursor:pointer; padding:0 }
    .resend-otp button:disabled { color:#aaa; cursor:not-allowed }
    .otp-target { font-weight:600; color:#222 }
</style>
@endsection

@php $__phoneOtpLoginEnabled = (bool) config('services.auth.phone_otp_login_enabled', false); @endphp
@section('content')
<section class="flat-spacing">
    <div class="container">
        <div class="auth-card" id="auth-root" data-phone-otp-enabled="{{ $__phoneOtpLoginEnabled ? '1' : '0' }}">

            <div class="auth-tabs" role="tablist" aria-label="Choose sign-in or sign-up">
                <button type="button" data-view="login" class="active">Sign in</button>
                <button type="button" data-view="register">Create account</button>
            </div>

            <a href="{{ route('client.social.redirect', 'google') }}" class="google-btn">
                <svg width="18" height="18" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                    <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.6 33 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.1 8 3l5.7-5.7C34.1 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.2-.1-2.4-.4-3.5z"/>
                    <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.4 19 12 24 12c3.1 0 5.9 1.1 8 3l5.7-5.7C34.1 6.1 29.3 4 24 4 16.3 4 9.7 8.4 6.3 14.7z"/>
                    <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35 26.7 36 24 36c-5.2 0-9.6-3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/>
                    <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.1-4.1 5.6l6.2 5.2C41.4 34.5 44 29.6 44 24c0-1.2-.1-2.4-.4-3.5z"/>
                </svg>
                Continue with Google
            </a>

            <div class="divider-or">or</div>

            {{-- LOGIN view --}}
            <div class="auth-view active" data-view="login" id="view-login">
                @if ($__phoneOtpLoginEnabled)
                <div class="auth-tabs" data-scope="login" role="tablist" aria-label="Login mode">
                    <button type="button" data-mode="email" class="active">Email &amp; password</button>
                    <button type="button" data-mode="phone">Phone OTP</button>
                </div>
                @endif

                {{-- Email + password form --}}
                <form action="{{ route('client.login.user') }}" class="login-box auth-form auth-mode-panel" data-mode="email" method="post">
                    @csrf
                    <input type="email" class="mb-2" name="identifier" placeholder="Email address" autocomplete="email">
                    <div class="text-danger mb-2 error" id="error-identifier"></div>

                    <input type="password" class="mb-2" name="password" placeholder="Password" autocomplete="current-password">
                    <div class="text-danger mb-2 error" id="error-password"></div>

                    <div class="d-flex align-items-center justify-content-between">
                        <a class="text-black fs-6" href="{{ route('client.forgot-password') }}">Forgot password?</a>
                    </div>

                    <button class="tf-btn btn-fill radius-4 login-form-button mt-3 w-100" type="button">
                        <span class="text">Sign in</span>
                    </button>
                </form>

                @if ($__phoneOtpLoginEnabled)
                {{-- Phone OTP: step 1 — enter phone --}}
                <form class="auth-form auth-mode-panel" data-mode="phone" id="phone-login-form" style="display:none">
                    @csrf
                    <input type="tel" class="mb-2" name="phone" placeholder="Phone number" autocomplete="tel" inputmode="numeric" pattern="[0-9]{10,15}">
                    <p class="form-hint">Enter the phone number linked to your account. We'll send a 6-digit code.</p>
                    <div class="text-danger mb-2 error" id="error-phone"></div>

                    <button class="tf-btn btn-fill radius-4 phone-otp-send-btn mt-2 w-100" type="button">
                        <span class="text">Send OTP</span>
                    </button>
                </form>

                {{-- Phone OTP: step 2 — enter code --}}
                <form class="auth-form auth-mode-panel" data-mode="phone-otp" id="phone-otp-form" style="display:none">
                    @csrf
                    <input type="hidden" name="token" value="">
                    <div class="text-center mb-3">
                        <h5>Enter your OTP</h5>
                        <p class="text-secondary" style="font-size:13px;margin:0">Sent to <span class="otp-target" id="phone-otp-target"></span>.</p>
                    </div>
                    <input type="text" class="mb-2" name="otp" placeholder="6-digit code" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{4,8}" maxlength="8">
                    <div class="text-danger mb-2 error" id="error-otp"></div>
                    <button class="tf-btn btn-fill radius-4 phone-otp-verify-btn w-100" type="button">
                        <span class="text">Verify &amp; sign in</span>
                    </button>
                    <div class="resend-otp">
                        Didn't get the code?
                        <button type="button" id="phone-otp-resend" disabled>Resend</button>
                        <span id="phone-otp-timer"></span>
                    </div>
                    <div class="text-center mt-2">
                        <button type="button" class="btn btn-link btn-sm" id="phone-otp-back">Use a different number</button>
                    </div>
                </form>
                @endif

                <p class="text-center mt-3 mb-0" style="font-size:14px;color:#666">
                    New here?
                    <span class="switch-mode-link" data-switch="register">Create an account</span>
                </p>
            </div>

            {{-- REGISTER view --}}
            <div class="auth-view" data-view="register" id="view-register" style="display:none">
                @if ($__phoneOtpLoginEnabled)
                <div class="auth-tabs" data-scope="register" role="tablist" aria-label="Sign-up mode">
                    <button type="button" data-mode="email" class="active">Email</button>
                    <button type="button" data-mode="phone">Phone</button>
                </div>
                @endif

                <form action="{{ route('client.register.user') }}" class="login-box auth-form" method="post">
                    @csrf
                    <input type="hidden" name="mode" value="email">

                    <input type="text" name="name" class="mb-2" placeholder="Your name" autocomplete="name" maxlength="50">
                    <div class="text-danger mb-2 error" id="error-name"></div>

                    <div class="auth-panel active" data-mode="email">
                        <input type="email" name="email" class="mb-2" placeholder="Email address" autocomplete="email">
                        <div class="text-danger mb-2 error" id="error-email"></div>
                    </div>
                    @if ($__phoneOtpLoginEnabled)
                    <div class="auth-panel" data-mode="phone">
                        <input type="tel" name="phone" class="mb-2" placeholder="Phone number" autocomplete="tel" disabled inputmode="numeric" pattern="[0-9]{10,15}">
                        <div class="text-danger mb-2 error" id="error-phone"></div>
                        <p class="form-hint">We'll text a one-time code to confirm this is your number.</p>
                    </div>
                    @endif

                    <input type="password" class="mb-2" name="password" placeholder="Password (min 8 characters)" autocomplete="new-password">
                    <div class="text-danger mb-2 error" id="error-password"></div>

                    <input type="password" class="mb-2" name="password_confirmation" placeholder="Confirm password" autocomplete="new-password">
                    <div class="text-danger mb-2 error" id="error-password_confirmation"></div>

                    <button class="tf-btn btn-fill radius-4 register-form-button mt-2 w-100" type="button">
                        <span class="text">Create account</span>
                    </button>
                </form>

                <p class="text-center mt-3 mb-0" style="font-size:14px;color:#666">
                    Already have an account?
                    <span class="switch-mode-link" data-switch="login">Sign in</span>
                </p>
            </div>

            {{-- Email OTP verify (email-flow post-register) --}}
            <div class="wrap d-none" id="otp-form">
                <div class="text-center mt-3 mb-3">
                    <h5>Enter your OTP</h5>
                    <p class="text-secondary" style="font-size:13px;margin:0">Sent to <span id="otp-target"></span>. Please don't refresh this page.</p>
                </div>
                <form action="{{ route('client.verify-otp') }}" class="login-box auth-form" method="post">
                    @csrf
                    <input type="hidden" name="token" value="">
                    <div class="text-danger mb-2 error" id="error-token"></div>
                    <input type="number" class="mb-2" name="otp" placeholder="6-digit code" autocomplete="one-time-code" maxlength="6">
                    <div class="text-danger mb-2 error" id="error-otp"></div>
                    <button class="tf-btn btn-fill radius-4 otp-form-button w-100" type="button">
                        <span class="text">Verify</span>
                    </button>
                </form>
                <div class="resend-otp">
                    Didn't get the code?
                    <button type="button" id="email-otp-resend" disabled>Resend</button>
                    <span id="email-otp-timer"></span>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script>
    (function () {
        const root = document.getElementById('auth-root');
        if (!root) return;

        // Top-level view switching (Sign in <-> Create account)
        function showView(v) {
            root.querySelectorAll('.auth-view').forEach(el => {
                el.style.display = el.getAttribute('data-view') === v ? '' : 'none';
                el.classList.toggle('active', el.getAttribute('data-view') === v);
            });
            root.querySelectorAll('.auth-tabs[role="tablist"]:not([data-scope]) button').forEach(b => {
                b.classList.toggle('active', b.getAttribute('data-view') === v);
            });
        }
        root.querySelectorAll('.auth-tabs:not([data-scope]) button').forEach(btn => {
            btn.addEventListener('click', () => showView(btn.getAttribute('data-view')));
        });
        root.querySelectorAll('.switch-mode-link').forEach(link => {
            link.addEventListener('click', () => showView(link.getAttribute('data-switch')));
        });

        // Mode switching within a view: for the LOGIN view we swap entire forms.
        const loginView = root.querySelector('.auth-view[data-view="login"]');
        function showLoginMode(mode) {
            // Show the email form OR the phone step-1 form. Reset OTP step.
            loginView.querySelectorAll('.auth-mode-panel').forEach(el => {
                const own = el.getAttribute('data-mode');
                let visible = false;
                if (mode === 'email' && own === 'email') visible = true;
                if (mode === 'phone' && own === 'phone') visible = true;
                el.style.display = visible ? '' : 'none';
            });
            const otpForm = document.getElementById('phone-otp-form');
            if (otpForm) otpForm.style.display = 'none';
        }
        const loginModeTabs = loginView.querySelector('.auth-tabs[data-scope="login"]');
        if (loginModeTabs) {
            loginModeTabs.querySelectorAll('button').forEach(btn => {
                btn.addEventListener('click', () => {
                    loginModeTabs.querySelectorAll('button').forEach(b => b.classList.toggle('active', b === btn));
                    showLoginMode(btn.getAttribute('data-mode'));
                });
            });
        }
        showLoginMode('email');

        // Legacy mode switching for the REGISTER view (unchanged from before)
        root.querySelectorAll('.auth-tabs[data-scope="register"]').forEach(bar => {
            const scope = bar.getAttribute('data-scope');
            const view = root.querySelector(`.auth-view[data-view="${scope}"]`);
            bar.querySelectorAll('button').forEach(btn => {
                btn.addEventListener('click', () => {
                    const mode = btn.getAttribute('data-mode');
                    bar.querySelectorAll('button').forEach(b => b.classList.toggle('active', b === btn));
                    view.querySelectorAll('.auth-panel').forEach(p => {
                        const on = p.getAttribute('data-mode') === mode;
                        p.classList.toggle('active', on);
                        p.querySelectorAll('input').forEach(inp => { inp.disabled = !on; });
                    });
                    const modeInput = view.querySelector('input[name="mode"]');
                    if (modeInput) modeInput.value = mode;
                });
            });
        });

        /* -------------------- Phone OTP flow -------------------- */
        const phoneForm = document.getElementById('phone-login-form');
        const otpForm   = document.getElementById('phone-otp-form');
        const resendBtn = document.getElementById('phone-otp-resend');
        const timerEl   = document.getElementById('phone-otp-timer');
        const backBtn   = document.getElementById('phone-otp-back');
        const csrf      = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        let resendTimer = null;

        function startResendCountdown(seconds = 30) {
            let remaining = seconds;
            resendBtn.disabled = true;
            timerEl.textContent = ' (' + remaining + 's)';
            clearInterval(resendTimer);
            resendTimer = setInterval(() => {
                remaining -= 1;
                if (remaining <= 0) {
                    clearInterval(resendTimer);
                    resendBtn.disabled = false;
                    timerEl.textContent = '';
                    return;
                }
                timerEl.textContent = ' (' + remaining + 's)';
            }, 1000);
        }

        function clearErrors(form) {
            form.querySelectorAll('.error').forEach(el => { el.textContent = ''; });
        }
        function showError(form, key, msg) {
            const el = form.querySelector('#error-' + key);
            if (el) el.textContent = msg;
        }
        function showFlash(type, msg) {
            if (typeof showSweetAlert === 'function') {
                showSweetAlert(type, msg);
            } else {
                alert(msg);
            }
        }

        async function sendPhoneOtp() {
            clearErrors(phoneForm);
            const phone = phoneForm.querySelector('input[name="phone"]').value.trim();
            if (!/^[0-9]{10,15}$/.test(phone)) {
                showError(phoneForm, 'phone', 'Please enter a valid phone number.');
                return;
            }
            const btn = phoneForm.querySelector('.phone-otp-send-btn');
            const original = btn.querySelector('.text').textContent;
            btn.disabled = true;
            btn.querySelector('.text').textContent = 'Sending…';
            try {
                const res = await fetch('{{ route('client.login.phone.send') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ phone }),
                });
                const body = await res.json().catch(() => ({}));
                if (res.status === 403) {
                    showError(phoneForm, 'phone', body.message || 'Blocked.');
                    return;
                }
                if (!res.ok) {
                    showError(phoneForm, 'phone', body.message || 'Could not send OTP.');
                    return;
                }
                // Even if the phone doesn't exist, backend returns success (generic).
                if (!body.token) {
                    showError(phoneForm, 'phone', body.message || 'If an account exists we’ve sent an OTP.');
                    return;
                }
                otpForm.querySelector('input[name="token"]').value = body.token;
                document.getElementById('phone-otp-target').textContent = phone;
                phoneForm.style.display = 'none';
                otpForm.style.display = '';
                otpForm.querySelector('input[name="otp"]').focus();
                startResendCountdown(30);
                showFlash('success', body.message || 'OTP sent.');
            } catch (e) {
                showError(phoneForm, 'phone', 'Network error. Please try again.');
            } finally {
                btn.disabled = false;
                btn.querySelector('.text').textContent = original;
            }
        }

        async function verifyPhoneOtp() {
            clearErrors(otpForm);
            const token = otpForm.querySelector('input[name="token"]').value;
            const otp   = otpForm.querySelector('input[name="otp"]').value.trim();
            if (!/^[0-9]{4,8}$/.test(otp)) {
                showError(otpForm, 'otp', 'Enter the code you received.');
                return;
            }
            const btn = otpForm.querySelector('.phone-otp-verify-btn');
            const original = btn.querySelector('.text').textContent;
            btn.disabled = true;
            btn.querySelector('.text').textContent = 'Verifying…';
            try {
                const res = await fetch('{{ route('client.login.phone.verify') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ token, otp }),
                });
                const body = await res.json().catch(() => ({}));
                if (!res.ok) {
                    showError(otpForm, 'otp', body.message || 'Invalid or expired code.');
                    return;
                }
                showFlash('success', body.message || 'Signed in!');
                setTimeout(() => { window.location.href = '{{ route('client.home') }}'; }, 400);
            } catch (e) {
                showError(otpForm, 'otp', 'Network error. Please try again.');
            } finally {
                btn.disabled = false;
                btn.querySelector('.text').textContent = original;
            }
        }

        phoneForm?.querySelector('.phone-otp-send-btn')?.addEventListener('click', sendPhoneOtp);
        otpForm?.querySelector('.phone-otp-verify-btn')?.addEventListener('click', verifyPhoneOtp);
        resendBtn?.addEventListener('click', sendPhoneOtp);
        backBtn?.addEventListener('click', () => {
            otpForm.style.display = 'none';
            phoneForm.style.display = '';
            phoneForm.querySelector('input[name="phone"]').focus();
        });
        // Submit on Enter within the OTP field
        otpForm?.querySelector('input[name="otp"]')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); verifyPhoneOtp(); }
        });
        phoneForm?.querySelector('input[name="phone"]')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); sendPhoneOtp(); }
        });
    })();

    /* -------------------- Email OTP resend -------------------- */
    (function () {
        const emailOtpForm = document.getElementById('otp-form');
        const emailResendBtn = document.getElementById('email-otp-resend');
        const emailTimerEl = document.getElementById('email-otp-timer');
        if (!emailOtpForm || !emailResendBtn) return;

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        let emailResendTimer = null;

        function startEmailCountdown(seconds = 30) {
            let remaining = seconds;
            emailResendBtn.disabled = true;
            emailTimerEl.textContent = ' (' + remaining + 's)';
            clearInterval(emailResendTimer);
            emailResendTimer = setInterval(() => {
                remaining -= 1;
                if (remaining <= 0) {
                    clearInterval(emailResendTimer);
                    emailResendBtn.disabled = false;
                    emailTimerEl.textContent = '';
                    return;
                }
                emailTimerEl.textContent = ' (' + remaining + 's)';
            }, 1000);
        }

        // Kick off the countdown whenever the OTP form is shown. Watches the
        // `d-none` class since login-js.js toggles it on token receipt.
        const observer = new MutationObserver((mutations) => {
            for (const m of mutations) {
                if (m.attributeName === 'class' && !emailOtpForm.classList.contains('d-none')) {
                    startEmailCountdown(30);
                }
            }
        });
        observer.observe(emailOtpForm, { attributes: true, attributeFilter: ['class'] });

        async function resendEmailOtp() {
            const tokenInput = emailOtpForm.querySelector('input[name="token"]');
            const token = tokenInput?.value?.trim() || '';
            if (!token) {
                if (typeof showSweetAlert === 'function') {
                    showSweetAlert('error', 'Session expired. Please sign in again.');
                }
                return;
            }
            const original = emailResendBtn.textContent;
            emailResendBtn.disabled = true;
            emailResendBtn.textContent = 'Sending…';
            try {
                const res = await fetch('{{ route('client.send-otp') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ token }),
                });
                const body = await res.json().catch(() => ({}));
                if (res.status === 429) {
                    if (typeof showSweetAlert === 'function') {
                        showSweetAlert('info', 'Too many attempts. Please wait a bit before requesting another code.');
                    }
                    return;
                }
                if (!res.ok || body.error) {
                    if (typeof showSweetAlert === 'function') {
                        showSweetAlert('error', body.error || 'Could not resend the code. Please try again.');
                    }
                    return;
                }
                if (typeof showSweetAlert === 'function') {
                    showSweetAlert('success', body.success || 'A new code has been sent.');
                }
                startEmailCountdown(30);
            } catch (e) {
                if (typeof showSweetAlert === 'function') {
                    showSweetAlert('error', 'Network error. Please try again.');
                }
            } finally {
                emailResendBtn.textContent = original;
            }
        }

        emailResendBtn.addEventListener('click', resendEmailOtp);
    })();
</script>
<script type="text/javascript" src="{{ \App\Helper\CommonHelper::assetV('client/js/login-js.js') }}"></script>
@endsection
