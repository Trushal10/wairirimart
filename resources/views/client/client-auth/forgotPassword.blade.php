@extends('layouts.client')

@section('title')
    Reset your password | {{ config('app.name') }}
@endsection

@section('meta_description')
    Forgotten your password? We'll email you a secure link to set a new one.
@endsection

@section('content')
    <!-- login -->
    <section class="flat-spacing">
        <div class="container">
            <div class="login-wrap">
                <div class="left">
                    <div class="heading">
                        <h4 class="mb_8">Reset your password</h4>
                        <p>We will send you an email to reset your password</p>
                    </div>
                    <form action="{{route('client.forgot-password.send')}}" method="POST" class="form-login">
                        @csrf
                        <div class="wrap">
                            <label for="email" class="visually-hidden">Email address</label>
                            <input type="email" placeholder="Email*" id="email" name="email"
                                autocomplete="email" required value="{{ old('email') }}"
                                aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                            @error ('email')
                                <div class="text-danger">
                                    {{ $message }}
                                </div>
                            @enderror
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

