<?php

namespace Daugt\Commerce\Blueprints;

use Daugt\Commerce\Entries\ProductEntry;
use Statamic\Entries\Collection;
use Statamic\Facades\Collection as CollectionFacade;

class ProductCollection
{
    public function __invoke(): Collection
    {
        $collection = CollectionFacade::make(ProductEntry::COLLECTION);
        $collection->entryClass(ProductEntry::class);
        $collection->title('daugt-commerce::collections.products.title');

        return $collection;
    }
}
