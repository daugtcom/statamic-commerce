<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Jobs\SyncStripeProduct;
use Daugt\Commerce\Tests\Support\FakeStripeClient;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;

class SyncStripeProductTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $collection = CollectionFacade::make(ProductEntry::COLLECTION);
        $collection->entryClass(ProductEntry::class);
        $collection->save();
    }

    public function test_sync_creates_product_and_price_for_published_entry(): void
    {
        $entry = $this->makeProduct();

        $fakeStripe = new FakeStripeClient();
        (new SyncStripeProduct($entry->id()))->handle($fakeStripe);

        $this->assertCount(1, $fakeStripe->products->created);
        $this->assertCount(1, $fakeStripe->prices->created);

        $entry = EntryFacade::find($entry->id());
        $this->assertNotNull($entry->get(ProductEntry::STRIPE_PRODUCT_ID));
        $this->assertNotNull($entry->get(ProductEntry::STRIPE_PRICE_ID));
    }

    public function test_sync_updates_existing_price_when_matching(): void
    {
        $entry = $this->makeProduct([
            ProductEntry::STRIPE_PRODUCT_ID => 'prod_123',
            ProductEntry::STRIPE_PRICE_ID => 'price_123',
        ]);

        $fakeStripe = new FakeStripeClient();
        $fakeStripe->prices->seed('price_123', [
            'currency' => 'eur',
            'unit_amount' => 1000,
            'recurring' => null,
            'active' => false,
        ]);

        (new SyncStripeProduct($entry->id()))->handle($fakeStripe);

        $this->assertCount(1, $fakeStripe->products->updated);
        $this->assertCount(0, $fakeStripe->prices->created);
        $this->assertCount(1, $fakeStripe->prices->updated);

        $this->assertSame('price_123', $fakeStripe->prices->updated[0]['id']);
        $this->assertSame(['active' => true], $fakeStripe->prices->updated[0]['payload']);
    }

    public function test_sync_creates_new_price_when_price_changes(): void
    {
        $entry = $this->makeProduct([
            ProductEntry::PRICE => 12,
            ProductEntry::STRIPE_PRODUCT_ID => 'prod_123',
            ProductEntry::STRIPE_PRICE_ID => 'price_123',
        ]);

        $fakeStripe = new FakeStripeClient();
        $fakeStripe->prices->seed('price_123', [
            'currency' => 'eur',
            'unit_amount' => 1000,
            'recurring' => null,
            'active' => true,
        ]);

        (new SyncStripeProduct($entry->id()))->handle($fakeStripe);

        $this->assertCount(1, $fakeStripe->prices->updated);
        $this->assertSame(['active' => false], $fakeStripe->prices->updated[0]['payload']);
        $this->assertCount(1, $fakeStripe->prices->created);

        $entry = EntryFacade::find($entry->id());
        $this->assertNotNull($entry->get(ProductEntry::STRIPE_PRICE_ID));
        $this->assertNotSame('price_123', $entry->get(ProductEntry::STRIPE_PRICE_ID));
    }

    public function test_sync_creates_recurring_price(): void
    {
        $entry = $this->makeProduct([
            ProductEntry::BILLING_TYPE => 'recurring',
            ProductEntry::SUBSCRIPTION_INTERVAL => 2,
            ProductEntry::SUBSCRIPTION_INTERVAL_UNIT => 'month',
        ]);

        $fakeStripe = new FakeStripeClient();
        (new SyncStripeProduct($entry->id()))->handle($fakeStripe);

        $this->assertCount(1, $fakeStripe->prices->created);
        $payload = $fakeStripe->prices->created[0];
        $this->assertSame([
            'interval' => 'month',
            'interval_count' => 2,
        ], $payload['recurring']);
    }

    public function test_sync_marks_draft_inactive_when_ids_exist(): void
    {
        $entry = $this->makeProduct([
            ProductEntry::STRIPE_PRODUCT_ID => 'prod_123',
            ProductEntry::STRIPE_PRICE_ID => 'price_123',
        ], false);

        $fakeStripe = new FakeStripeClient();
        $fakeStripe->prices->seed('price_123', [
            'currency' => 'eur',
            'unit_amount' => 1000,
            'recurring' => null,
            'active' => true,
        ]);

        (new SyncStripeProduct($entry->id()))->handle($fakeStripe);

        $this->assertSame('prod_123', $fakeStripe->products->updated[0]['id']);
        $this->assertFalse($fakeStripe->products->updated[0]['payload']['active']);

        $this->assertSame('price_123', $fakeStripe->prices->updated[0]['id']);
        $this->assertSame(['active' => false], $fakeStripe->prices->updated[0]['payload']);
    }

    public function test_sync_persists_product_id_when_interval_is_invalid(): void
    {
        $entry = $this->makeProduct([
            ProductEntry::BILLING_TYPE => 'recurring',
            ProductEntry::SUBSCRIPTION_INTERVAL_UNIT => 'hour',
        ]);

        $fakeStripe = new FakeStripeClient();
        (new SyncStripeProduct($entry->id()))->handle($fakeStripe);

        $this->assertCount(1, $fakeStripe->products->created);
        $this->assertCount(0, $fakeStripe->prices->created);

        $entry = EntryFacade::find($entry->id());
        $this->assertNotNull($entry->get(ProductEntry::STRIPE_PRODUCT_ID));
        $this->assertNull($entry->get(ProductEntry::STRIPE_PRICE_ID));
    }

    public function test_sync_updates_tax_code_on_product(): void
    {
        $entry = $this->makeProduct([
            ProductEntry::STRIPE_PRODUCT_ID => 'prod_123',
            ProductEntry::STRIPE_TAX_CODE => 'txcd_123',
        ]);

        $fakeStripe = new FakeStripeClient();
        (new SyncStripeProduct($entry->id()))->handle($fakeStripe);

        $this->assertSame('prod_123', $fakeStripe->products->updated[0]['id']);
        $this->assertSame('txcd_123', $fakeStripe->products->updated[0]['payload']['tax_code']);
    }

    public function test_sync_skips_draft_without_stripe_ids(): void
    {
        $entry = $this->makeProduct([], false);

        $fakeStripe = new FakeStripeClient();
        (new SyncStripeProduct($entry->id()))->handle($fakeStripe);

        $this->assertCount(0, $fakeStripe->products->created);
        $this->assertCount(0, $fakeStripe->prices->created);
    }

    public function test_external_product_archives_stripe_data(): void
    {
        $entry = $this->makeProduct([
            ProductEntry::EXTERNAL_PRODUCT => true,
            ProductEntry::STRIPE_PRODUCT_ID => 'prod_123',
            ProductEntry::STRIPE_PRICE_ID => 'price_123',
        ]);

        $fakeStripe = new FakeStripeClient();
        (new SyncStripeProduct($entry->id()))->handle($fakeStripe);

        $this->assertCount(1, $fakeStripe->products->updated);
        $this->assertSame('prod_123', $fakeStripe->products->updated[0]['id']);

        $this->assertCount(1, $fakeStripe->prices->updated);
        $this->assertSame('price_123', $fakeStripe->prices->updated[0]['id']);

        $entry = EntryFacade::find($entry->id());
        $this->assertNull($entry->get(ProductEntry::STRIPE_PRODUCT_ID));
        $this->assertNull($entry->get(ProductEntry::STRIPE_PRICE_ID));
    }

    private function makeProduct(array $data = [], bool $published = true): ProductEntry
    {
        $entry = EntryFacade::make()
            ->collection(ProductEntry::COLLECTION)
            ->slug('product-' . uniqid())
            ->published($published);

        $entry->data(array_merge([
            ProductEntry::TITLE => 'Test Product',
            ProductEntry::PRICE => 10,
            ProductEntry::BILLING_TYPE => 'one_time',
        ], $data));

        $entry->saveQuietly();

        return $entry;
    }
}
