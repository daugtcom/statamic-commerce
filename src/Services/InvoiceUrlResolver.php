<?php

namespace Daugt\Commerce\Services;

use Daugt\Commerce\Entries\InvoiceEntry;
use Statamic\Facades\Blink;
use Stripe\StripeClient;

class InvoiceUrlResolver
{
    public function resolve(InvoiceEntry $entry): ?string
    {
        if (config('statamic.daugt-commerce.payment.provider') !== 'stripe') {
            return null;
        }

        $invoiceId = $entry->stripeInvoiceId();
        if (! $invoiceId) {
            return null;
        }

        if (! config('statamic.daugt-commerce.payment.providers.stripe.config.secret')) {
            return null;
        }

        return Blink::once("daugt-commerce.invoice-url.{$invoiceId}", function () use ($invoiceId) {
            try {
                $invoice = app(StripeClient::class)->invoices->retrieve($invoiceId, []);
            } catch (\Throwable) {
                return null;
            }

            $url = $invoice->invoice_pdf ?? null;

            return $url ?: null;
        });
    }
}
