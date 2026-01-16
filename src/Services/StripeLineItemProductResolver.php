<?php

namespace Daugt\Commerce\Services;

use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Enums\BillingType;
use Daugt\Commerce\Support\StripePayload;
use Statamic\Facades\Entry;

class StripeLineItemProductResolver
{
    public function resolve(array $lineItem): ?ProductEntry
    {
        $priceId = StripePayload::string($lineItem, 'price');
        $price = StripePayload::array($lineItem, 'price');
        $stripeProductId = StripePayload::string($price, 'product');
        $billingType = StripePayload::array($price, 'recurring') !== []
            ? BillingType::RECURRING->value
            : BillingType::ONE_TIME->value;

        if ($priceId === '') {
            $priceId = StripePayload::string($price, 'id');
        }

        $query = Entry::query()->where('collection', ProductEntry::COLLECTION);

        if ($priceId !== '') {
            $match = $query->where(ProductEntry::STRIPE_PRICE_ID, $priceId)->first();
            if ($match instanceof ProductEntry) {
                return $match;
            }
        }

        if ($stripeProductId !== '') {
            $productQuery = $query;
            if ($billingType) {
                $productQuery = $productQuery->where(ProductEntry::BILLING_TYPE, $billingType);
            }

            $match = $productQuery->where(ProductEntry::STRIPE_PRODUCT_ID, $stripeProductId)->first();
            if ($match instanceof ProductEntry) {
                return $match;
            }
        }

        return null;
    }
}
