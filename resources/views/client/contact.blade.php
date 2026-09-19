@extends('layouts.client')

@section('title')
    Contact Us | {{ config('app.name') }}
@endsection

@section('meta_description')
Have a question, gift idea, or bulk-order request? Talk to the {{ config('app.name') }} team — we usually reply within a few hours on weekdays.
@endsection

@section('content')
    <!-- page-hero -->
    @include('client.partials.page-hero', [
        'title'  => 'Contact Us',
        'crumbs' => [['label' => 'Contact Us']],
    ])
    <!-- /page-hero -->
    {{-- The theme's map block lived here behind `display:none`, which still
         cost every visitor a Google Maps iframe — a third-party connection and
         payload for something nobody could see. Its coordinates also pointed at
         an unrelated company left over from the template. The address in the
         Information panel comes from Settings and is the real one. --}}

    <!-- contact-us -->
    <section class="flat-spacing">
        <div class="container">
            <div class="contact-us-content">
                <div class="left">
                    <h4>Get In Touch</h4>
                    <p class="text-secondary-2">Use the form below to get in touch with the sales team</p>
                    <form id="contactForm" action="{{route('client.contact.save')}}" method="post" class="form-leave-comment">
                        @csrf
                        <input type="hidden" name="recaptcha" id="recaptcha">
                        {{-- Honeypot: hidden from real users, bots often fill it. --}}
                        <input type="text" name="website" id="contact-website" tabindex="-1" autocomplete="off" aria-hidden="true"
                            style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden">
                        @error('recaptcha')
                            <div class="error text-danger">{{ $message }}</div>
                        @enderror
                        <div class="wrap">
                            <div class="cols">
                                <fieldset class="">
                                    <label for="name" class="visually-hidden">Name</label>
                                    <input type="text" placeholder="Your Name*" name="name" id="name" autocomplete="name" required value="{{ old('name') }}">
                                    <div class="text-danger">
                                        @error ('name') {{ $message }} @enderror
                                    </div>
                                </fieldset>
                                <fieldset class="">
                                    <label for="email" class="visually-hidden">Email</label>
                                    <input type="email" placeholder="Your Email*" name="email" id="email" autocomplete="email" required value="{{ old('email') }}">
                                    <div class="text-danger">
                                        @error ('email') {{ $message }} @enderror
                                    </div>
                                </fieldset>
                            </div>
                            <div class="cols">
                                <fieldset class="">
                                    <label for="phone" class="visually-hidden">Phone</label>
                                    <input type="text" placeholder="Your Phone*" name="phone" id="phone" autocomplete="tel" required value="{{ old('phone') }}">
                                    <div class="text-danger">
                                        @error ('phone') {{ $message }} @enderror
                                    </div>
                                </fieldset>
                                <fieldset class="">
                                    <label for="city" class="visually-hidden">City</label>
                                    <input type="text" placeholder="Your City*" name="city" id="city" autocomplete="address-level2" required value="{{ old('city') }}">
                                    <div class="text-danger">
                                        @error ('city') {{ $message }} @enderror
                                    </div>
                                </fieldset>
                            </div>
                            <fieldset class="">
                                <label for="subject" class="visually-hidden">Subject</label>
                                <input type="text" name="subject" id="subject" placeholder="Your Subject*" required value="{{ old('subject') }}">
                                <div class="text-danger">
                                    @error ('subject') {{ $message }} @enderror
                                </div>
                            </fieldset>
                            <fieldset class="">
                                <label for="message" class="visually-hidden">Message</label>
                                <textarea name="message" id="message" rows="4" placeholder="Your Message*" required>{{ old('message') }}</textarea>
                                <div class="text-danger">
                                    @error ('message') {{ $message }} @enderror
                                </div>
                            </fieldset>
                        </div>
                        <div class="button-submit send-wrap">
                            <button class="tf-btn btn-fill radius-4" type="submit">
                                <span class="text text-button">Send message</span>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="right">
                    <h4>Information</h4>
                    <div class="mb_20">
                        <div class="text-title mb_8">Phone:</div>
                        <p class="text-secondary">{{$settings->phone ?? ''}}</p>
                    </div>
                    <div class="mb_20">
                        <div class="text-title mb_8">Email:</div>
                        <p class="text-secondary">{{$settings->email ?? ''}}</p>
                    </div>
                    <div class="mb_20">
                        <div class="text-title mb_8">Address:</div>
                        <p class="text-secondary">{{$settings->address ?? ''}}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- /contact-us -->
@endsection

@section('scripts')
@php $__recaptchaKey = config('services.recaptcha.v3-recaptcha-site-key'); @endphp
@if ($__recaptchaKey)
{{-- reCAPTCHA v3: fetches a FRESH token at submit time (tokens expire after
     2 minutes, so grabbing one on page load is unreliable for slow readers). --}}
<script src="https://www.google.com/recaptcha/api.js?render={{ $__recaptchaKey }}"></script>
<script>
    (function () {
        const form = document.getElementById('contactForm');
        const tokenInput = document.getElementById('recaptcha');
        if (! form || ! tokenInput || typeof grecaptcha === 'undefined') return;

        let submitting = false;

        form.addEventListener('submit', function (e) {
            if (submitting || tokenInput.value) return; // already have a fresh token
            e.preventDefault();
            submitting = true;

            grecaptcha.ready(function () {
                grecaptcha.execute("{{ $__recaptchaKey }}", { action: 'contact' })
                    .then(function (token) {
                        tokenInput.value = token || '';
                        form.submit();
                    })
                    .catch(function () {
                        // Fail-open: submit anyway; server-side rule will
                        // reject if the token is invalid.
                        form.submit();
                    });
            });
        });
    })();
</script>
@endif
@endsection



