<?php

namespace Daugt\Commerce\Jobs\StripeWebhooks;

use Daugt\Commerce\Entries\InvoiceEntry;
use Daugt\Commerce\Entries\OrderEntry;
use Daugt\Commerce\Enums\InvoiceStatus;
use Statamic\Facades\Entry;

class InvoiceEvent extends StripeWebhookJob
{
    public function handle(): void
    {
        $payload = $this->payload();
        $invoice = $payload['data']['object'] ?? null;

        if (! is_array($invoice)) {
            return;
        }

        $subscriptionId = (string) ($invoice['subscription'] ?? '');
        if ($subscriptionId === '') {
            return;
        }

        $order = $this->findOrderBySubscriptionId($subscriptionId);
        if (! $order) {
            $this->release(10);
            return;
        }

        $invoiceId = (string) ($invoice['id'] ?? '');
        if ($invoiceId === '') {
            return;
        }

        $status = $this->resolveStatus($payload, $invoice);

        $entry = Entry::query()
            ->where('collection', InvoiceEntry::COLLECTION)
            ->where(InvoiceEntry::STRIPE_INVOICE_ID, $invoiceId)
            ->first()
            ?? Entry::make()->collection(InvoiceEntry::COLLECTION);

        $entry->set(InvoiceEntry::ORDER, $order->id());
        $entry->set(InvoiceEntry::USER, $order->get(OrderEntry::USER));
        $entry->set(InvoiceEntry::STATUS, $status);
        $entry->set(InvoiceEntry::STRIPE_PAYMENT_INTENT_ID, $invoice['payment_intent'] ?? null);
        $entry->set(InvoiceEntry::STRIPE_INVOICE_ID, $invoiceId);
        $entry->save();
    }

    private function resolveStatus(array $payload, array $invoice): string
    {
        $type = (string) ($payload['type'] ?? '');

        return match ($type) {
            'invoice.payment_succeeded' => InvoiceStatus::PAID->value,
            'invoice.payment_failed' => InvoiceStatus::FAILED->value,
            default => $this->mapInvoiceStatus((string) ($invoice['status'] ?? '')),
        };
    }

    private function mapInvoiceStatus(string $status): string
    {
        return match ($status) {
            'paid' => InvoiceStatus::PAID->value,
            'uncollectible', 'void' => InvoiceStatus::FAILED->value,
            default => InvoiceStatus::PENDING->value,
        };
    }
}
