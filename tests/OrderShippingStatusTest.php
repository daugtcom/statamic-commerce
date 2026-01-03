<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Actions\UpdateOrderShippingStatus;
use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Enums\ShippingStatus;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;

class OrderShippingStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $collection = CollectionFacade::make(OrderEntry::COLLECTION);
        $collection->entryClass(OrderEntry::class);
        $collection->save();

        $productCollection = CollectionFacade::make(ProductEntry::COLLECTION);
        $productCollection->entryClass(ProductEntry::class);
        $productCollection->save();
    }

    public function test_shipping_status_is_pending_when_items_are_pending(): void
    {
        $first = $this->makeProduct(true);
        $second = $this->makeProduct(true);
        $order = $this->makeOrder([
            ['type' => 'item', 'product' => [$first->id()], 'shipping_status' => ShippingStatus::PENDING->value],
            ['type' => 'item', 'product' => [$second->id()], 'shipping_status' => ShippingStatus::PENDING->value],
        ]);

        $this->assertSame(ShippingStatus::PENDING->value, $this->computedShippingStatus($order));
    }

    public function test_shipping_status_is_shipped_when_partially_shipped(): void
    {
        $first = $this->makeProduct(true);
        $second = $this->makeProduct(true);
        $order = $this->makeOrder([
            ['type' => 'item', 'product' => [$first->id()], 'shipping_status' => ShippingStatus::PENDING->value],
            ['type' => 'item', 'product' => [$second->id()], 'shipping_status' => ShippingStatus::SHIPPED->value],
        ]);

        $this->assertSame(ShippingStatus::SHIPPED->value, $this->computedShippingStatus($order));
    }

    public function test_shipping_status_is_delivered_when_all_items_delivered(): void
    {
        $first = $this->makeProduct(true);
        $second = $this->makeProduct(true);
        $order = $this->makeOrder([
            ['type' => 'item', 'product' => [$first->id()], 'shipping_status' => ShippingStatus::DELIVERED->value],
            ['type' => 'item', 'product' => [$second->id()], 'shipping_status' => ShippingStatus::DELIVERED->value],
        ]);

        $this->assertSame(ShippingStatus::DELIVERED->value, $this->computedShippingStatus($order));
    }

    public function test_shipping_status_is_shipped_when_not_all_items_are_delivered(): void
    {
        $first = $this->makeProduct(true);
        $second = $this->makeProduct(true);
        $order = $this->makeOrder([
            ['type' => 'item', 'product' => [$first->id()], 'shipping_status' => ShippingStatus::DELIVERED->value],
            ['type' => 'item', 'product' => [$second->id()], 'shipping_status' => ShippingStatus::PENDING->value],
        ]);

        $this->assertSame(ShippingStatus::SHIPPED->value, $this->computedShippingStatus($order));
    }

    public function test_shipping_status_is_null_when_no_items_require_shipping(): void
    {
        $first = $this->makeProduct(false);
        $second = $this->makeProduct(false);

        $order = $this->makeOrder([
            ['type' => 'item', 'product' => [$first->id()], 'shipping_status' => ShippingStatus::DELIVERED->value],
            ['type' => 'item', 'product' => [$second->id()], 'shipping_status' => ShippingStatus::SHIPPED->value],
        ]);

        $this->assertNull($this->computedShippingStatus($order));
    }

    public function test_action_updates_order_item_shipping_statuses(): void
    {
        $first = $this->makeProduct(true);
        $second = $this->makeProduct(true);
        $order = $this->makeOrder([
            ['type' => 'item', 'product' => [$first->id()], 'shipping_status' => ShippingStatus::PENDING->value],
            ['type' => 'item', 'product' => [$second->id()], 'shipping_status' => ShippingStatus::SHIPPED->value],
        ]);

        $action = new UpdateOrderShippingStatus();
        $action->run(collect([$order]), ['shipping_status' => ShippingStatus::DELIVERED->value]);

        $order = Entry::find($order->id());
        $this->assertInstanceOf(OrderEntry::class, $order);

        $items = $order->get(OrderEntry::ITEMS);
        $this->assertIsArray($items);

        foreach ($items as $item) {
            $this->assertSame(ShippingStatus::DELIVERED->value, $item['shipping_status'] ?? null);
        }
    }

    public function test_action_skips_items_without_shipping(): void
    {
        $shippable = $this->makeProduct(true);
        $nonShippable = $this->makeProduct(false);

        $order = $this->makeOrder([
            ['type' => 'item', 'product' => [$shippable->id()], 'shipping_status' => ShippingStatus::PENDING->value],
            ['type' => 'item', 'product' => [$nonShippable->id()]],
        ]);

        $action = new UpdateOrderShippingStatus();
        $action->run(collect([$order]), ['shipping_status' => ShippingStatus::DELIVERED->value]);

        $order = Entry::find($order->id());
        $this->assertInstanceOf(OrderEntry::class, $order);

        $items = $order->get(OrderEntry::ITEMS);
        $this->assertIsArray($items);

        $firstItem = $items[0] ?? [];
        $secondItem = $items[1] ?? [];

        $this->assertSame(ShippingStatus::DELIVERED->value, $firstItem['shipping_status'] ?? null);
        $this->assertArrayNotHasKey('shipping_status', $secondItem);
    }

    private function makeOrder(array $items): OrderEntry
    {
        $order = Entry::make()->collection(OrderEntry::COLLECTION);
        $order->set(OrderEntry::ITEMS, $items);
        $order->saveQuietly();

        $order = Entry::find($order->id());
        $this->assertInstanceOf(OrderEntry::class, $order);

        return $order;
    }

    private function computedShippingStatus(OrderEntry $order): ?string
    {
        return $order->augmentedValue(OrderEntry::SHIPPING_STATUS)->raw();
    }

    private function makeProduct(bool $shipping): ProductEntry
    {
        $product = Entry::make()->collection(ProductEntry::COLLECTION);
        $product->set(ProductEntry::TITLE, 'Test Product');
        $product->set(ProductEntry::SHIPPING, $shipping);
        $product->saveQuietly();

        $product = Entry::find($product->id());
        $this->assertInstanceOf(ProductEntry::class, $product);

        return $product;
    }
}
