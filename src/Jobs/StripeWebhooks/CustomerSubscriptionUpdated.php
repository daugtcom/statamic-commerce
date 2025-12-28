<?php

namespace Daugt\Commerce\Jobs\StripeWebhooks;

use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Enums\BillingType;
use Daugt\Commerce\Services\OrderEntitlementService;
use Daugt\Commerce\Support\StripePayload;
use Carbon\Carbon;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

class CustomerSubscriptionUpdated extends StripeWebhookJob
{
    public function handle(): void
    {
        $payload = StripePayload::array($this->payload());
        $subscription = StripePayload::array($payload, 'data.object');
        $subscriptionId = StripePayload::string($subscription, 'id');
        if ($subscriptionId === '') {
            return;
        }

        $order = $this->findOrderBySubscriptionId($subscriptionId);
        $endsAt = $this->resolveSubscriptionEnd($subscription);

        if ($order) {
            if ($endsAt) {
                foreach ($this->orderProductIdsForSubscription($order, $subscriptionId) as $productId) {
                    app(OrderEntitlementService::class)->applySubscriptionEnd($order, $productId, $endsAt);
                }
            }

            return;
        }

        $customerId = StripePayload::string($subscription, 'customer');
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

        if ($endsAt) {
            app(OrderEntitlementService::class)->applySubscriptionEnd($order, (string) $product->id(), $endsAt);
        }
    }

    private function firstPrice(array $subscription): ?array
    {
        $items = StripePayload::array($subscription, 'items.data');
        $firstItem = $items[0] ?? null;
        $price = StripePayload::array($firstItem, 'price');

        if ($price !== []) {
            return $price;
        }

        $plan = StripePayload::array($subscription, 'plan');
        if ($plan !== []) {
            return [
                'id' => StripePayload::string($plan, 'id'),
                'product' => StripePayload::string($plan, 'product'),
            ];
        }

        return null;
    }

    private function findProduct(array $price): ?ProductEntry
    {
        $priceId = StripePayload::string($price, 'id');
        $stripeProductId = StripePayload::string($price, 'product');

        $query = Entry::query()
            ->where('collection', ProductEntry::COLLECTION)
            ->where(ProductEntry::BILLING_TYPE, BillingType::RECURRING->value);

        if ($priceId !== '') {
            $match = $query->where(ProductEntry::STRIPE_PRICE_ID, $priceId)->first();
            if ($match instanceof ProductEntry) {
                return $match;
            }
        }

        if ($stripeProductId !== '') {
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
        $items = StripePayload::array($order->get(OrderEntry::ITEMS));

        foreach ($items as $item) {
            $item = StripePayload::array($item);
            if ($this->firstId($item['product'] ?? null) === $productId) {
                return true;
            }
        }

        return false;
    }

    private function setSubscriptionIdForProduct(OrderEntry $order, string $productId, string $subscriptionId): bool
    {
        $items = StripePayload::array($order->get(OrderEntry::ITEMS));
        if ($items === []) {
            return false;
        }

        $changed = false;

        foreach ($items as $index => $item) {
            $item = StripePayload::array($item);
            if ($item === []) {
                continue;
            }

            if ($this->firstId($item['product'] ?? null) !== $productId) {
                continue;
            }

            if (StripePayload::string($item, 'stripe_subscription_id') === $subscriptionId) {
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

    private function orderProductIdsForSubscription(OrderEntry $order, string $subscriptionId): array
    {
        $items = StripePayload::array($order->get(OrderEntry::ITEMS));

        $productIds = [];

        foreach ($items as $item) {
            $item = StripePayload::array($item);

            if (StripePayload::string($item, 'stripe_subscription_id') !== $subscriptionId) {
                continue;
            }

            $productId = $this->firstId($item['product'] ?? null);
            if ($productId) {
                $productIds[] = $productId;
            }
        }

        return array_values(array_unique($productIds));
    }

    private function firstId(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return StripePayload::string($value) ?: null;
    }

    private function resolveSubscriptionEnd(array $subscription): ?Carbon
    {
        $endedAt = StripePayload::int($subscription, 'ended_at');
        if ($endedAt) {
            return Carbon::createFromTimestamp($endedAt);
        }

        $cancelAt = StripePayload::int($subscription, 'cancel_at');
        if ($cancelAt) {
            return Carbon::createFromTimestamp($cancelAt);
        }

        return null;
    }
}
