<?php

namespace Daugt\Commerce\Payments;

use Daugt\Commerce\Payments\Contracts\PaymentIdStore;
use Daugt\Commerce\Payments\Contracts\PaymentProvider;
use Daugt\Commerce\Support\AddonSettings;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

class PaymentProviderResolver
{
    public function __construct(private Container $container)
    {
    }

    public function provider(?string $handle = null): PaymentProvider
    {
        return $this->resolve('provider', $handle);
    }

    public function store(?string $handle = null): PaymentIdStore
    {
        return $this->resolve('store', $handle);
    }

    public function currency(): string
    {
        $currency = AddonSettings::firstValue('currency') ?: 'EUR';

        return strtolower($currency);
    }

    public function providerHandle(): string
    {
        $provider = AddonSettings::firstValue('provider');

        return (string) ($provider ?: config('statamic.daugt-commerce.payment.provider', 'stripe'));
    }

    private function resolve(string $type, ?string $handle): mixed
    {
        $handle = $handle ?: $this->providerHandle();
        $providers = config('statamic.daugt-commerce.payment.providers', []);
        $definition = $providers[$handle] ?? null;

        if (! is_array($definition) || empty($definition[$type])) {
            throw new RuntimeException(sprintf('Payment %s is not configured for provider [%s].', $type, $handle));
        }

        return $this->container->make($definition[$type]);
    }

}
