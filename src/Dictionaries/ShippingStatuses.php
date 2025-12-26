<?php

namespace Daugt\Commerce\Dictionaries;

use Daugt\Commerce\Enums\ShippingStatus;
use Statamic\Dictionaries\BasicDictionary;

class ShippingStatuses extends BasicDictionary
{
    protected function getItems(): array
    {
        return collect(ShippingStatus::cases())->map(fn (ShippingStatus $status) => [
            'value' => $status->value,
            'label' => __("daugt-commerce::shipping-statuses.{$status->value}"),
        ])->all();
    }
}
