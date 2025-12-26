<?php

namespace Daugt\Commerce\Payments\Extensions;

use Daugt\Commerce\Payments\Contracts\PaymentProviderExtension;
use Statamic\Fields\Blueprint as StatamicBlueprint;

class DummyProviderExtension implements PaymentProviderExtension
{
    public function extendEntryBlueprint(string $collectionHandle, StatamicBlueprint $blueprint): void
    {
        // No-op for dummy provider.
    }

    public function extendUserBlueprint(StatamicBlueprint $blueprint): void
    {
        // No-op for dummy provider.
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
