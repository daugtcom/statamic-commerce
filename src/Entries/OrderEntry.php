<?php

namespace Daugt\Commerce\Entries;

use Statamic\Entries\Entry;

class OrderEntry extends Entry
{
    public const COLLECTION = 'orders';
    public const USER = 'user';
    public const ORDER_NUMBER = 'order_number';
    public const STATUS = 'status';
    public const SUCCEEDED_AT = 'succeeded_at';
    public const ITEMS = 'items';
    public const BILLING_ADDRESS = 'billing_address';
    public const SHIPPING_ADDRESS = 'shipping_address';
    public const STRIPE_CHECKOUT_SESSION_ID = 'stripe_checkout_session_id';

    public function orderNumber(): ?int
    {
        $value = $this->get(self::ORDER_NUMBER);

        return $value !== null ? (int) $value : null;
    }

    public function userId(): ?string
    {
        $value = $this->get(self::USER);

        return $value !== null ? (string) $value : null;
    }

    public function status(): ?string
    {
        $value = $this->get(self::STATUS);

        return $value !== null ? (string) $value : null;
    }

    public function succeededAt(): ?string
    {
        $value = $this->get(self::SUCCEEDED_AT);

        return $value !== null ? (string) $value : null;
    }

    public function stripeCheckoutSessionId(): ?string
    {
        $value = $this->get(self::STRIPE_CHECKOUT_SESSION_ID);

        return $value !== null ? (string) $value : null;
    }
}
