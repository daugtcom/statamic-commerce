<?php

return [
    'payment' => [
        'provider' => env('DAUGT_COMMERCE_PAYMENT_PROVIDER', 'stripe'),
        'providers' => [
            'stripe' => [
                'provider' => Daugt\Commerce\Payments\Providers\StripeProvider::class,
                'store' => Daugt\Commerce\Payments\Stores\StripeIdStore::class,
                'extension' => Daugt\Commerce\Payments\Extensions\StripeProviderExtension::class,
                'config' => [
                    'key' => env('STRIPE_KEY'),
                    'secret' => env('STRIPE_SECRET'),
                    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
                ],
            ],
            'dummy' => [
                'provider' => Daugt\Commerce\Payments\Providers\DummyProvider::class,
                'store' => Daugt\Commerce\Payments\Stores\DummyIdStore::class,
                'extension' => Daugt\Commerce\Payments\Extensions\DummyProviderExtension::class,
                'config' => [],
            ],
        ],
    ],
];
