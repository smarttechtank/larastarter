<?php

use Illuminate\Http\Request;
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
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('api.register');

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
});

// Authenticated routes
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // User info
    Route::get('/user', function (Request $request) {
        return $request->user();
    })->name('api.user');

    // Logout
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('api.logout');

    // Two-factor authentication toggle
    Route::post('/two-factor/toggle', [TwoFactorAuthController::class, 'toggle'])->name('api.two-factor.toggle');

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

    // User bulk delete
    Route::delete('/users/bulk-delete', [UserAPIController::class, 'bulkDestroy'])
        ->name('users.bulk-delete');

    // User API resource
    Route::apiResource('/users', UserAPIController::class);

    // Role bulk delete
    Route::delete('/roles/bulk-delete', [RoleAPIController::class, 'bulkDestroy'])
        ->name('roles.bulk-delete');

    // Role API resource
    Route::apiResource('/roles', RoleAPIController::class);
});
