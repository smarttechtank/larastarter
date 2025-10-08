<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\TwoFactorAuthController;
use App\Http\Controllers\API\UserAPIController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware(['guest', 'registration.enabled'])
    ->name('register');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest')
    ->name('login');

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware('guest')
    ->name('password.email');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('guest')
    ->name('password.store');

Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::get('/email-change/verify/{id}/{token}/{email}', [App\Http\Controllers\API\UserAPIController::class, 'verifyEmailChange'])
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('email-change.verify');

Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.send');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Two-factor authentication routes
Route::post('/two-factor/verify', [TwoFactorAuthController::class, 'verify'])
    ->middleware('guest')
    ->name('two-factor.verify');

Route::get('/two-factor/setup', [TwoFactorAuthController::class, 'setup'])
    ->middleware('auth')
    ->name('two-factor.setup');

Route::post('/two-factor/enable', [TwoFactorAuthController::class, 'enable'])
    ->middleware('auth')
    ->name('two-factor.enable');

Route::post('/two-factor/disable', [TwoFactorAuthController::class, 'disable'])
    ->middleware('auth')
    ->name('two-factor.disable');

Route::get('/two-factor/recovery-codes', [TwoFactorAuthController::class, 'recoveryCodes'])
    ->middleware('auth')
    ->name('two-factor.recovery-codes');

Route::post('/two-factor/recovery-codes/regenerate', [TwoFactorAuthController::class, 'regenerateRecoveryCodes'])
    ->middleware('auth')
    ->name('two-factor.recovery-codes.regenerate');

Route::post('/users/email-change/request', [UserAPIController::class, 'requestEmailChange'])
    ->middleware('auth')
    ->name('user.email-change.request');

Route::post('/users/email-change/resend', [UserAPIController::class, 'resendEmailChangeVerification'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('user.email-change.resend');

Route::delete('/users/email-change/cancel', [UserAPIController::class, 'cancelEmailChange'])
    ->middleware('auth')
    ->name('user.email-change.cancel');

Route::get('/users/email-change/status', [UserAPIController::class, 'getEmailChangeStatus'])
    ->middleware('auth')
    ->name('user.email-change.status');
