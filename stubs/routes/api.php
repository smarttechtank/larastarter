<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserAPIController;
use App\Http\Controllers\API\RoleAPIController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\TwoFactorAuthController;

// Guest routes
Route::middleware('guest')->group(function () {
    // Authentication
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('api.login');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('registration.enabled')
        ->name('api.register');

    // Password reset
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('api.password.email');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('api.password.update');

    // Two-factor authentication verification
    Route::post('/two-factor/verify', [TwoFactorAuthController::class, 'verify'])->name('api.two-factor.verify');
});

// Email verification routes
Route::middleware('auth:sanctum')->group(function () {
    // Email verification
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('api.verification.verify');

    // Email verification notification
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware(['throttle:6,1'])
        ->name('api.verification.send');

    // Email change verification
    Route::get('/email-change/verify/{id}/{token}/{email}', [UserAPIController::class, 'verifyEmailChange'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('api.email-change.verify');
});

// Authenticated routes
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // User info
    Route::get('/user', [UserAPIController::class, 'getCurrentUser'])->name('api.user');

    // Logout
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('api.logout');

    // Two-factor authentication management
    Route::get('/two-factor/setup', [TwoFactorAuthController::class, 'setup'])->name('api.two-factor.setup');
    Route::post('/two-factor/enable', [TwoFactorAuthController::class, 'enable'])->name('api.two-factor.enable');
    Route::post('/two-factor/disable', [TwoFactorAuthController::class, 'disable'])->name('api.two-factor.disable');

    // Recovery codes management
    Route::get('/two-factor/recovery-codes', [TwoFactorAuthController::class, 'recoveryCodes'])->name('api.two-factor.recovery-codes');
    Route::post('/two-factor/recovery-codes/regenerate', [TwoFactorAuthController::class, 'regenerateRecoveryCodes'])->name('api.two-factor.recovery-codes.regenerate');

    // User profile
    Route::match(['put', 'patch'], '/users/update-profile', [UserAPIController::class, 'updateProfile'])
        ->name('user.profile.update');

    // User password
    Route::match(['put', 'patch'], '/users/update-password', [UserAPIController::class, 'updatePassword'])
        ->name('user.password.update');

    // User avatar
    Route::match(['put', 'patch'], '/users/upload-avatar', [UserAPIController::class, 'uploadAvatar'])
        ->name('user.avatar.upload');
    Route::delete('/users/delete-avatar', [UserAPIController::class, 'deleteAvatar'])
        ->name('user.avatar.delete');

    // User email change management
    Route::post('/users/email-change/request', [UserAPIController::class, 'requestEmailChange'])
        ->name('api.user.email-change.request');
    Route::post('/users/email-change/resend', [UserAPIController::class, 'resendEmailChangeVerification'])
        ->middleware(['throttle:6,1'])
        ->name('api.user.email-change.resend');
    Route::delete('/users/email-change/cancel', [UserAPIController::class, 'cancelEmailChange'])
        ->name('api.user.email-change.cancel');
    Route::get('/users/email-change/status', [UserAPIController::class, 'getEmailChangeStatus'])
        ->name('api.user.email-change.status');

    // User bulk delete
    Route::delete('/users/bulk-delete', [UserAPIController::class, 'bulkDestroy'])
        ->name('users.bulk-delete');

    // User resend password reset
    Route::post('/users/resend-password-reset', [UserAPIController::class, 'resendPasswordReset'])
        ->name('users.resend-password-reset');

    // User API resource
    Route::apiResource('/users', UserAPIController::class);

    // Role bulk delete
    Route::delete('/roles/bulk-delete', [RoleAPIController::class, 'bulkDestroy'])
        ->name('roles.bulk-delete');

    // Role API resource
    Route::apiResource('/roles', RoleAPIController::class);
});
