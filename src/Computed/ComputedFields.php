<?php

namespace Daugt\Commerce\Computed;

use Daugt\Commerce\Entries\InvoiceEntry;
use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Services\OrderShippingStatusResolver;
use Daugt\Commerce\Services\InvoiceUrlResolver;
use Statamic\Facades\Collection;

class ComputedFields
{
    public static function register(): void
    {
        Collection::computed(InvoiceEntry::COLLECTION, InvoiceEntry::URL, function (InvoiceEntry $entry, $value) {
            return app(InvoiceUrlResolver::class)->resolve($entry);
        });

        Collection::computed(OrderEntry::COLLECTION, OrderEntry::SHIPPING_STATUS, function (OrderEntry $entry, $value) {
            return app(OrderShippingStatusResolver::class)->resolve($entry);
        });
    }
}
