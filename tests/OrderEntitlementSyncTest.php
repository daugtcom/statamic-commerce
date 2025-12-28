<?php

namespace Daugt\Commerce\Tests;

use Carbon\Carbon;
use Daugt\Access\Entries\EntitlementEntry;
use Daugt\Access\Services\AccessService;
use Daugt\Access\Blueprints\EntitlementBlueprint;
use Daugt\Commerce\Blueprints\OrderBlueprint;
use Daugt\Commerce\Blueprints\OrderCollection;
use Daugt\Commerce\Blueprints\ProductBlueprint;
use Daugt\Commerce\Blueprints\ProductCollection;
use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Enums\AccessType;
use Daugt\Commerce\Enums\OrderStatus;
use Daugt\Commerce\Services\OrderEntitlementService;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

class OrderEntitlementSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(AccessService::class)) {
            $this->fail('Access addon is required for entitlement sync tests.');
        }

        (new ProductCollection())()->save();
        (new OrderCollection())()->save();

        $productBlueprint = (new ProductBlueprint())([], false);
        $productBlueprint->setHandle('collections/products/product');
        Blueprint::save($productBlueprint);

        $orderBlueprint = (new OrderBlueprint())();
        $orderBlueprint->setHandle('collections/orders/order');
        Blueprint::save($orderBlueprint);

        CollectionFacade::make('entitlements')
            ->entryClass(EntitlementEntry::class)
            ->title('Entitlements')
            ->save();

        if (class_exists(EntitlementBlueprint::class)) {
            $entitlementBlueprint = (new EntitlementBlueprint())();
            $entitlementBlueprint->setHandle('collections/entitlements/entitlement');
            Blueprint::save($entitlementBlueprint);
        }

        CollectionFacade::make('courses')
            ->title('Courses')
            ->save();
    }

    public function test_creates_entitlements_when_order_is_paid(): void
    {
        $user = User::make()->email('entitled@example.test');
        $user->saveQuietly();

        $target = Entry::make()->collection('courses');
        $target->set('title', 'Course A');
        $target->saveQuietly();

        $product = $this->makeProductWithAccessItem($target->id(), [
            'access_type' => AccessType::PERMANENT->value,
        ]);

        $order = $this->makeOrder($user->id(), OrderStatus::PAID->value, [
            $this->orderItem($product->id()),
        ]);

        $entitlements = Entry::query()
            ->where('collection', EntitlementEntry::COLLECTION)
            ->get();

        $this->assertCount(1, $entitlements);
        $entitlement = $entitlements->first();

        $this->assertSame($user->id(), $entitlement->get(EntitlementEntry::USER));
        $this->assertSame($target->id(), $entitlement->get(EntitlementEntry::TARGET));
        $this->assertSame($order->id(), $entitlement->get('order'));
    }

    public function test_revokes_entitlements_when_order_is_not_paid(): void
    {
        $user = User::make()->email('revoked@example.test');
        $user->saveQuietly();

        $target = Entry::make()->collection('courses');
        $target->set('title', 'Course B');
        $target->saveQuietly();

        $product = $this->makeProductWithAccessItem($target->id(), [
            'access_type' => AccessType::PERMANENT->value,
        ]);

        $order = $this->makeOrder($user->id(), OrderStatus::PAID->value, [
            $this->orderItem($product->id()),
        ]);

        $order->set(OrderEntry::STATUS, OrderStatus::FAILED->value);
        $order->save();

        $entitlements = Entry::query()
            ->where('collection', EntitlementEntry::COLLECTION)
            ->get();

        $this->assertCount(0, $entitlements);
    }

    public function test_grants_entitlements_when_status_changes_to_paid(): void
    {
        $user = User::make()->email('status@example.test');
        $user->saveQuietly();

        $target = Entry::make()->collection('courses');
        $target->set('title', 'Course C');
        $target->saveQuietly();

        $product = $this->makeProductWithAccessItem($target->id(), [
            'access_type' => AccessType::PERMANENT->value,
        ]);

        $order = $this->makeOrder($user->id(), OrderStatus::PENDING->value, [
            $this->orderItem($product->id()),
        ]);

        $this->assertCount(0, $this->entitlements());

        $order->set(OrderEntry::STATUS, OrderStatus::PAID->value);
        $order->save();

        $this->assertCount(1, $this->entitlements());
    }

    public function test_creates_date_range_entitlements(): void
    {
        $user = User::make()->email('range@example.test');
        $user->saveQuietly();

        $target = Entry::make()->collection('courses');
        $target->set('title', 'Course D');
        $target->saveQuietly();

        $product = $this->makeProductWithAccessItem($target->id(), [
            'access_type' => AccessType::DATE_RANGE->value,
            'date_range' => [
                'start' => '2024-02-01 00:00:00',
                'end' => '2024-03-01 00:00:00',
            ],
        ]);

        $this->makeOrder($user->id(), OrderStatus::PAID->value, [
            $this->orderItem($product->id()),
        ]);

        $entitlement = $this->entitlements()->first();
        $this->assertSame('2024-02-01 00:00:00', $entitlement->get(EntitlementEntry::VALIDITY_START));
        $this->assertSame('2024-03-01 00:00:00', $entitlement->get(EntitlementEntry::VALIDITY_END));
    }

    public function test_creates_duration_entitlements(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-01 00:00:00'));

        $user = User::make()->email('duration@example.test');
        $user->saveQuietly();

        $target = Entry::make()->collection('courses');
        $target->set('title', 'Course E');
        $target->saveQuietly();

        $product = $this->makeProductWithAccessItem($target->id(), [
            'access_type' => AccessType::DURATION->value,
            'access_duration_iterations' => 2,
            'access_duration_unit' => 'month',
        ]);

        $this->makeOrder($user->id(), OrderStatus::PAID->value, [
            $this->orderItem($product->id()),
        ]);

        $entitlement = $this->entitlements()->first();
        $this->assertSame('2024-01-01 00:00:00', $entitlement->get(EntitlementEntry::VALIDITY_START));
        $this->assertSame('2024-03-01 00:00:00', $entitlement->get(EntitlementEntry::VALIDITY_END));

        Carbon::setTestNow();
    }

    public function test_permanent_access_from_payment_date_sets_validity_range(): void
    {
        $user = User::make()->email('permanent@example.test');
        $user->saveQuietly();

        $target = Entry::make()->collection('courses');
        $target->set('title', 'Course F');
        $target->saveQuietly();

        $product = $this->makeProductWithAccessItem($target->id(), [
            'access_type' => AccessType::PERMANENT->value,
            'access_permanent_from_payment_date' => true,
        ]);

        $order = $this->makeOrder($user->id(), OrderStatus::PAID->value, [
            $this->orderItem($product->id()),
        ], '2024-01-05 10:00:00');

        $entitlement = $this->entitlements()->first();
        $this->assertSame('2024-01-05 10:00:00', $entitlement->get(EntitlementEntry::VALIDITY_START));
        $this->assertNull($entitlement->get(EntitlementEntry::VALIDITY_END));
        $this->assertFalse((bool) $entitlement->get(EntitlementEntry::KEEP_ACCESSIBLE_AFTER_EXPIRY));
        $this->assertFalse((bool) $entitlement->get(EntitlementEntry::KEEP_UNLOCKED_WHEN_ACTIVE));
    }

    public function test_access_flags_are_copied_to_entitlement(): void
    {
        $user = User::make()->email('flags@example.test');
        $user->saveQuietly();

        $target = Entry::make()->collection('courses');
        $target->set('title', 'Course G');
        $target->saveQuietly();

        $product = $this->makeProductWithAccessItem($target->id(), [
            'access_type' => AccessType::DATE_RANGE->value,
            'date_range' => [
                'start' => '2024-02-01 00:00:00',
                'end' => '2024-03-01 00:00:00',
            ],
            'access_keep_accessible_after_expiry' => true,
            'access_keep_unlocked_when_active' => true,
        ]);

        $this->makeOrder($user->id(), OrderStatus::PAID->value, [
            $this->orderItem($product->id()),
        ]);

        $entitlement = $this->entitlements()->first();

        $this->assertTrue((bool) $entitlement->get(EntitlementEntry::KEEP_ACCESSIBLE_AFTER_EXPIRY));
        $this->assertTrue((bool) $entitlement->get(EntitlementEntry::KEEP_UNLOCKED_WHEN_ACTIVE));
    }

    public function test_subscription_end_caps_entitlement_end_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-01-01 00:00:00'));

        $user = User::make()->email('cancelled@example.test');
        $user->saveQuietly();

        $target = Entry::make()->collection('courses');
        $target->set('title', 'Course F');
        $target->saveQuietly();

        $product = $this->makeProductWithAccessItem($target->id(), [
            'access_type' => AccessType::DURATION->value,
            'access_duration_iterations' => 2,
            'access_duration_unit' => 'month',
        ]);

        $order = $this->makeOrder($user->id(), OrderStatus::PAID->value, [
            $this->orderItem($product->id()),
        ]);

        $entitlement = $this->entitlements()->first();
        $this->assertSame('2024-03-01 00:00:00', $entitlement->get(EntitlementEntry::VALIDITY_END));

        $endsAt = Carbon::parse('2024-01-15 00:00:00');
        app(OrderEntitlementService::class)->applySubscriptionEnd($order, (string) $product->id(), $endsAt);

        $entitlement = $this->entitlements()->first();
        $this->assertSame('2024-01-15 00:00:00', $entitlement->get(EntitlementEntry::VALIDITY_END));

        Carbon::setTestNow();
    }

    private function makeProductWithAccessItem(string $targetId, array $accessOverrides): ProductEntry
    {
        $item = array_merge([
            'type' => 'courses',
            'courses' => [$targetId],
            'access_type' => AccessType::PERMANENT->value,
        ], $accessOverrides);

        $product = Entry::make()->collection(ProductEntry::COLLECTION);
        $product->set(ProductEntry::TITLE, 'Product');
        $product->set(ProductEntry::EXTERNAL_PRODUCT, false);
        $product->set(ProductEntry::ALL_ACCESS_ITEMS, [$item]);
        $product->saveQuietly();

        return $product;
    }

    private function makeOrder(string $userId, string $status, array $items, ?string $succeededAt = null): OrderEntry
    {
        $order = Entry::make()->collection(OrderEntry::COLLECTION);
        $order->set(OrderEntry::USER, $userId);
        $order->set(OrderEntry::STATUS, $status);
        $order->set(OrderEntry::ITEMS, $items);
        if ($succeededAt) {
            $order->set(OrderEntry::SUCCEEDED_AT, $succeededAt);
        }
        $order->save();

        return $order;
    }

    private function orderItem(string $productId): array
    {
        return [
            'product' => [$productId],
            'quantity' => 1,
            'shipping_status' => 'pending',
        ];
    }

    private function entitlements()
    {
        return Entry::query()
            ->where('collection', EntitlementEntry::COLLECTION)
            ->get();
    }
}
