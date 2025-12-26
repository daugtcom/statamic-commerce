<?php

namespace Daugt\Commerce\Controllers;

use Stripe\StripeClient;

class StripeCheckoutController
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
        $key = config('statamic.daugt-commerce.payment.providers.stripe.config.key');

        return view('statamic-commerce::stripe-checkout', [
            'stripe_key' => $key,
            'stripe_client_secret' => $checkout_session->client_secret,
        ]);
    }
}
