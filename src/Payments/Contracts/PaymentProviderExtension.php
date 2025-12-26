<?php

namespace Daugt\Commerce\Payments\Contracts;

use Statamic\Fields\Blueprint as StatamicBlueprint;

interface PaymentProviderExtension
{
    public function extendEntryBlueprint(string $collectionHandle, StatamicBlueprint $blueprint): void;

    public function extendUserBlueprint(StatamicBlueprint $blueprint): void;

    public static function entryTabsToRemove(string $collectionHandle): array;

    public static function userFieldsToRemove(): array;
}
