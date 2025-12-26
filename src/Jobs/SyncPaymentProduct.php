<?php

namespace Daugt\Commerce\Jobs;

use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Payments\DTO\ProductSyncData;
use Daugt\Commerce\Payments\PaymentProviderResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Statamic\Facades\Entry;

class SyncPaymentProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private string $entryId)
    {
    }

    public function handle(PaymentProviderResolver $resolver): void
    {
        $entry = Entry::find($this->entryId);

        if (! $entry || $entry->collectionHandle() !== ProductEntry::COLLECTION) {
            return;
        }

        /** @var ProductEntry $entry */
        $store = $resolver->store();
        $provider = $resolver->provider();
        $ids = $store->getProductIds($entry);

        if ($entry->externalProduct()) {
            $provider->archiveProduct($ids['product_id'] ?? null, $ids['price_id'] ?? null);
            $store->setProductIds($entry, null, null);
            return;
        }

        $productData = new ProductSyncData(
            $entry->id(),
            (string) ($entry->get(ProductEntry::TITLE) ?: 'Product'),
            is_string($entry->get(ProductEntry::DESCRIPTION)) ? $entry->get(ProductEntry::DESCRIPTION) : null,
            $entry->price(),
            $resolver->currency(),
            $entry->billingType() ?: 'one_time',
            $entry->subscriptionIntervalUnit(),
            $entry->subscriptionInterval(),
            $entry->stripeTaxCode(),
            (bool) $entry->published(),
            [
                'statamic_entry_id' => $entry->id(),
                'statamic_collection' => $entry->collectionHandle(),
                'statamic_site' => $entry->locale(),
            ]
        );

        $result = $provider->syncProduct(
            $productData,
            $ids['product_id'] ?? null,
            $ids['price_id'] ?? null
        );

        $store->setProductIds($entry, $result->productId, $result->priceId);
    }
}
