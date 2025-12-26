<?php

namespace Daugt\Commerce\Jobs\StripeWebhooks;

use Daugt\Commerce\Entries\OrderEntry;
use Statamic\Facades\Entry;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

abstract class StripeWebhookJob extends ProcessWebhookJob
{
    protected function payload(): array
    {
        return $this->webhookCall->payload ?? [];
    }

    protected function findOrderBySubscriptionId(string $subscriptionId): ?OrderEntry
    {
        if ($subscriptionId === '') {
            return null;
        }

        $orders = Entry::query()
            ->where('collection', OrderEntry::COLLECTION)
            ->get();

        foreach ($orders as $order) {
            if (! $order instanceof OrderEntry) {
                continue;
            }

            if ($this->orderHasSubscriptionId($order, $subscriptionId)) {
                return $order;
            }
        }

        return null;
    }

    private function orderHasSubscriptionId(OrderEntry $order, string $subscriptionId): bool
    {
        $items = $order->get(OrderEntry::ITEMS);
        if (! is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if ((string) ($item['stripe_subscription_id'] ?? '') === $subscriptionId) {
                return true;
            }
        }

        return false;
    }
}
