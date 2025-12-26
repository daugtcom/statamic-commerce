<?php

namespace Daugt\Commerce\Entries;

use Statamic\Entries\Entry;

class InvoiceEntry extends Entry
{
    public const COLLECTION = 'invoices';
    public const USER = 'user';
    public const ORDER = 'order';
    public const STATUS = 'status';
    public const STRIPE_PAYMENT_INTENT_ID = 'stripe_payment_intent_id';
    public const STRIPE_INVOICE_ID = 'stripe_invoice_id';

    public function status(): ?string
    {
        $value = $this->get(self::STATUS);

        return $value !== null ? (string) $value : null;
    }

    public function userId(): ?string
    {
        $value = $this->get(self::USER);

        return $value !== null ? (string) $value : null;
    }

    public function stripePaymentIntentId(): ?string
    {
        $value = $this->get(self::STRIPE_PAYMENT_INTENT_ID);

        return $value !== null ? (string) $value : null;
    }

    public function stripeInvoiceId(): ?string
    {
        $value = $this->get(self::STRIPE_INVOICE_ID);

        return $value !== null ? (string) $value : null;
    }
}
