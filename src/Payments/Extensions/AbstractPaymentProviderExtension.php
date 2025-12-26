<?php

namespace Daugt\Commerce\Payments\Extensions;

use Daugt\Commerce\Payments\Contracts\PaymentProviderExtension;
use Statamic\Fields\Blueprint as StatamicBlueprint;

abstract class AbstractPaymentProviderExtension implements PaymentProviderExtension
{
    public function extendEntryBlueprint(string $collectionHandle, StatamicBlueprint $blueprint): void
    {
        // Optional override.
    }

    public function extendUserBlueprint(StatamicBlueprint $blueprint): void
    {
        // Optional override.
    }

    public static function entryTabsToRemove(string $collectionHandle): array
    {
        return [];
    }

    public static function userFieldsToRemove(): array
    {
        return [];
    }

}
