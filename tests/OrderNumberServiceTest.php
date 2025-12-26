<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Blueprints\OrderBlueprint;
use Daugt\Commerce\Blueprints\OrderCollection;
use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Support\AddonSettings;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;

class OrderNumberServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $collection = (new OrderCollection())();
        $collection->save();

        $blueprint = (new OrderBlueprint())();
        $blueprint->setHandle('collections/orders/order');
        Blueprint::save($blueprint);

        AddonSettings::set('next_order_number', 1);
    }

    public function test_assigns_next_order_number_and_increments_setting(): void
    {
        $order = Entry::make()->collection(OrderEntry::COLLECTION);
        $order->set(OrderEntry::STATUS, 'pending');
        $order->save();

        $this->assertSame(1, $order->orderNumber());
        $this->assertSame(2, (int) AddonSettings::get('next_order_number'));
    }

    public function test_uses_configured_next_order_number(): void
    {
        AddonSettings::set('next_order_number', 42);

        $order = Entry::make()->collection(OrderEntry::COLLECTION);
        $order->set(OrderEntry::STATUS, 'pending');
        $order->save();

        $this->assertSame(42, $order->orderNumber());
        $this->assertSame(43, (int) AddonSettings::get('next_order_number'));
    }

    public function test_updates_next_order_number_when_manual_value_is_higher(): void
    {
        AddonSettings::set('next_order_number', 5);

        $order = Entry::make()->collection(OrderEntry::COLLECTION);
        $order->set(OrderEntry::ORDER_NUMBER, 10);
        $order->set(OrderEntry::STATUS, 'pending');
        $order->save();

        $this->assertSame(10, $order->orderNumber());
        $this->assertSame(11, (int) AddonSettings::get('next_order_number'));
    }

    public function test_avoids_duplicate_numbers_when_setting_is_stale(): void
    {
        $first = Entry::make()->collection(OrderEntry::COLLECTION);
        $first->set(OrderEntry::STATUS, 'pending');
        $first->save();

        AddonSettings::set('next_order_number', 1);

        $second = Entry::make()->collection(OrderEntry::COLLECTION);
        $second->set(OrderEntry::STATUS, 'pending');
        $second->save();

        $this->assertSame(1, $first->orderNumber());
        $this->assertSame(2, $second->orderNumber());
        $this->assertSame(3, (int) AddonSettings::get('next_order_number'));
    }
}
