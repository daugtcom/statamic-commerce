<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Blueprints\InvoiceBlueprint;
use Daugt\Commerce\Blueprints\InvoiceCollection;
use Daugt\Commerce\Blueprints\OrderBlueprint;
use Daugt\Commerce\Blueprints\OrderCollection;
use Daugt\Commerce\Entries\InvoiceEntry;
use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Enums\InvoiceStatus;
use Daugt\Commerce\Jobs\StripeWebhooks\InvoiceEvent;
use Spatie\WebhookClient\Models\WebhookCall;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

class StripeWebhookInvoiceEventTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new OrderCollection())()->save();
        (new InvoiceCollection())()->save();

        $orderBlueprint = (new OrderBlueprint())();
        $orderBlueprint->setHandle('collections/orders/order');
        Blueprint::save($orderBlueprint);

        $invoiceBlueprint = (new InvoiceBlueprint())();
        $invoiceBlueprint->setHandle('collections/invoices/invoice');
        Blueprint::save($invoiceBlueprint);
    }

    public function test_invoice_event_creates_invoice_entry(): void
    {
        $user = User::make()->email('invoice@example.test');
        $user->set('stripe_id', 'cus_456');
        $user->saveQuietly();

        $order = Entry::make()->collection(OrderEntry::COLLECTION);
        $order->set(OrderEntry::USER, $user->id());
        $order->set(OrderEntry::ITEMS, [
            [
                'type' => 'item',
                'product' => ['prod_1'],
                'quantity' => 1,
                'stripe_subscription_id' => 'sub_456',
            ],
        ]);
        $order->saveQuietly();

        $payload = [
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'in_456',
                    'subscription' => 'sub_456',
                    'payment_intent' => 'pi_456',
                    'status' => 'open',
                ],
            ],
        ];

        $job = new InvoiceEvent(new WebhookCall(['payload' => $payload]));
        $job->handle();

        $invoice = Entry::query()
            ->where('collection', InvoiceEntry::COLLECTION)
            ->where(InvoiceEntry::STRIPE_INVOICE_ID, 'in_456')
            ->first();

        $this->assertInstanceOf(InvoiceEntry::class, $invoice);
        $this->assertSame(InvoiceStatus::FAILED->value, $invoice->get(InvoiceEntry::STATUS));
        $this->assertSame($order->id(), $this->firstId($invoice->get(InvoiceEntry::ORDER)));
        $this->assertSame($user->id(), $this->firstId($invoice->get(InvoiceEntry::USER)));
        $this->assertSame('pi_456', $invoice->get(InvoiceEntry::STRIPE_PAYMENT_INTENT_ID));
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
