<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Blueprints\OrderBlueprint;
use Daugt\Commerce\Blueprints\OrderCollection;
use Daugt\Commerce\Blueprints\ProductBlueprint;
use Daugt\Commerce\Blueprints\ProductCollection;
use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Enums\BillingType;
use Daugt\Commerce\Jobs\StripeWebhooks\CustomerSubscriptionUpdated;
use Spatie\WebhookClient\Models\WebhookCall;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

class StripeWebhookCustomerSubscriptionUpdatedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new ProductCollection())()->save();
        (new OrderCollection())()->save();

        $orderCollection = CollectionFacade::find(OrderEntry::COLLECTION);
        $orderCollection->titleFormats([]);
        $orderCollection->save();

        $productBlueprint = (new ProductBlueprint())([], false);
        $productBlueprint->setHandle('collections/products/product');
        Blueprint::save($productBlueprint);

        $orderBlueprint = (new OrderBlueprint())();
        $orderBlueprint->setHandle('collections/orders/order');
        Blueprint::save($orderBlueprint);
    }

    public function test_subscription_updated_sets_subscription_id_on_order_item(): void
    {
        $user = User::make()->email('sub@example.test');
        $user->set('stripe_id', 'cus_sub');
        $user->saveQuietly();

        $product = $this->makeProduct('Sub Product', 'price_sub', 'prod_sub');

        $order = Entry::make()->collection(OrderEntry::COLLECTION);
        $order->blueprint(Blueprint::in('collections/orders')->first());
        $order->set(OrderEntry::USER, $user->id());
        $order->set(OrderEntry::ITEMS, [
            [
                'type' => 'item',
                'product' => [$product->id()],
                'quantity' => 1,
            ],
        ]);
        $order->saveQuietly();

        $payload = [
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_789',
                    'customer' => 'cus_sub',
                    'items' => [
                        'data' => [
                            [
                                'price' => [
                                    'id' => 'price_sub',
                                    'product' => 'prod_sub',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $job = new CustomerSubscriptionUpdated(new WebhookCall(['payload' => $payload]));
        $job->handle();

        $updated = Entry::find($order->id());
        $items = $updated->get(OrderEntry::ITEMS);
        $this->assertSame('sub_789', $items[0]['stripe_subscription_id'] ?? null);
    }

    private function makeProduct(string $title, string $priceId, string $productId): ProductEntry
    {
        $entry = Entry::make()->collection(ProductEntry::COLLECTION);
        $entry->set(ProductEntry::TITLE, $title);
        $entry->set(ProductEntry::BILLING_TYPE, BillingType::RECURRING->value);
        $entry->set(ProductEntry::STRIPE_PRICE_ID, $priceId);
        $entry->set(ProductEntry::STRIPE_PRODUCT_ID, $productId);
        $entry->set(ProductEntry::EXTERNAL_PRODUCT, false);
        $entry->saveQuietly();

        return $entry;
    }
}
