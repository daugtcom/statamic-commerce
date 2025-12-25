<?php

namespace Daugt\Commerce\Dictionaries;

use Daugt\Commerce\Enums\BillingDurationType;
use Statamic\Dictionaries\BasicDictionary;

class BillingDurationTypes extends BasicDictionary
{
    protected function getItems(): array
    {
        return collect(BillingDurationType::cases())->map(fn (BillingDurationType $unit) => [
            'value' => $unit->value,
            'label' => __("daugt-commerce::billing-duration-types.{$unit->value}"),
        ])->all();
    }
}
