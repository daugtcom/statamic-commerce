<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Entries\ProductEntry;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;

class ProductEntryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $collection = CollectionFacade::make(ProductEntry::COLLECTION);
        $collection->entryClass(ProductEntry::class);
        $collection->save();
    }

    public function test_entry_getters_return_expected_values(): void
    {
        $entry = EntryFacade::make()
            ->collection(ProductEntry::COLLECTION)
            ->slug('getter-test')
            ->published(true);

        $entry->data([
            ProductEntry::TITLE => 'Getter Product',
            ProductEntry::PRICE => 12.5,
            ProductEntry::BILLING_TYPE => 'recurring',
            ProductEntry::SUBSCRIPTION_INTERVAL => 3,
            ProductEntry::SUBSCRIPTION_INTERVAL_UNIT => 'month',
            ProductEntry::SUBSCRIPTION_DURATION => 'limited',
            ProductEntry::SUBSCRIPTION_DURATION_ITERATIONS => 6,
            ProductEntry::SHIPPING => true,
            ProductEntry::EXTERNAL_PRODUCT => false,
            ProductEntry::EXTERNAL_PRODUCT_URL => 'https://example.test',
            ProductEntry::STRIPE_TAX_CODE => 'txcd_123',
            ProductEntry::STRIPE_PRODUCT_ID => 'prod_123',
            ProductEntry::STRIPE_PRICE_ID => 'price_123',
        ]);

        $entry->saveQuietly();

        $entry = EntryFacade::find($entry->id());
        $this->assertInstanceOf(ProductEntry::class, $entry);
        $this->assertSame(12.5, $entry->price());
        $this->assertSame('recurring', $entry->billingType());
        $this->assertSame(3, $entry->subscriptionInterval());
        $this->assertSame('month', $entry->subscriptionIntervalUnit());
        $this->assertSame('limited', $entry->subscriptionDuration());
        $this->assertSame(6, $entry->subscriptionDurationIterations());
        $this->assertTrue($entry->shipping());
        $this->assertFalse($entry->externalProduct());
        $this->assertSame('https://example.test', $entry->externalProductUrl());
        $this->assertSame('txcd_123', $entry->stripeTaxCode());
        $this->assertSame('prod_123', $entry->stripeProductId());
        $this->assertSame('price_123', $entry->stripePriceId());
    }

    public function test_entry_getters_handle_missing_values(): void
    {
        $entry = EntryFacade::make()
            ->collection(ProductEntry::COLLECTION)
            ->slug('getter-missing')
            ->published(true);

        $entry->data([
            ProductEntry::TITLE => 'Missing Values',
        ]);

        $entry->saveQuietly();

        $entry = EntryFacade::find($entry->id());
        $this->assertNull($entry->price());
        $this->assertNull($entry->billingType());
        $this->assertNull($entry->subscriptionInterval());
        $this->assertNull($entry->subscriptionIntervalUnit());
        $this->assertNull($entry->subscriptionDuration());
        $this->assertNull($entry->subscriptionDurationIterations());
        $this->assertFalse($entry->shipping());
        $this->assertFalse($entry->externalProduct());
        $this->assertNull($entry->externalProductUrl());
        $this->assertNull($entry->stripeTaxCode());
        $this->assertNull($entry->stripeProductId());
        $this->assertNull($entry->stripePriceId());
    }
}
