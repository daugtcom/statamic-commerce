<?php

namespace Daugt\Commerce\Blueprints;

use Daugt\Commerce\Entries\InvoiceEntry;
use Statamic\Entries\Collection;
use Statamic\Facades\Collection as CollectionFacade;

class InvoiceCollection
{
    public function __invoke(): Collection
    {
        $collection = CollectionFacade::make(InvoiceEntry::COLLECTION);
        $collection->entryClass(InvoiceEntry::class);
        $collection->title('daugt-commerce::collections.invoices.title');
        $collection->revisionsEnabled(false);
        $collection->dated(false);
        $collection->requiresSlugs(false);
        $collection->titleFormats('Invoice #{{ id }}');

        return $collection;
    }
}
