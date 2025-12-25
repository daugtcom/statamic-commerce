<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Tests\Support\FakeStripeClient;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Stripe\StripeClient;

class StripeProductLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('queue.default', 'sync');

        $collection = CollectionFacade::make(ProductEntry::COLLECTION);
        $collection->entryClass(ProductEntry::class);
        $collection->save();
    }

    public function test_product_save_syncs_to_stripe_and_persists_ids(): void
    {
        $fakeStripe = new FakeStripeClient();
        $this->app->instance(StripeClient::class, $fakeStripe);

        $entry = EntryFacade::make()
            ->collection(ProductEntry::COLLECTION)
            ->slug('product-sync')
            ->published(true);

        $entry->data([
            ProductEntry::TITLE => 'Full Flow Product',
            ProductEntry::PRICE => 19.95,
            ProductEntry::BILLING_TYPE => 'one_time',
            ProductEntry::STRIPE_TAX_CODE => 'txcd_123',
        ]);

        $entry->save();

        $this->assertCount(1, $fakeStripe->products->created);
        $this->assertCount(1, $fakeStripe->prices->created);

        $entry = EntryFacade::find($entry->id());
        $this->assertNotNull($entry->get(ProductEntry::STRIPE_PRODUCT_ID));
        $this->assertNotNull($entry->get(ProductEntry::STRIPE_PRICE_ID));
    }

    public function test_product_delete_archives_stripe_records(): void
    {
        $fakeStripe = new FakeStripeClient();
        $this->app->instance(StripeClient::class, $fakeStripe);

        $entry = EntryFacade::make()
            ->collection(ProductEntry::COLLECTION)
            ->slug('product-delete')
            ->published(true);

        $entry->data([
            ProductEntry::TITLE => 'Delete Product',
            ProductEntry::PRICE => 10,
            ProductEntry::BILLING_TYPE => 'one_time',
            ProductEntry::STRIPE_PRODUCT_ID => 'prod_123',
            ProductEntry::STRIPE_PRICE_ID => 'price_123',
        ]);

        $entry->saveQuietly();

        $entry->delete();

        $this->assertSame('price_123', $fakeStripe->prices->updated[0]['id']);
        $this->assertSame(['active' => false], $fakeStripe->prices->updated[0]['payload']);

        $this->assertSame('prod_123', $fakeStripe->products->updated[0]['id']);
        $this->assertSame(['active' => false], $fakeStripe->products->updated[0]['payload']);
    }

    public function test_product_update_creates_new_price_when_price_changes(): void
    {
        $fakeStripe = new FakeStripeClient();
        $this->app->instance(StripeClient::class, $fakeStripe);

        $entry = EntryFacade::make()
            ->collection(ProductEntry::COLLECTION)
            ->slug('product-update')
            ->published(true);

        $entry->data([
            ProductEntry::TITLE => 'Update Product',
            ProductEntry::PRICE => 10,
            ProductEntry::BILLING_TYPE => 'one_time',
        ]);

        $entry->save();

        $entry = EntryFacade::find($entry->id());
        $originalPriceId = $entry->get(ProductEntry::STRIPE_PRICE_ID);
        $this->assertNotNull($originalPriceId);

        $entry->set(ProductEntry::PRICE, 20);
        $entry->save();

        $entry = EntryFacade::find($entry->id());
        $this->assertNotSame($originalPriceId, $entry->get(ProductEntry::STRIPE_PRICE_ID));
        $this->assertCount(2, $fakeStripe->prices->created);
    }
}
