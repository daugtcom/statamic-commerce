<?php

namespace Daugt\Commerce\Dictionaries;

use Illuminate\Support\Str;
use Statamic\Dictionaries\BasicDictionary;

class PaymentProviders extends BasicDictionary
{
    protected function getItems(): array
    {
        $providers = config('statamic.daugt-commerce.payment.providers', []);

        return collect($providers)->map(function (array $config, string $handle) {
            $label = Str::ucfirst($handle);

            return [
                'value' => $handle,
                'label' => $label,
            ];
        })->values()->all();
    }
}
