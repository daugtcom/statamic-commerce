<?php

namespace Daugt\Commerce\Dictionaries;

use Daugt\Commerce\Enums\OrderStatus;
use Statamic\Dictionaries\BasicDictionary;

class OrderStatuses extends BasicDictionary
{
    protected function getItems(): array
    {
        return collect(OrderStatus::cases())->map(fn (OrderStatus $status) => [
            'value' => $status->value,
            'label' => __("daugt-commerce::order-statuses.{$status->value}"),
        ])->all();
    }
}
