<?php

namespace Daugt\Commerce\Computed;

use Daugt\Commerce\Entries\InvoiceEntry;
use Daugt\Commerce\Services\InvoiceUrlResolver;
use Statamic\Facades\Collection;

class ComputedFields
{
    public static function register(): void
    {
        Collection::computed(InvoiceEntry::COLLECTION, InvoiceEntry::URL, function (InvoiceEntry $entry, $value) {
            return app(InvoiceUrlResolver::class)->resolve($entry);
        });
    }
}
