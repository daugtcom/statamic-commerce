<?php

use Daugt\Commerce\Controllers\StripeShippingOptionsController;
use Daugt\Commerce\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('daugt-commerce/stripe/webhook', StripeWebhookController::class)
    ->withoutMiddleware([
        'App\Http\Middleware\VerifyCsrfToken',
        'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
    ])
    ->name('daugt-commerce.stripe.webhook');

Route::post('daugt-commerce/stripe/shipping-options', StripeShippingOptionsController::class)
    ->withoutMiddleware([
        'App\Http\Middleware\VerifyCsrfToken',
        'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
    ])
    ->name('daugt-commerce.stripe.shipping-options');
