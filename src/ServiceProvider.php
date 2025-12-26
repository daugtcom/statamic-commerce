<?php

namespace Daugt\Commerce;

use Daugt\Commerce\Carts\Contracts\CartStore;
use Daugt\Commerce\Carts\Stores\SessionCartStore;
use Daugt\Commerce\Payments\PaymentProviderResolver;
use Statamic\Providers\AddonServiceProvider;
use Stripe\StripeClient;

class ServiceProvider extends AddonServiceProvider
{

    protected $vite = [
        'input' => [
          'resources/js/addon.js',
          'resources/css/addon.css',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    public function register() {
        parent::register();

        $this->mergeConfigFrom(
            __DIR__ . '/../config/statamic/daugt-commerce.php',
            'statamic.daugt-commerce'
        );

        $this->app->singleton(PaymentProviderResolver::class, function ($app) {
            return new PaymentProviderResolver($app);
        });

        $this->app->bind(CartStore::class, SessionCartStore::class);

        $this->app->singleton(StripeClient::class, function () {
            $secret = config('statamic.daugt-commerce.payment.providers.stripe.config.secret');

            if (! $secret) {
                throw new \RuntimeException('Missing Stripe secret. Set STRIPE_SECRET in your .env.');
            }

            return new StripeClient($secret);
        });
    }

    public function boot() {
        parent::boot();
    }

    public function bootAddon()
    {
        parent::bootAddon();
        $this->loadJsonTranslationsFrom(__DIR__ . '/../lang');
        $this->publishes([
            __DIR__ . '/../config/statamic/daugt-commerce.php' => config_path('statamic/daugt-commerce.php'),
        ], 'daugt-commerce-config');
    }
}
