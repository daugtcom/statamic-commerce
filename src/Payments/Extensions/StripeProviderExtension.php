<?php

namespace Daugt\Commerce\Payments\Extensions;

use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Entries\InvoiceEntry;
use Daugt\Commerce\Payments\Checkout\StripeCheckoutBuilder;
use Statamic\Fields\Blueprint as StatamicBlueprint;

class StripeProviderExtension extends AbstractPaymentProviderExtension
{
    public function extendEntryBlueprint(string $collectionHandle, StatamicBlueprint $blueprint): void
    {
        if ($collectionHandle === ProductEntry::COLLECTION) {
            $this->ensureStripeTab($blueprint, 'daugt-commerce::products.sections.tax');
            $blueprint->ensureFieldsInTab($this->productStripeFields(), 'stripe');
            return;
        }

        if ($collectionHandle === OrderEntry::COLLECTION) {
            $this->ensureStripeTab($blueprint, 'daugt-commerce::orders.sections.stripe');
            $blueprint->ensureFieldsInTab($this->orderStripeFields(), 'stripe');
            $this->ensureStripeOrderItemField($blueprint);
            return;
        }

        if ($collectionHandle === InvoiceEntry::COLLECTION) {
            $this->ensureStripeTab($blueprint, 'daugt-commerce::invoices.sections.stripe');
            $blueprint->ensureFieldsInTab($this->invoiceStripeFields(), 'stripe');
        }
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
        if (! in_array($collectionHandle, [
            ProductEntry::COLLECTION,
            OrderEntry::COLLECTION,
            InvoiceEntry::COLLECTION,
        ], true)) {
            return [];
        }

        return ['stripe'];
    }

    public static function userFieldsToRemove(): array
    {
        return ['stripe_id'];
    }

    public function checkoutView(array $params): ?array
    {
        return app(StripeCheckoutBuilder::class)->build($params);
    }

    private function ensureStripeTab(StatamicBlueprint $blueprint, string $display): void
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
            $sections[$sectionIndex]['display'] = $display;
        }

        $stripeTab['sections'] = $sections;
        $tabs['stripe'] = $stripeTab;
        $contents['tabs'] = $tabs;

        $blueprint->setContents($contents);
    }

    private function productStripeFields(): array
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

    private function orderStripeFields(): array
    {
        return [
            OrderEntry::STRIPE_CHECKOUT_SESSION_ID => [
                'type' => 'text',
                'display' => 'daugt-commerce::orders.fields.stripe_checkout_session_id',
                'read_only' => true,
            ],
        ];
    }

    private function invoiceStripeFields(): array
    {
        return [
            InvoiceEntry::STRIPE_PAYMENT_INTENT_ID => [
                'type' => 'text',
                'display' => 'daugt-commerce::invoices.fields.stripe_payment_intent_id',
                'read_only' => true,
            ],
            InvoiceEntry::STRIPE_INVOICE_ID => [
                'type' => 'text',
                'display' => 'daugt-commerce::invoices.fields.stripe_invoice_id',
                'read_only' => true,
            ],
        ];
    }

    private function ensureStripeOrderItemField(StatamicBlueprint $blueprint): void
    {
        $contents = $blueprint->contents();
        $tabs = $contents['tabs'] ?? [];
        $sections = $tabs['main']['sections'] ?? null;

        if (! is_array($sections)) {
            return;
        }

        foreach ($sections as $sectionIndex => $section) {
            $fields = $section['fields'] ?? null;
            if (! is_array($fields)) {
                continue;
            }

            foreach ($fields as $fieldIndex => $field) {
                if (($field['handle'] ?? null) !== OrderEntry::ITEMS) {
                    continue;
                }

                $itemFields = $field['field']['sets']['item']['fields'] ?? null;
                if (! is_array($itemFields)) {
                    return;
                }

                foreach ($itemFields as $itemField) {
                    if (($itemField['handle'] ?? null) === 'stripe_subscription_id') {
                        return;
                    }
                }

                $itemFields[] = [
                    'handle' => 'stripe_subscription_id',
                    'field' => [
                        'type' => 'text',
                        'display' => 'daugt-commerce::orders.fields.item_stripe_subscription_id',
                        'read_only' => true,
                        'width' => 33,
                    ],
                ];

                $contents['tabs']['main']['sections'][$sectionIndex]['fields'][$fieldIndex]['field']['sets']['item']['fields'] = $itemFields;
                $blueprint->setContents($contents);
                return;
            }
        }
    }
}
