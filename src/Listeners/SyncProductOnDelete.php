<?php

namespace Daugt\Commerce\Listeners;

use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Jobs\ArchiveStripeProduct;
use Statamic\Events\EntryDeleted;

class SyncProductOnDelete
{
    public function handle(EntryDeleted $event): void
    {
        $entry = $event->entry;

        if ($entry->collectionHandle() !== ProductEntry::COLLECTION) {
            return;
        }

        ArchiveStripeProduct::dispatch(
            $entry->get('stripe_product_id'),
            $entry->get('stripe_price_id')
        );
    }
}
