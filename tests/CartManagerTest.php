<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Carts\CartManager;
use Daugt\Commerce\Carts\Stores\SessionCartStore;
use Daugt\Commerce\Entries\ProductEntry;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;

class CartManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('session.driver', 'array');
        $this->app['session.store']->start();

        $collection = CollectionFacade::make(ProductEntry::COLLECTION);
        $collection->entryClass(ProductEntry::class);
        $collection->save();
    }

    public function test_adds_and_merges_items(): void
    {
        $manager = $this->manager();

        $manager->add('prod-1', 1);
        $manager->add('prod-1', 2);

        $cart = $manager->get();

        $this->assertCount(1, $cart['items']);
        $this->assertSame(3, $cart['items']['prod-1']);
    }

    public function test_set_quantity_and_remove(): void
    {
        $manager = $this->manager();

        $manager->add('prod-1', 1);
        $manager->setQuantity('prod-1', 5);

        $cart = $manager->get();
        $this->assertSame(5, $cart['items']['prod-1']);

        $manager->remove('prod-1');
        $cart = $manager->get();

        $this->assertCount(0, $cart['items']);
    }

    public function test_add_subscription_forces_quantity_one_and_keeps_one_time(): void
    {
        $subscription = $this->makeProduct('sub-1', 'recurring');
        $oneTime = $this->makeProduct('one-1', 'one_time');

        $manager = $this->manager();
        $manager->add($oneTime->id(), 2);
        $manager->add($subscription->id(), 3);

        $cart = $manager->get();

        $this->assertCount(2, $cart['items']);
        $this->assertSame(1, $cart['items'][$subscription->id()]);
        $this->assertSame(2, $cart['items'][$oneTime->id()]);
    }

    public function test_add_subscription_replaces_existing_subscription_only(): void
    {
        $firstSubscription = $this->makeProduct('sub-2', 'recurring');
        $secondSubscription = $this->makeProduct('sub-3', 'recurring');
        $oneTime = $this->makeProduct('one-2', 'one_time');

        $manager = $this->manager();
        $manager->add($firstSubscription->id(), 1);
        $manager->add($oneTime->id(), 1);
        $manager->add($secondSubscription->id(), 1);

        $cart = $manager->get();

        $this->assertCount(2, $cart['items']);
        $this->assertSame(1, $cart['items'][$secondSubscription->id()]);
        $this->assertSame(1, $cart['items'][$oneTime->id()]);
    }

    public function test_add_one_time_keeps_subscription(): void
    {
        $subscription = $this->makeProduct('sub-4', 'recurring');
        $oneTime = $this->makeProduct('one-3', 'one_time');

        $manager = $this->manager();
        $manager->add($subscription->id(), 1);
        $manager->add($oneTime->id(), 2);

        $cart = $manager->get();

        $this->assertCount(2, $cart['items']);
        $this->assertSame(1, $cart['items'][$subscription->id()]);
        $this->assertSame(2, $cart['items'][$oneTime->id()]);
    }

    private function manager(): CartManager
    {
        return new CartManager(new SessionCartStore($this->app['session.store']));
    }

    private function makeProduct(string $id, string $billingType): ProductEntry
    {
        $entry = EntryFacade::make()
            ->collection(ProductEntry::COLLECTION)
            ->id($id)
            ->slug($id)
            ->published(true);

        $entry->data([
            ProductEntry::TITLE => 'Test Product',
            ProductEntry::BILLING_TYPE => $billingType,
            ProductEntry::PRICE => 10,
        ]);

        $entry->saveQuietly();

        return $entry;
    }
}
