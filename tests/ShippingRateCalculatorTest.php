<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Enums\ShippingCalculationMethod;
use Daugt\Commerce\Services\ShippingRateCalculator;
use Daugt\Commerce\Services\StripeLineItemProductResolver;
use Daugt\Commerce\Support\AddonSettings;
use Statamic\Addons\Addon as StatamicAddon;
use Statamic\Facades\Addon;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;

class ShippingRateCalculatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $collection = CollectionFacade::make(ProductEntry::COLLECTION);
        $collection->entryClass(ProductEntry::class);
        $collection->save();

        $addon = StatamicAddon::make('daugtcom/statamic-commerce');
        $addon->editions(['pro']);
        Addon::shouldReceive('get')
            ->with('daugtcom/statamic-commerce')
            ->andReturn($addon);
        Addon::shouldReceive('all')
            ->andReturn(collect([$addon]));
    }

    public function test_calculate_amount_uses_sum_for_shippable_items(): void
    {
        AddonSettings::set('shipping_flat_rate', 10);
        AddonSettings::set('shipping_calculation_method', ShippingCalculationMethod::SUM->value);

        $this->makeProduct('price_ship', true);
        $this->makeProduct('price_non', false);

        $lineItems = [
            [
                'price' => [
                    'id' => 'price_ship',
                    'product' => 'prod_ship',
                ],
                'quantity' => 2,
            ],
            [
                'price' => [
                    'id' => 'price_non',
                    'product' => 'prod_non',
                ],
                'quantity' => 1,
            ],
        ];

        $amount = $this->makeCalculator()->calculateAmountForCountry('GB', $lineItems);

        $this->assertSame(2000, $amount);
    }

    public function test_calculate_amount_uses_max_for_shippable_items(): void
    {
        AddonSettings::set('shipping_flat_rate', 10);
        AddonSettings::set('shipping_calculation_method', ShippingCalculationMethod::MAX->value);

        $this->makeProduct('price_ship', true);

        $lineItems = [
            [
                'price' => [
                    'id' => 'price_ship',
                    'product' => 'prod_ship',
                ],
                'quantity' => 3,
            ],
        ];

        $amount = $this->makeCalculator()->calculateAmountForCountry('GB', $lineItems);

        $this->assertSame(1000, $amount);
    }

    public function test_calculate_amount_returns_null_when_no_items_require_shipping(): void
    {
        AddonSettings::set('shipping_flat_rate', 10);

        $this->makeProduct('price_non', false);

        $lineItems = [
            [
                'price' => [
                    'id' => 'price_non',
                    'product' => 'prod_non',
                ],
                'quantity' => 1,
            ],
        ];

        $amount = $this->makeCalculator()->calculateAmountForCountry('GB', $lineItems);

        $this->assertNull($amount);
    }

    public function test_is_country_allowed_respects_settings(): void
    {
        AddonSettings::set('shipping_allowed_countries', ['gb', 'DE']);

        $calculator = $this->makeCalculator();

        $this->assertTrue($calculator->isCountryAllowed('GB'));
        $this->assertTrue($calculator->isCountryAllowed('DE'));
        $this->assertFalse($calculator->isCountryAllowed('US'));
    }

    private function makeCalculator(): ShippingRateCalculator
    {
        return new ShippingRateCalculator(new StripeLineItemProductResolver());
    }

    private function makeProduct(string $priceId, bool $shipping): ProductEntry
    {
        $entry = Entry::make()->collection(ProductEntry::COLLECTION);
        $entry->set(ProductEntry::TITLE, 'Test Product');
        $entry->set(ProductEntry::STRIPE_PRICE_ID, $priceId);
        $entry->set(ProductEntry::STRIPE_PRODUCT_ID, 'prod_' . $priceId);
        $entry->set(ProductEntry::SHIPPING, $shipping);
        $entry->set(ProductEntry::EXTERNAL_PRODUCT, false);
        $entry->saveQuietly();

        return $entry;
    }
}
