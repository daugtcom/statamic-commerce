<?php

namespace Daugt\Commerce\Listeners;

use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Services\OrderNumberService;
use Statamic\Events\EntrySaving;

class AssignOrderNumber
{
    public function handle(EntrySaving $event): void
    {
        $entry = $event->entry;

        if ($entry->collectionHandle() !== OrderEntry::COLLECTION || ! $entry instanceof OrderEntry) {
            return;
        }

        app(OrderNumberService::class)->assign($entry);
    }
}
