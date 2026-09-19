@extends('layouts.client')

@section('title')
    Reset Password | {{ config('app.name') }}
@endsection

@section('meta_description')
    Enter the code we emailed you and choose a new {{ config('app.name') }} password.
@endsection

@section('structured_data')
    <meta name="robots" content="noindex, nofollow">
@endsection

@section('content')
    <!-- page-hero -->
    @include('client.partials.page-hero', [
        'title'  => 'Reset your password',
        'crumbs' => [
            ['label' => 'Sign in', 'url' => route('client.login')],
            ['label' => 'Reset password'],
        ],
    ])
    <!-- /page-hero -->

    <!-- login -->
    <section class="flat-spacing">
        <div class="container">
            <div class="login-wrap">
                <div class="left">
                    <div class="heading">
                        <h4 class="mb_8">Choose a new password</h4>
                        <p>Enter the code we just emailed you, then pick a new password.</p>
                    </div>
                    <form action="{{route('client.reset-pasword')}}" method="Post" class="form-login form-has-password">
                        @csrf
                        @method('PUT')
                        <input type="hidden" id="token" name="token" value="{{ $token }}">
                        <div class="wrap">
                            <fieldset class="">
                                <label for="otp" class="visually-hidden">One-time code</label>
                                <input type="text" placeholder="OTP*" id="otp" name="otp"
                                    inputmode="numeric" autocomplete="one-time-code" maxlength="6" required
                                    value="{{ old('otp') }}">
                            </fieldset>
                            @error ('otp')
                                <div class="text-danger">
                                    {{ $message }}
                                </div>
                            @enderror
                            <fieldset class="position-relative password-item">
                                <label for="password" class="visually-hidden">New password</label>
                                <input class="input-password" type="password" placeholder="Password*" name="password" id="password"
                                    autocomplete="new-password" required>
                                <span class="toggle-password unshow">
                                    <i class="icon-eye-hide-line"></i>
                                </span>
                            </fieldset>
                            @error ('password')
                                <div class="text-danger">
                                    {{ $message }}
                                </div>
                            @enderror
                            <fieldset class="position-relative password-item">
                                <label for="password_confirmation" class="visually-hidden">Confirm new password</label>
                                <input class="input-password" type="password" placeholder="Confirm Password*" id="password_confirmation" name="password_confirmation"
                                    autocomplete="new-password" required>
                                <span class="toggle-password unshow">
                                    <i class="icon-eye-hide-line"></i>
                                </span>
                            </fieldset>
                            @error ('password_confirmation')
                                <div class="text-danger">
                                    {{ $message }}
                                </div>
                            @enderror
                            <div class="d-flex justify-content-between">
                                <div class="d-flex">
                                    OTP is Expired after:- 
                                    <div id="timer-display"></div>
                                </div>
                                <button type="button" id="send-otp" class="float-end btn-link"
                                    style="background:none;border:0;padding:0;text-decoration:underline;cursor:pointer">
                                    <span class="text text-button">Send New OTP</span>
                                </button>
                            </div>
                        </div>
                        <div class="button-submit">
                            <button class="tf-btn btn-fill radius-4" type="submit">
                                <span class="text text-button">Submit</span>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="right">
                    <h4 class="mb_8">Already have an account?</h4>
                    <p class="text-secondary">Welcome back. Sign in to access your personalized experience, saved preferences, and more. We're thrilled to have you with us again!</p>
                    <a href="{{route('client.login')}}" class="tf-btn btn-fill radius-4"><span class="text text-button">Login</span></a>
                </div>
            </div>
        </div>
    </section>
    <!-- /login -->
@endsection

@section('scripts')
    <script>
        $(document).ready(function () {
            timer();
            
            $('#send-otp').on('click', function() {
                let url = '{{route("client.send-otp")}}';

                let data = {
                    'token': '{{request('token')}}',
                }
                $.ajax({
                    url: url,
                    type: 'post',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: data,
                    success: function(response) {
                        console.log(response);
                        if(response.success){
                            showSweetAlert('success', response.success);
                        }
                        timer();
                    },
                    error: function(error) {
                        if(error.responseJSON.error){
                            showSweetAlert('error', error.responseJSON.error);
                            return false;
                        }
                        console.log(error);
                    }
                });
            })
        })

        function timer() {
            let time = 300; // 300sec | 5 minutes
            const $display = $('#timer-display')
            const $button = $('#send-otp').hide();

            const updateDisplay = () => {
                const minutes = Math.floor(time / 60), seconds = time % 60;
                $display.text(`${minutes}:${seconds.toString().padStart(2, '0')}`);
            };

            updateDisplay();

            const timer = setInterval(() => {
                if (--time <= 0) {
                    clearInterval(timer);
                    $button.show().prop('disabled', false);
                }
                updateDisplay();
            }, 1000);
        }
    </script>
@endsection