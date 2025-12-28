<?php

namespace Daugt\Commerce\Listeners;

use Daugt\Access\Entries\EntitlementEntry;
use Statamic\Events\EntrySaving;

class EnsureEntitlementEntryBlueprint
{
    public function handle(EntrySaving $event): void
    {
        if (! class_exists(EntitlementEntry::class)) {
            return;
        }

        $entry = $event->entry;

        if ($entry->collectionHandle() !== EntitlementEntry::COLLECTION) {
            return;
        }

        $current = $entry->get('blueprint');
        if (is_string($current) && $current !== '') {
            return;
        }

        $collection = $entry->collection();
        if (! $collection) {
            return;
        }

        $blueprint = $collection->entryBlueprint();
        if (! $blueprint) {
            return;
        }

        $handle = $blueprint->handle();
        if ($handle) {
            $entry->blueprint($handle);
        }
    }
}
