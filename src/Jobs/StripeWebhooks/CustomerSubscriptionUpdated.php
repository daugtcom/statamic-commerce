<?php

namespace Daugt\Commerce\Jobs\StripeWebhooks;

use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Enums\BillingType;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

class CustomerSubscriptionUpdated extends StripeWebhookJob
{
    public function handle(): void
    {
        $payload = $this->payload();
        $subscription = $payload['data']['object'] ?? null;

        if (! is_array($subscription)) {
            return;
        }

        $subscriptionId = (string) ($subscription['id'] ?? '');
        if ($subscriptionId === '') {
            return;
        }

        if ($this->findOrderBySubscriptionId($subscriptionId)) {
            return;
        }

        $customerId = (string) ($subscription['customer'] ?? '');
        if ($customerId === '') {
            return;
        }

        $user = User::query()->where('stripe_id', $customerId)->first();
        if (! $user) {
            return;
        }

        $price = $this->firstPrice($subscription);
        $product = $price ? $this->findProduct($price) : null;
        if (! $product) {
            return;
        }

        $order = $this->findOrderForUserProduct($user->id(), (string) $product->id());
        if (! $order) {
            $this->release(10);
            return;
        }

        if ($this->setSubscriptionIdForProduct($order, (string) $product->id(), $subscriptionId)) {
            $order->save();
        }
    }

    private function firstPrice(array $subscription): ?array
    {
        $items = $subscription['items']['data'] ?? null;
        if (is_array($items) && isset($items[0]) && is_array($items[0])) {
            $price = $items[0]['price'] ?? null;
            if (is_array($price)) {
                return $price;
            }
        }

        $plan = $subscription['plan'] ?? null;
        if (is_array($plan)) {
            return [
                'id' => $plan['id'] ?? null,
                'product' => $plan['product'] ?? null,
            ];
        }

        return null;
    }

    private function findProduct(array $price): ?ProductEntry
    {
        $priceId = $price['id'] ?? null;
        $stripeProductId = $price['product'] ?? null;

        $query = Entry::query()
            ->where('collection', ProductEntry::COLLECTION)
            ->where(ProductEntry::BILLING_TYPE, BillingType::RECURRING->value);

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

    private function findOrderForUserProduct(string $userId, string $productId): ?OrderEntry
    {
        $orders = Entry::query()
            ->where('collection', OrderEntry::COLLECTION)
            ->where(OrderEntry::USER, $userId)
            ->get();

        foreach ($orders as $order) {
            if (! $order instanceof OrderEntry) {
                continue;
            }

            if ($this->orderHasProduct($order, $productId)) {
                return $order;
            }
        }

        return null;
    }

    private function orderHasProduct(OrderEntry $order, string $productId): bool
    {
        $items = $order->get(OrderEntry::ITEMS);
        if (! is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if ($this->firstId($item['product'] ?? null) === $productId) {
                return true;
            }
        }

        return false;
    }

    private function setSubscriptionIdForProduct(OrderEntry $order, string $productId, string $subscriptionId): bool
    {
        $items = $order->get(OrderEntry::ITEMS);
        if (! is_array($items)) {
            return false;
        }

        $changed = false;

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            if ($this->firstId($item['product'] ?? null) !== $productId) {
                continue;
            }

            if (($item['stripe_subscription_id'] ?? null) === $subscriptionId) {
                continue;
            }

            $item['stripe_subscription_id'] = $subscriptionId;
            $items[$index] = $item;
            $changed = true;
        }

        if ($changed) {
            $order->set(OrderEntry::ITEMS, $items);
        }

        return $changed;
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
