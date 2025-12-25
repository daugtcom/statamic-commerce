<?php

namespace Daugt\Commerce\Listeners;

use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Jobs\SyncStripeProduct;
use Statamic\Events\EntrySaved;

class SyncProductOnSave
{
    public function handle(EntrySaved $event): void
    {
        $entry = $event->entry;

        if ($entry->collectionHandle() !== ProductEntry::COLLECTION) {
            return;
        }

        SyncStripeProduct::dispatch($entry->id());
    }
}
