<?php

namespace Daugt\Commerce\Payments\Extensions;

use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Payments\Contracts\PaymentProviderExtension;
use Statamic\Fields\Blueprint as StatamicBlueprint;

class StripeProviderExtension implements PaymentProviderExtension
{
    public function extendEntryBlueprint(string $collectionHandle, StatamicBlueprint $blueprint): void
    {
        if ($collectionHandle !== ProductEntry::COLLECTION) {
            return;
        }

        $this->ensureStripeTab($blueprint);
        $blueprint->ensureFieldsInTab($this->stripeFields(), 'stripe');
    }

    public function extendUserBlueprint(StatamicBlueprint $blueprint): void
    {
        $blueprint->ensureField('stripe_id', [
            'type' => 'text',
            'display' => 'daugt-commerce::users.fields.stripe_id',
            'read_only' => true,
        ], 'sidebar');
    }

    public static function entryTabsToRemove(string $collectionHandle): array
    {
        if ($collectionHandle !== ProductEntry::COLLECTION) {
            return [];
        }

        return ['stripe'];
    }

    public static function userFieldsToRemove(): array
    {
        return ['stripe_id'];
    }

    private function ensureStripeTab(StatamicBlueprint $blueprint): void
    {
        $contents = $blueprint->contents();
        $tabs = $contents['tabs'] ?? [];
        $stripeTab = $tabs['stripe'] ?? [];
        $sections = $stripeTab['sections'] ?? [];

        $sectionIndex = count($sections) ? count($sections) - 1 : 0;
        if (! isset($sections[$sectionIndex])) {
            $sections[$sectionIndex] = [];
        }

        if (! isset($sections[$sectionIndex]['display'])) {
            $sections[$sectionIndex]['display'] = 'daugt-commerce::products.sections.tax';
        }

        $stripeTab['sections'] = $sections;
        $tabs['stripe'] = $stripeTab;
        $contents['tabs'] = $tabs;

        $blueprint->setContents($contents);
    }

    private function stripeFields(): array
    {
        return [
            ProductEntry::STRIPE_TAX_CODE => [
                'dictionary' => 'stripe_tax_codes',
                'max_items' => 1,
                'type' => 'dictionary',
                'display' => 'daugt-commerce::products.fields.stripe_tax_code',
            ],
            ProductEntry::STRIPE_PRODUCT_ID => [
                'type' => 'text',
                'display' => 'daugt-commerce::products.fields.stripe_product_id',
                'read_only' => true,
            ],
            ProductEntry::STRIPE_PRICE_ID => [
                'type' => 'text',
                'display' => 'daugt-commerce::products.fields.stripe_price_id',
                'read_only' => true,
            ],
        ];
    }
}
