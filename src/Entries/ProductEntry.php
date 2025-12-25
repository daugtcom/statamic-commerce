<?php

namespace Daugt\Commerce\Entries;

use Statamic\Entries\Entry;

class ProductEntry extends Entry
{
    public const COLLECTION = 'products';
    public const TITLE = 'title';
    public const DESCRIPTION = 'description';
    public const CATEGORIES = 'categories';
    public const PRICE = 'price';
    public const BILLING_TYPE = 'billing_type';
    public const SUBSCRIPTION_INTERVAL = 'subscription_interval';
    public const SUBSCRIPTION_INTERVAL_UNIT = 'subscription_interval_unit';
    public const SUBSCRIPTION_DURATION = 'subscription_duration';
    public const SUBSCRIPTION_DURATION_ITERATIONS = 'subscription_duration_iterations';
    public const MEDIA = 'media';
    public const EXTERNAL_PRODUCT = 'external_product';
    public const EXTERNAL_PRODUCT_URL = 'external_product_url';
    public const ALL_ACCESS_ITEMS = 'all_access_items';
    public const SHIPPING = 'shipping';
    public const STRIPE_TAX_CODE = 'stripe_tax_code';

    public function price(): ?float
    {
        $value = $this->get(self::PRICE);

        return $value !== null ? (float) $value : null;
    }

    public function billingType(): ?string
    {
        $value = $this->get(self::BILLING_TYPE);

        return $value !== null ? (string) $value : null;
    }

    public function subscriptionInterval(): ?int
    {
        $value = $this->get(self::SUBSCRIPTION_INTERVAL);

        return $value !== null ? (int) $value : null;
    }

    public function subscriptionIntervalUnit(): ?string
    {
        $value = $this->get(self::SUBSCRIPTION_INTERVAL_UNIT);

        return $value !== null ? (string) $value : null;
    }

    public function subscriptionDuration(): ?string
    {
        $value = $this->get(self::SUBSCRIPTION_DURATION);

        return $value !== null ? (string) $value : null;
    }

    public function subscriptionDurationIterations(): ?int
    {
        $value = $this->get(self::SUBSCRIPTION_DURATION_ITERATIONS);

        return $value !== null ? (int) $value : null;
    }

    public function shipping(): bool
    {
        return (bool) $this->get(self::SHIPPING);
    }

    public function externalProduct(): bool
    {
        return (bool) $this->get(self::EXTERNAL_PRODUCT);
    }

    public function externalProductUrl(): ?string
    {
        $value = $this->get(self::EXTERNAL_PRODUCT_URL);

        return $value !== null ? (string) $value : null;
    }

    public function stripeTaxCode(): ?string
    {
        $value = $this->get(self::STRIPE_TAX_CODE);

        return $value !== null ? (string) $value : null;
    }
}
