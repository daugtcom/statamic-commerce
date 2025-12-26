<?php

use Illuminate\Support\Facades\Route;

if (Route::hasMacro('stripeWebhooks')) {
    Route::stripeWebhooks('/daugt-commerce/stripe/webhook');
}
