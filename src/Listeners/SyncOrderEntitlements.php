<?php

namespace Daugt\Commerce\Listeners;

use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Services\OrderEntitlementService;
use Statamic\Events\EntrySaved;

class SyncOrderEntitlements
{
    public function handle(EntrySaved $event): void
    {
        $entry = $event->entry;

        if ($entry->collectionHandle() !== OrderEntry::COLLECTION || ! $entry instanceof OrderEntry) {
            return;
        }

        app(OrderEntitlementService::class)->syncForOrder($entry);
    }
}
