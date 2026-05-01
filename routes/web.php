<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use XetaSuite\Http\Controllers\Api\V1\AppConfigController;
use XetaSuite\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use XetaSuite\Http\Controllers\Api\V1\Auth\SetupPasswordController;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'version' => '1.0.0',
    ]);
});

Route::prefix('api/v1')->middleware(['web'])->group(function () {
    // Public route to get app configuration
    Route::get('/app/config', AppConfigController::class);

    Route::prefix('auth')->group(function () {
        // Auth
        Route::post('/login', [AuthenticatedSessionController::class, 'store']);
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

        // Password reset (with reCAPTCHA protection + rate limiting)
        Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
            ->middleware(['recaptcha:forgot_password', 'throttle:password-reset']);
        Route::post('/reset-password', [NewPasswordController::class, 'store'])
            ->middleware('throttle:password-reset');

        // Password setup (for new users)
        Route::get('/setup-password/{id}/{hash}', [SetupPasswordController::class, 'verify'])
            ->name('auth.password.setup');
        Route::post('/setup-password/{id}/{hash}', [SetupPasswordController::class, 'store'])
            ->name('auth.password.setup.store');
        Route::post('/setup-password-resend', [SetupPasswordController::class, 'resend'])
            ->middleware(['recaptcha:resend_setup_password', 'throttle:password-reset'])
            ->name('auth.password.setup.resend');
    });
});
