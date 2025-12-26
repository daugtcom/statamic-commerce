<?php

namespace Daugt\Commerce\Jobs\StripeWebhooks;

use Daugt\Commerce\Entries\InvoiceEntry;
use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Enums\BillingType;
use Daugt\Commerce\Enums\OrderStatus;
use Daugt\Commerce\Enums\ShippingStatus;
use Daugt\Commerce\Support\StripeAddress;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Stripe\StripeClient;

class CheckoutSessionCompleted extends StripeWebhookJob
{
    public function handle(StripeClient $stripeClient): void
    {
        $payload = $this->payload();
        $session = $payload['data']['object'] ?? null;

        if (! is_array($session)) {
            return;
        }

        $sessionId = (string) ($session['id'] ?? '');
        $customerId = (string) ($session['customer'] ?? '');

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
        $items = $this->buildItems($lineItems, $existingItems, $session);

        $order->set(OrderEntry::USER, $user->id());
        $order->set(OrderEntry::STATUS, $status);
        $order->set(OrderEntry::STRIPE_CHECKOUT_SESSION_ID, $sessionId);
        $order->set(OrderEntry::SUCCEEDED_AT, $status === OrderStatus::PAID->value ? now() : null);
        $order->set(OrderEntry::ITEMS, $items);

        $billingAddress = StripeAddress::fromCustomerDetails($session['customer_details'] ?? null);
        if ($billingAddress !== []) {
            $order->set(OrderEntry::BILLING_ADDRESS, $billingAddress);
        }

        $shippingAddress = StripeAddress::fromShippingDetails($session['shipping_details'] ?? null);
        if ($shippingAddress !== []) {
            $order->set(OrderEntry::SHIPPING_ADDRESS, $shippingAddress);
        }

        $order->save();

        $this->syncInvoice($stripeClient, $order, $user->id(), $session, $status);
        $this->syncUserAddresses($user, $session);
    }

    private function resolveStatus(array $payload, array $session): string
    {
        $paymentStatus = (string) ($session['payment_status'] ?? '');
        if ($paymentStatus === 'paid') {
            return OrderStatus::PAID->value;
        }

        $type = (string) ($payload['type'] ?? '');
        if ($type === 'checkout.session.async_payment_failed' || $type === 'checkout.session.expired') {
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
        $items = $response['data'] ?? [];

        return is_array($items) ? $items : [];
    }

    private function buildItems(array $lineItems, array $existingItems, array $session): array
    {
        $items = [];
        $subscriptionId = $session['subscription'] ?? null;

        foreach ($lineItems as $lineItem) {
            if (! is_array($lineItem)) {
                continue;
            }

            $product = $this->findProduct($lineItem);
            if (! $product) {
                continue;
            }

            $productId = (string) $product->id();
            $existing = $existingItems[$productId] ?? [];
            $shippingStatus = $existing['shipping_status'] ?? ShippingStatus::PENDING->value;

            $isRecurring = ! empty($lineItem['price']['recurring']);

            $items[] = array_filter([
                'type' => 'item',
                'product' => [$productId],
                'quantity' => (int) ($lineItem['quantity'] ?? 1),
                'shipping_status' => $shippingStatus,
                'stripe_subscription_id' => $isRecurring && is_string($subscriptionId) ? $subscriptionId : null,
            ], fn ($value) => $value !== null);
        }

        return $items;
    }

    private function findProduct(array $lineItem): ?ProductEntry
    {
        $price = $lineItem['price'] ?? [];
        $priceId = $price['id'] ?? null;
        $stripeProductId = $price['product'] ?? null;
        $billingType = ! empty($price['recurring']) ? BillingType::RECURRING->value : BillingType::ONE_TIME->value;

        $query = Entry::query()
            ->where('collection', ProductEntry::COLLECTION)
            ->where(ProductEntry::BILLING_TYPE, $billingType);

        if (is_string($priceId) && $priceId !== '') {
            $match = $query->where(ProductEntry::STRIPE_PRICE_ID, $priceId)->first();
            if ($match instanceof ProductEntry) {
                return $match;
            }
        }

        if (is_string($stripeProductId) && $stripeProductId !== '') {
            $match = $query->where(ProductEntry::STRIPE_PRODUCT_ID, $stripeProductId)->first();
            if ($match instanceof ProductEntry) {
                return $match;
            }
        }

        return null;
    }

    private function indexExistingItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $indexed = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $productId = $this->firstId($item['product'] ?? null);
            if (! $productId) {
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

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    private function syncInvoice(StripeClient $stripeClient, OrderEntry $order, string $userId, array $session, string $status): void
    {
        $invoiceId = $session['invoice'] ?? null;
        if (! is_string($invoiceId) || $invoiceId === '') {
            return;
        }

        $invoice = Entry::query()
            ->where('collection', InvoiceEntry::COLLECTION)
            ->where(InvoiceEntry::STRIPE_INVOICE_ID, $invoiceId)
            ->first()
            ?? Entry::make()->collection(InvoiceEntry::COLLECTION);

        $invoice->set(InvoiceEntry::ORDER, $order->id());
        $invoice->set(InvoiceEntry::USER, $userId);
        $invoice->set(InvoiceEntry::STATUS, $status);
        $invoice->set(InvoiceEntry::STRIPE_PAYMENT_INTENT_ID, $session['payment_intent'] ?? null);
        $invoice->set(InvoiceEntry::STRIPE_INVOICE_ID, $invoiceId);
        $invoice->save();

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

    private function syncUserAddresses($user, array $session): void
    {
        $billing = StripeAddress::fromCustomerDetails($session['customer_details'] ?? null);
        $shipping = StripeAddress::fromShippingDetails($session['shipping_details'] ?? null);

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
}
