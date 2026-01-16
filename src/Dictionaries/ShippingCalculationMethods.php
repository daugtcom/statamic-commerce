<?php

namespace Daugt\Commerce\Dictionaries;

use Daugt\Commerce\Enums\ShippingCalculationMethod;
use Statamic\Dictionaries\BasicDictionary;

class ShippingCalculationMethods extends BasicDictionary
{
    protected function getItems(): array
    {
        return collect(ShippingCalculationMethod::cases())->map(fn (ShippingCalculationMethod $method) => [
            'value' => $method->value,
            'label' => __("daugt-commerce::shipping-calculation.{$method->value}"),
        ])->all();
    }
}
