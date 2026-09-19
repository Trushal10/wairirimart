<?php

use App\Http\Controllers\Client\ForgotPasswordController;
use App\Http\Controllers\Client\LoginUserController;
use App\Http\Controllers\Client\OtpController;
use App\Http\Controllers\Client\RegisterUserController;
use App\Http\Controllers\Client\SocialAuthController;
use Illuminate\Support\Facades\Route;

Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->whereIn('provider', ['google'])
    ->name('client.social.redirect');
Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->whereIn('provider', ['google'])
    ->name('client.social.callback');

Route::get('register', [RegisterUserController::class, 'index'])->name('client.register');
Route::post('register', [RegisterUserController::class, 'save'])
    ->middleware('throttle:5,1')
    ->name('client.register.user');
Route::get('verification', [RegisterUserController::class, 'verify'])->name('client.verification');
Route::post('verify-otp', [RegisterUserController::class, 'verifyOtp'])
    ->middleware('throttle:5,1')
    ->name('client.verify-otp');
Route::get('login', [LoginUserController::class, 'index'])->name('client.login');
Route::post('login', [LoginUserController::class, 'loginUser'])
    ->middleware('throttle:5,1')
    ->name('client.login.user');
Route::post('login/phone/send-otp', [LoginUserController::class, 'phoneOtpSend'])
    ->middleware('throttle:3,1')
    ->name('client.login.phone.send');
Route::post('login/phone/verify', [LoginUserController::class, 'phoneOtpVerify'])
    ->middleware('throttle:5,1')
    ->name('client.login.phone.verify');
Route::get('forgot-password', [ForgotPasswordController::class, 'index'])->name('client.forgot-password');
// Distinct from the GET route's name: sharing one name works at runtime but
// route:cache refuses to serialise duplicates, which breaks deploys.
Route::post('forgot-password', [ForgotPasswordController::class, 'forgotPassword'])
    ->middleware('throttle:5,1')
    ->name('client.forgot-password.send');
Route::get('reset-password', [ForgotPasswordController::class, 'resetPasswordForm'])->name('client.reset-password-form');
Route::put('reset-password', [ForgotPasswordController::class, 'resetPassword'])
    ->middleware('throttle:5,1')
    ->name('client.reset-pasword');
Route::post('send-otp', [OtpController::class, 'index'])
    ->middleware('throttle:3,1')
    ->name('client.send-otp');

Route::middleware('client_auth')->group(function () {
    Route::delete('logout', [LoginUserController::class, 'logout'])->name('client.logout.user');
});
