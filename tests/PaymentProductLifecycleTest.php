<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Payments\PaymentProviderResolver;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;

class PaymentProductLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('queue.default', 'sync');

        $collection = CollectionFacade::make(ProductEntry::COLLECTION);
        $collection->entryClass(ProductEntry::class);
        $collection->save();
    }

    public function test_product_save_persists_dummy_ids(): void
    {
        $entry = EntryFacade::make()
            ->collection(ProductEntry::COLLECTION)
            ->slug('product-sync')
            ->published(true);

        $entry->data([
            ProductEntry::TITLE => 'Full Flow Product',
            ProductEntry::PRICE => 19.95,
            ProductEntry::BILLING_TYPE => 'one_time',
        ]);

        $entry->save();

        $resolver = $this->app->make(PaymentProviderResolver::class);
        $ids = $resolver->store()->getProductIds($entry);

        $this->assertSame("dummy_prod_{$entry->id()}", $ids['product_id']);
        $this->assertSame("dummy_price_{$entry->id()}", $ids['price_id']);
    }

    public function test_product_update_keeps_dummy_ids(): void
    {
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

        $resolver = $this->app->make(PaymentProviderResolver::class);
        $initialIds = $resolver->store()->getProductIds($entry);

        $entry->set(ProductEntry::PRICE, 20);
        $entry->save();

        $updatedIds = $resolver->store()->getProductIds($entry);

        $this->assertSame($initialIds, $updatedIds);
    }
}
