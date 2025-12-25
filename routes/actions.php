<?php

use Daugt\Commerce\Controllers\StripeCheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('/stripe-checkout', StripeCheckoutController::class);
