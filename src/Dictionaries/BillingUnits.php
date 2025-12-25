<?php

namespace Daugt\Commerce\Dictionaries;

use Daugt\Commerce\Enums\BillingUnit;
use Statamic\Dictionaries\BasicDictionary;

class BillingUnits extends BasicDictionary
{
    protected function getItems(): array
    {
        return collect(BillingUnit::cases())->map(fn (BillingUnit $unit) => [
            'value' => $unit->value,
            'label' => __("daugt-commerce::billing-units.{$unit->value}"),
        ])->all();
    }
}
