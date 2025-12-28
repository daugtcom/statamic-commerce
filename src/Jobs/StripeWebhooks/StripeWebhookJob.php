<?php

namespace Daugt\Commerce\Jobs\StripeWebhooks;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Entries\InvoiceEntry;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;

abstract class StripeWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(protected array $payload)
    {
    }

    protected function payload(): array
    {
        return $this->payload ?? [];
    }

    protected function ensureEntryBlueprint($entry): bool
    {
        if (! method_exists($entry, 'collection')) {
            return false;
        }

        $collection = $entry->collection();
        if (! $collection) {
            return false;
        }

        $handle = $entry->get('blueprint');
        if (is_string($handle) && $handle !== '') {
            return true;
        }

        $blueprint = $collection->entryBlueprint();
        if (! $blueprint) {
            return false;
        }

        $entry->blueprint($blueprint->handle());

        return true;
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

    protected function ensureInvoiceTitleFormat(): void
    {
        $collection = CollectionFacade::find(InvoiceEntry::COLLECTION);
        if (! $collection) {
            return;
        }

        $format = 'Invoice #{{ if invoice_number }}{{ invoice_number }}{{ elseif stripe_invoice_id }}{{ stripe_invoice_id }}{{ else }}{{ id }}{{ /if }}';
        $site = $collection->sites()->first();
        if (is_string($site)) {
            $siteHandle = $site;
        } elseif (is_object($site) && method_exists($site, 'handle')) {
            $siteHandle = $site->handle();
        } else {
            $siteHandle = null;
        }

        $current = $siteHandle ? $collection->titleFormat($siteHandle) : null;

        if ($current !== $format) {
            $collection->titleFormats($format);
            $collection->save();
        }
    }
}
