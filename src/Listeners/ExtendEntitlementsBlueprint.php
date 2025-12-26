<?php

namespace Daugt\Commerce\Listeners;

use Daugt\Commerce\Entries\OrderEntry;
use Statamic\Events\EntryBlueprintFound;

class ExtendEntitlementsBlueprint
{
    public function handle(EntryBlueprintFound $event): void
    {
        $collectionHandle = $this->collectionHandle($event);

        if ($collectionHandle !== 'entitlements') {
            return;
        }

        $event->blueprint->ensureField('order', [
            'type' => 'entries',
            'collections' => [OrderEntry::COLLECTION],
            'mode' => 'select',
            'max_items' => 1,
            'display' => 'daugt-commerce::entitlements.fields.order',
            'read_only' => true,
        ]);
    }

    private function collectionHandle(EntryBlueprintFound $event): ?string
    {
        $entry = $event->entry;

        if ($entry && method_exists($entry, 'collectionHandle')) {
            return $entry->collectionHandle();
        }

        $handle = $event->blueprint->handle();

        if (! $handle) {
            return null;
        }

        $parts = explode('/', $handle);

        if (count($parts) >= 2 && $parts[0] === 'collections') {
            return $parts[1];
        }

        return null;
    }
}
