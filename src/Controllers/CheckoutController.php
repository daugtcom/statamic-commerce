<?php

namespace Daugt\Commerce\Controllers;

use Stripe\StripeClient;

class CheckoutController
{
    public function __invoke(StripeClient $stripeClient)
    {
        if (!auth()->check()) {
            return redirect()->route('statamic.cp.login');
        }
        $checkout_session = $stripeClient->checkout->sessions->create([
            'ui_mode' => 'embedded',
            'line_items' => [[
            ]],
            'mode' => 'setup',
            // TODO: REMOVE, THIS IS TEMPORARY
            'redirect_on_completion' => 'never',
            /*'automatic_tax' => [
                'enabled' => true,
            ],*/
            'currency' => 'eur'
        ]);
        return view('statamic-commerce::checkout', ['stripe_key' => config('statamic.daugt-commerce.stripe.key'), 'stripe_client_secret' => $checkout_session->client_secret]);
    }
}
