<?php

namespace Daugt\Commerce\Blueprints;

use Daugt\Commerce\Entries\OrderEntry;
use Statamic\Entries\Collection;
use Statamic\Facades\Collection as CollectionFacade;

class OrderCollection
{
    public function __invoke(): Collection
    {
        $collection = CollectionFacade::make(OrderEntry::COLLECTION);
        $collection->entryClass(OrderEntry::class);
        $collection->title('daugt-commerce::collections.orders.title');
        $collection->revisionsEnabled(false);
        $collection->dated(false);
        $collection->requiresSlugs(false);
        $collection->sortField('order_number');
        $collection->sortDirection('desc');
        $collection->titleFormats('Order #{{ order_number }}');

        return $collection;
    }
}
