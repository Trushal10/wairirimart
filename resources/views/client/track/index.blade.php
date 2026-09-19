@extends('layouts.client')

@php use App\Helper\CommonHelper; @endphp

@section('title', 'Track Your Order | ' . config('app.name'))
@section('meta_description', 'Track the status of your order and shipment in real time.')

@section('style')
<style>{!! CommonHelper::inlineCss(['client/css/order-ui.css']) !!}</style>
<style>
    .tf-page { background: var(--ou-ground); }
    .tf-wrap { max-width: 520px; margin: 0 auto; padding: 0 16px; }
    .tf-lead { font-size: 14px; color: var(--ou-body); line-height: 1.6; margin: 0 0 22px; }

    .tf-field { margin-bottom: 16px; }
    .tf-label {
        display: block; font-size: 13px; font-weight: 600;
        color: var(--ou-ink); margin-bottom: 6px;
    }
    .tf-hint { display: block; font-size: 12px; font-weight: 500; color: var(--ou-soft); margin-top: 5px; }
    /* The theme's global input rules are broad; scope ours to this form so
       both fields render the same regardless of which one wins. */
    .tf-wrap input.tf-control {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #dcdcdc;
        border-radius: 10px;
        background: #fff;
        font-size: 14px;
        color: var(--ou-ink);
        line-height: 1.4;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .tf-wrap input.tf-control::placeholder { color: #b3b6ba; }
    .tf-wrap input.tf-control:focus {
        outline: none;
        border-color: var(--ou-ink);
        box-shadow: 0 0 0 3px rgba(20, 22, 26, .08);
    }
    .tf-wrap input.tf-control[aria-invalid="true"] { border-color: var(--ou-bad-fg); }

    .tf-submit { width: 100%; margin-top: 4px; }
    .tf-alt {
        text-align: center; font-size: 13px; color: var(--ou-soft);
        margin: 20px 0 0; padding-top: 18px; border-top: 1px solid var(--ou-line-soft);
    }
    .tf-alt a { color: var(--ou-ink); font-weight: 600; text-decoration: underline; text-underline-offset: 3px; }
</style>
@endsection

@section('content')
    @include('client.partials.page-hero', [
        'title'  => 'Track Your Order',
        'crumbs' => [['label' => 'Track order']],
    ])

    <section class="flat-spacing tf-page">
        <div class="tf-wrap">
            <div class="ou-card">
                <h2 class="ou-card-title">Where's my order?</h2>
                <p class="tf-lead">
                    Enter your order number and the email you used at checkout. You'll see the
                    courier, the current status and the full delivery timeline.
                </p>

                {{-- The old page rendered a bootstrap .alert here. Errors now use
                     the same tone vocabulary as the rest of the order pages, and
                     the fields themselves are marked invalid so the message is
                     not the only signal. --}}
                @if ($errors->any())
                    <div class="ou-note ou-note-bad" style="margin-bottom:18px" role="alert">
                        <svg class="ou-note-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16h.01"/>
                        </svg>
                        <div>
                            @foreach ($errors->all() as $e)
                                <div>{{ $e }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form method="post" action="{{ route('client.track.verify') }}" novalidate>
                    @csrf

                    <div class="tf-field">
                        <label for="order_no" class="tf-label">Order number</label>
                        <input id="order_no" name="order_no" type="text" required maxlength="100"
                            value="{{ old('order_no') }}" placeholder="e.g. 3008202615120270"
                            autocomplete="off" inputmode="numeric"
                            class="tf-control"
                            aria-invalid="{{ $errors->has('order_no') ? 'true' : 'false' }}"
                            aria-describedby="order_no_hint">
                        <span class="tf-hint" id="order_no_hint">It's at the top of your order confirmation email.</span>
                    </div>

                    <div class="tf-field">
                        <label for="email" class="tf-label">Email used at checkout</label>
                        <input id="email" name="email" type="email" required maxlength="150"
                            value="{{ old('email') }}" placeholder="you@example.com"
                            autocomplete="email"
                            class="tf-control"
                            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                    </div>

                    <button type="submit" class="ou-btn ou-btn-primary tf-submit">Track order</button>
                </form>

                <p class="tf-alt">
                    @auth('customer')
                        All your orders are in <a href="{{ route('client.profile') }}?type=orders">your account</a>.
                    @else
                        Have an account? <a href="{{ route('client.login') }}">Sign in</a> to see every order without typing anything.
                    @endauth
                </p>
            </div>
        </div>
    </section>
@endsection
