<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Carts\CartManager;
use Daugt\Commerce\Carts\Stores\SessionCartStore;

class CartManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('session.driver', 'array');
        $this->app['session.store']->start();
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

    private function manager(): CartManager
    {
        return new CartManager(new SessionCartStore($this->app['session.store']));
    }
}
