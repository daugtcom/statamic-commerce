<?php

namespace Daugt\Commerce\Dictionaries;

use Daugt\Commerce\Enums\AccessType;
use Statamic\Dictionaries\BasicDictionary;

class AccessTypes extends BasicDictionary
{
    protected function getItems(): array
    {
        return collect(AccessType::cases())->map(fn (AccessType $type) => [
            'value' => $type->value,
            'label' => __("daugt-commerce::access-types.{$type->value}"),
        ])->all();
    }
}
