<?php

namespace Daugt\Commerce\Listeners;

use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Jobs\ArchivePaymentProduct;
use Daugt\Commerce\Payments\PaymentProviderResolver;
use Statamic\Events\EntryDeleted;

class SyncProductOnDelete
{
    public function __construct(private PaymentProviderResolver $resolver)
    {
    }

    public function handle(EntryDeleted $event): void
    {
        $entry = $event->entry;

        if ($entry->collectionHandle() !== ProductEntry::COLLECTION) {
            return;
        }

        $ids = $this->resolver->store()->getProductIds($entry);

        ArchivePaymentProduct::dispatch(
            $ids['product_id'] ?? null,
            $ids['price_id'] ?? null
        );
    }
}
