<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Jobs\SyncPaymentProduct;
use Daugt\Commerce\Payments\PaymentProviderResolver;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;

class SyncPaymentProductTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $collection = CollectionFacade::make(ProductEntry::COLLECTION);
        $collection->entryClass(ProductEntry::class);
        $collection->save();
    }

    public function test_sync_creates_ids_for_published_entry(): void
    {
        $entry = $this->makeProduct();

        $ids = $this->runSync($entry);

        $this->assertSame("dummy_prod_{$entry->id()}", $ids['product_id']);
        $this->assertSame("dummy_price_{$entry->id()}", $ids['price_id']);
    }

    public function test_sync_skips_draft_without_existing_ids(): void
    {
        $entry = $this->makeProduct([], false);

        $ids = $this->runSync($entry);

        $this->assertNull($ids['product_id']);
        $this->assertNull($ids['price_id']);
    }

    public function test_external_product_clears_existing_ids(): void
    {
        $entry = $this->makeProduct([
            ProductEntry::EXTERNAL_PRODUCT => true,
        ]);

        $resolver = $this->app->make(PaymentProviderResolver::class);
        $resolver->store()->setProductIds($entry, 'dummy_prod_existing', 'dummy_price_existing');

        $ids = $this->runSync($entry);

        $this->assertNull($ids['product_id']);
        $this->assertNull($ids['price_id']);
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

    private function runSync(ProductEntry $entry): array
    {
        $resolver = $this->app->make(PaymentProviderResolver::class);
        (new SyncPaymentProduct($entry->id()))->handle($resolver);

        return $resolver->store()->getProductIds($entry);
    }
}
