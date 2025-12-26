<?php

namespace Daugt\Commerce\Dictionaries;

use Daugt\Commerce\Enums\InvoiceStatus;
use Statamic\Dictionaries\BasicDictionary;

class InvoiceStatuses extends BasicDictionary
{
    protected function getItems(): array
    {
        return collect(InvoiceStatus::cases())->map(fn (InvoiceStatus $status) => [
            'value' => $status->value,
            'label' => __("daugt-commerce::invoice-statuses.{$status->value}"),
        ])->all();
    }
}
