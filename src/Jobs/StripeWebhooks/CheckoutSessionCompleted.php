<?php

namespace Daugt\Commerce\Jobs\StripeWebhooks;

use Daugt\Commerce\Entries\InvoiceEntry;
use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Enums\OrderStatus;
use Daugt\Commerce\Enums\ShippingStatus;
use Daugt\Commerce\Services\StripeLineItemProductResolver;
use Daugt\Commerce\Support\StripeAddress;
use Daugt\Commerce\Support\StripePayload;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Stripe\StripeClient;

class CheckoutSessionCompleted extends StripeWebhookJob
{
    public function handle(StripeClient $stripeClient, StripeLineItemProductResolver $productResolver): void
    {
        $payload = StripePayload::array($this->payload());
        $type = StripePayload::string($payload, 'type');
        if ($type === 'checkout.session.expired') {
            return;
        }

        $session = StripePayload::array($payload, 'data.object');
        $sessionId = StripePayload::string($session, 'id');
        $customerId = StripePayload::string($session, 'customer');

        if ($sessionId === '' || $customerId === '') {
            return;
        }

        $user = User::query()->where('stripe_id', $customerId)->first();
        if (! $user) {
            return;
        }

        $status = $this->resolveStatus($payload, $session);
        $order = $this->findOrder($sessionId, $user->id())
            ?? Entry::make()->collection(OrderEntry::COLLECTION);

        $existingItems = $this->indexExistingItems($order->get(OrderEntry::ITEMS));
        $lineItems = $this->fetchLineItems($stripeClient, $sessionId);
        $items = $this->buildItems($lineItems, $existingItems, $session, $productResolver);

        $order->set(OrderEntry::USER, $user->id());
        $order->set(OrderEntry::STATUS, $status);
        $order->set(OrderEntry::STRIPE_CHECKOUT_SESSION_ID, $sessionId);
        $order->set(OrderEntry::SUCCEEDED_AT, $status === OrderStatus::PAID->value ? now() : null);
        $order->set(OrderEntry::ITEMS, $items);

        $billingAddress = StripeAddress::fromCustomerDetails(
            StripePayload::array($session, 'customer_details')
        );
        if ($billingAddress !== []) {
            $order->set(OrderEntry::BILLING_ADDRESS, $billingAddress);
        }

        $shippingAddress = StripeAddress::fromShippingDetails(
            StripePayload::array($session, 'shipping_details')
        );
        if ($shippingAddress !== []) {
            $order->set(OrderEntry::SHIPPING_ADDRESS, $shippingAddress);
        }

        $this->ensureEntryBlueprint($order);
        $order->save();

        $this->syncInvoice($stripeClient, $order, $user->id(), $session, $status);
        $this->syncUserAddresses($user, $session);
    }

    private function resolveStatus(array $payload, array $session): string
    {
        $paymentStatus = StripePayload::string($session, 'payment_status');
        if ($paymentStatus === 'paid') {
            return OrderStatus::PAID->value;
        }

        $type = StripePayload::string($payload, 'type');
        if ($type === 'checkout.session.async_payment_failed') {
            return OrderStatus::FAILED->value;
        }

        return OrderStatus::PENDING->value;
    }

    private function findOrder(string $sessionId, string $userId): mixed
    {
        return Entry::query()
            ->where('collection', OrderEntry::COLLECTION)
            ->where(OrderEntry::STRIPE_CHECKOUT_SESSION_ID, $sessionId)
            ->where(OrderEntry::USER, $userId)
            ->first();
    }

    private function fetchLineItems(StripeClient $stripeClient, string $sessionId): array
    {
        $response = $stripeClient->checkout->sessions->allLineItems($sessionId, ['limit' => 100]);
        $items = StripePayload::array($response, 'data');

        return $this->normalizeLineItems($items);
    }

    private function buildItems(
        array $lineItems,
        array $existingItems,
        array $session,
        StripeLineItemProductResolver $productResolver
    ): array
    {
        $items = [];
        $subscriptionId = StripePayload::string($session, 'subscription');

        foreach ($lineItems as $lineItem) {
            $lineItem = $this->normalizeLineItem($lineItem);
            if (! $lineItem) {
                continue;
            }

            $product = $productResolver->resolve($lineItem);
            if (! $product) {
                continue;
            }

            $productId = (string) $product->id();
            $existing = $existingItems[$productId] ?? [];
            $requiresShipping = $product->shipping();
            $shippingStatus = $requiresShipping
                ? ($existing['shipping_status'] ?? ShippingStatus::PENDING->value)
                : null;

            $isRecurring = StripePayload::array($lineItem, 'price.recurring') !== [];

            $items[] = array_filter([
                'type' => 'item',
                'product' => [$productId],
                'quantity' => (int) (StripePayload::int($lineItem, 'quantity') ?? 1),
                'shipping_status' => $shippingStatus,
                'stripe_subscription_id' => $isRecurring && $subscriptionId !== '' ? $subscriptionId : null,
            ], fn ($value) => $value !== null);
        }

        return $items;
    }

