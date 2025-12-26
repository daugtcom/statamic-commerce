<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Blueprints\InvoiceBlueprint;
use Daugt\Commerce\Blueprints\InvoiceCollection;
use Daugt\Commerce\Blueprints\OrderBlueprint;
use Daugt\Commerce\Blueprints\OrderCollection;
use Daugt\Commerce\Blueprints\ProductBlueprint;
use Daugt\Commerce\Blueprints\ProductCollection;
use Daugt\Commerce\Entries\InvoiceEntry;
use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Enums\BillingType;
use Daugt\Commerce\Enums\OrderStatus;
use Daugt\Commerce\Enums\ShippingStatus;
use Daugt\Commerce\Jobs\StripeWebhooks\CheckoutSessionCompleted;
use Spatie\WebhookClient\Models\WebhookCall;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Stripe\StripeClient;

class StripeWebhookCheckoutSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new ProductCollection())()->save();
        (new OrderCollection())()->save();
        $orderCollection = CollectionFacade::find(OrderEntry::COLLECTION);
        $orderCollection->titleFormats([]);
        $orderCollection->save();
        (new InvoiceCollection())()->save();

        $productBlueprint = (new ProductBlueprint())([], false);
        $productBlueprint->setHandle('collections/products/product');
        Blueprint::save($productBlueprint);

        $orderBlueprint = (new OrderBlueprint())();
        $orderBlueprint->setHandle('collections/orders/order');
        Blueprint::save($orderBlueprint);

        $invoiceBlueprint = (new InvoiceBlueprint())();
        $invoiceBlueprint->setHandle('collections/invoices/invoice');
        Blueprint::save($invoiceBlueprint);
    }

    public function test_checkout_session_creates_order_and_invoice(): void
    {
        $user = User::make()->email('stripe@example.test');
        $user->set('stripe_id', 'cus_123');
        $user->saveQuietly();

        $oneTime = $this->makeProduct('One-Time', BillingType::ONE_TIME->value, 'price_one', 'prod_one');
        $recurring = $this->makeProduct('Recurring', BillingType::RECURRING->value, 'price_sub', 'prod_sub');

        $existing = Entry::make()->collection(OrderEntry::COLLECTION);
        $orderBlueprint = Blueprint::in('collections/orders')->first();
        $this->assertNotNull($orderBlueprint);
        $existing->blueprint($orderBlueprint);
        $existing->set(OrderEntry::USER, $user->id());
        $existing->set(OrderEntry::STRIPE_CHECKOUT_SESSION_ID, 'cs_test_123');
        $existing->set(OrderEntry::ITEMS, [
            [
                'type' => 'item',
                'product' => [$oneTime->id()],
                'quantity' => 1,
                'shipping_status' => ShippingStatus::SHIPPED->value,
            ],
        ]);
        $existing->saveQuietly();

        $payload = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'customer' => 'cus_123',
                    'payment_status' => 'paid',
                    'invoice' => 'in_123',
                    'payment_intent' => 'pi_123',
                    'subscription' => 'sub_123',
                    'customer_details' => [
                        'name' => 'Ada Lovelace',
                        'phone' => '+123456',
                        'address' => [
                            'line1' => '1 Main St',
                            'city' => 'London',
                            'postal_code' => '12345',
                            'country' => 'GB',
                        ],
                    ],
                    'shipping_details' => [
                        'name' => 'Ada Lovelace',
                        'phone' => '+123456',
                        'address' => [
                            'line1' => '2 Side St',
                            'city' => 'London',
                            'postal_code' => '67890',
                            'country' => 'GB',
                        ],
                    ],
                ],
            ],
        ];

        $lineItems = [
            [
                'quantity' => 1,
                'price' => [
                    'id' => 'price_one',
                    'product' => 'prod_one',
                    'recurring' => null,
                ],
            ],
            [
                'quantity' => 1,
                'price' => [
                    'id' => 'price_sub',
                    'product' => 'prod_sub',
                    'recurring' => ['interval' => 'month'],
                ],
            ],
        ];

        $stripeClient = new FakeStripeClient([
            'checkout' => new FakeCheckoutService($lineItems),
            'invoices' => new FakeInvoicesService(),
        ]);

        $job = new CheckoutSessionCompleted(new WebhookCall(['payload' => $payload]));
        $job->handle($stripeClient);

        $order = Entry::query()
            ->where('collection', OrderEntry::COLLECTION)
            ->where(OrderEntry::STRIPE_CHECKOUT_SESSION_ID, 'cs_test_123')
            ->first();

        $this->assertInstanceOf(OrderEntry::class, $order);
        $this->assertSame(OrderStatus::PAID->value, $order->get(OrderEntry::STATUS));
        $this->assertNotNull($order->get(OrderEntry::SUCCEEDED_AT));

        $items = $order->get(OrderEntry::ITEMS);
        $this->assertCount(2, $items);

        $oneTimeItem = $this->itemForProduct($items, (string) $oneTime->id());
        $this->assertSame(ShippingStatus::SHIPPED->value, $oneTimeItem['shipping_status'] ?? null);

        $recurringItem = $this->itemForProduct($items, (string) $recurring->id());
        $this->assertSame('sub_123', $recurringItem['stripe_subscription_id'] ?? null);

        $billing = $order->get(OrderEntry::BILLING_ADDRESS);
        $this->assertSame('Ada Lovelace', $billing['name'] ?? null);
        $this->assertSame('1 Main St', $billing['line1'] ?? null);

        $shipping = $order->get(OrderEntry::SHIPPING_ADDRESS);
        $this->assertSame('2 Side St', $shipping['line1'] ?? null);

        $invoice = Entry::query()
            ->where('collection', InvoiceEntry::COLLECTION)
            ->where(InvoiceEntry::STRIPE_INVOICE_ID, 'in_123')
            ->first();

        $this->assertInstanceOf(InvoiceEntry::class, $invoice);
        $this->assertSame($order->id(), $this->firstId($invoice->get(InvoiceEntry::ORDER)));
        $this->assertSame($user->id(), $this->firstId($invoice->get(InvoiceEntry::USER)));
        $this->assertSame(OrderStatus::PAID->value, $invoice->get(InvoiceEntry::STATUS));
        $this->assertSame('pi_123', $invoice->get(InvoiceEntry::STRIPE_PAYMENT_INTENT_ID));

        $refreshedUser = User::find($user->id());
        $this->assertSame('1 Main St', $refreshedUser->get('billing_address')['line1'] ?? null);
        $this->assertSame('2 Side St', $refreshedUser->get('shipping_address')['line1'] ?? null);
    }

    private function makeProduct(string $title, string $billingType, string $priceId, string $productId): ProductEntry
    {
        $entry = Entry::make()->collection(ProductEntry::COLLECTION);
        $entry->set(ProductEntry::TITLE, $title);
        $entry->set(ProductEntry::BILLING_TYPE, $billingType);
        $entry->set(ProductEntry::STRIPE_PRICE_ID, $priceId);
        $entry->set(ProductEntry::STRIPE_PRODUCT_ID, $productId);
        $entry->set(ProductEntry::EXTERNAL_PRODUCT, false);
        $entry->saveQuietly();

        return $entry;
    }

    private function itemForProduct(mixed $items, string $productId): array
    {
        if (! is_array($items)) {
            return [];
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if ($this->firstId($item['product'] ?? null) === $productId) {
                return $item;
            }
        }

        return [];
    }

    private function firstId(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}

class FakeStripeClient extends StripeClient
{
    public function __construct(private array $services)
    {
        parent::__construct('sk_test');
    }

    public function getService($name)
    {
        return $this->services[$name] ?? parent::getService($name);
    }
}

class FakeCheckoutService
{
    public function __construct(array $lineItems)
    {
        $this->sessions = new FakeCheckoutSessionsService($lineItems);
    }

    public FakeCheckoutSessionsService $sessions;
}

class FakeCheckoutSessionsService
{
    public function __construct(private array $lineItems)
    {
    }

    public function allLineItems(string $sessionId, array $params = []): array
    {
        return ['data' => $this->lineItems];
    }
}

class FakeInvoicesService
{
    public array $updated = [];

    public function update(string $invoiceId, array $params = []): array
    {
        $this->updated[] = ['id' => $invoiceId, 'params' => $params];

        return ['id' => $invoiceId];
    }
}
