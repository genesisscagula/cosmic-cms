<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    // Registration is intentionally not rate-limited through Laravel's cache-backed
    // limiter. Database/file cache entries can survive logout and silently return 429
    // before the controller redirects the newly-created account to onboarding/pending.
    // Validation, unique email/website constraints, CSRF, and the pending-payment state
    // still protect this flow.
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:cosmic-login');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:cosmic-password-reset')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('onboarding/pending', [RegisteredUserController::class, 'pending'])
        ->name('onboarding.pending');

    Route::get('onboarding/success', [RegisteredUserController::class, 'success'])
        ->name('onboarding.success');

    Route::post('onboarding/recover', [RegisteredUserController::class, 'recoverProvisioning'])
        ->middleware('throttle:3,1')
        ->name('onboarding.recover');

    // Do not rate-limit the onboarding checkout route. The previous database-backed
    // throttle persisted attempts across logout/new registrations and could block the next
    // customer with a silent 429 response before PayPal was called. Frontend duplicate-submit
    // protection and payment idempotency still protect this endpoint.
    Route::post('onboarding/checkout', [PaymentController::class, 'onboardingCheckout'])
        ->name('onboarding.checkout');

    Route::get('payments/success', [PaymentController::class, 'success'])
        ->name('payments.success');

    Route::get('payments/cancel', [PaymentController::class, 'cancel'])
        ->name('payments.cancel');

    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