    private function indexExistingItems(mixed $items): array
    {
        $items = StripePayload::array($items);
        $indexed = [];

        foreach ($items as $item) {
            $item = StripePayload::array($item);
            $productId = $this->firstId($item['product'] ?? null);
            if ($productId === '') {
                continue;
            }

            $indexed[$productId] = $item;
        }

        return $indexed;
    }

    private function firstId(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return StripePayload::string($value) ?: null;
    }

    private function syncInvoice(StripeClient $stripeClient, OrderEntry $order, string $userId, array $session, string $status): void
    {
        $invoiceId = StripePayload::string($session, 'invoice');
        if ($invoiceId === '') {
            return;
        }

        $invoice = Entry::query()
            ->where('collection', InvoiceEntry::COLLECTION)
            ->where(InvoiceEntry::STRIPE_INVOICE_ID, $invoiceId)
            ->first()
            ?? Entry::make()->collection(InvoiceEntry::COLLECTION);

        $invoiceNumber = $this->resolveInvoiceNumber($stripeClient, $invoiceId) ?: $invoiceId;

        $invoice->set(InvoiceEntry::ORDER, $order->id());
        $invoice->set(InvoiceEntry::USER, $userId);
        $invoice->set(InvoiceEntry::STATUS, $status);
        $invoice->set(InvoiceEntry::NUMBER, $invoiceNumber);
        $paymentIntentId = StripePayload::string($session, 'payment_intent');
        $invoice->set(InvoiceEntry::STRIPE_PAYMENT_INTENT_ID, $paymentIntentId ?: null);
        $invoice->set(InvoiceEntry::STRIPE_INVOICE_ID, $invoiceId);

        if (! $this->ensureEntryBlueprint($invoice)) {
            return;
        }

        $this->ensureInvoiceTitleFormat();
        $invoice->save();
        $this->attachInvoiceToOrder($order, (string) $invoice->id());

        try {
            $stripeClient->invoices->update($invoiceId, [
                'metadata' => [
                    'order_id' => $order->id(),
                ],
            ]);
        } catch (\Throwable) {
            // Ignore metadata update failures.
        }
    }

    private function attachInvoiceToOrder(OrderEntry $order, string $invoiceEntryId): void
    {
        if ($invoiceEntryId === '') {
            return;
        }

        $invoices = $order->get(OrderEntry::INVOICES);
        if (! is_array($invoices)) {
            $invoices = [];
        }

        if (! in_array($invoiceEntryId, $invoices, true)) {
            $invoices[] = $invoiceEntryId;
            $order->set(OrderEntry::INVOICES, $invoices);
            $order->saveQuietly();
        }
    }

    private function syncUserAddresses($user, array $session): void
    {
        $billing = StripeAddress::fromCustomerDetails(
            StripePayload::array($session, 'customer_details')
        );
        $shipping = StripeAddress::fromShippingDetails(
            StripePayload::array($session, 'shipping_details')
        );

        if ($billing !== []) {
            $user->set('billing_address', $billing);
        }

        if ($shipping !== []) {
            $user->set('shipping_address', $shipping);
        }

        if ($billing !== [] || $shipping !== []) {
            $user->saveQuietly();
        }
    }

    private function resolveInvoiceNumber(StripeClient $stripeClient, string $invoiceId): ?string
    {
        try {
            $invoice = $stripeClient->invoices->retrieve($invoiceId);
        } catch (\Throwable) {
            return null;
        }

        $invoice = StripePayload::array($invoice);

        return StripePayload::string($invoice, 'number') ?: null;
    }

    private function normalizeLineItems(mixed $items): array
    {
        $items = StripePayload::array($items);
        $normalized = [];

        foreach ($items as $item) {
            $item = $this->normalizeLineItem($item);
            if ($item !== []) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }

    private function normalizeLineItem(mixed $item): array
    {
        $item = StripePayload::array($item);
        if ($item === []) {
            return [];
        }

        $item['price'] = StripePayload::array($item, 'price');

        return $item;
    }
}
