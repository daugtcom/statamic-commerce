<?php

namespace Daugt\Commerce\Dictionaries;

use Daugt\Commerce\Enums\BillingType;
use Statamic\Dictionaries\BasicDictionary;

class BillingTypes extends BasicDictionary
{
    protected function getItems(): array
    {
        return collect(BillingType::cases())->map(fn (BillingType $unit) => [
            'value' => $unit->value,
            'label' => __("daugt-commerce::billing-types.{$unit->value}"),
        ])->all();
    }
}
