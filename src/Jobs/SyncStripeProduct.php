<?php

namespace Daugt\Commerce\Jobs;

use Daugt\Commerce\Entries\ProductEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Statamic\Facades\Entry;
use Stripe\StripeClient;

class SyncStripeProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private string $entryId)
    {
    }

    public function handle(StripeClient $stripeClient): void
    {
        $entry = Entry::find($this->entryId);

        if (! $entry || $entry->collectionHandle() !== ProductEntry::COLLECTION) {
            return;
        }

        /** @var ProductEntry $entry */
        if ($entry->externalProduct()) {
            $this->archiveStripeProduct(
                $stripeClient,
                $entry->stripeProductId(),
                $entry->stripePriceId()
            );

            if ($entry->stripeProductId() || $entry->stripePriceId()) {
                $entry->set(ProductEntry::STRIPE_PRODUCT_ID, null);
                $entry->set(ProductEntry::STRIPE_PRICE_ID, null);
                $entry->saveQuietly();
            }

            return;
        }

        $isPublished = $entry->published();
        $stripeProductId = $entry->stripeProductId();
        $stripePriceId = $entry->stripePriceId();

        if (! $isPublished && ! $stripeProductId) {
            return;
        }

        $productPayload = $this->buildProductPayload($entry, $isPublished);

        if ($stripeProductId) {
            $stripeClient->products->update($stripeProductId, $productPayload);
        } else {
            $product = $stripeClient->products->create($productPayload);
            $stripeProductId = $product->id;
        }

        $pricePayload = $this->buildPricePayload($entry, $stripeProductId, $isPublished);
        if (! $pricePayload) {
            return;
        }

        $stripePriceId = $this->syncPrice(
            $stripeClient,
            $stripePriceId,
            $pricePayload,
            $isPublished
        );

        $this->persistStripeIds($entry, $stripeProductId, $stripePriceId);
    }

    private function buildProductPayload(ProductEntry $entry, bool $isPublished): array
    {
        $title = $entry->get(ProductEntry::TITLE) ?: 'Product';
        $taxCode = $entry->stripeTaxCode();

        $payload = [
            'name' => $title,
            'active' => $isPublished,
            'metadata' => [
                'statamic_entry_id' => $entry->id(),
                'statamic_collection' => $entry->collectionHandle(),
                'statamic_site' => $entry->locale(),
            ],
        ];

        if ($taxCode) {
            $payload['tax_code'] = $taxCode;
        } else {
            $payload['tax_code'] = null;
        }

        $description = $entry->get(ProductEntry::DESCRIPTION);
        if (is_string($description) && $description !== '') {
            $payload['description'] = $description;
        }

        return $payload;
    }

    private function buildPricePayload(ProductEntry $entry, string $stripeProductId, bool $isPublished): ?array
    {
        $price = $entry->price();
        if ($price === null) {
            logger()->warning('Stripe sync skipped: missing price.', [
                'entry_id' => $entry->id(),
            ]);
            return null;
        }

        $unitAmount = (int) round($price * 100);

        $payload = [
            // TODO: make configurable
            'currency' => 'eur',
            'unit_amount' => $unitAmount,
            'product' => $stripeProductId,
            'active' => $isPublished,
            'metadata' => [
                'statamic_entry_id' => $entry->id(),
                'billing_type' => $entry->billingType() ?: 'one_time',
                'subscription_duration' => $entry->subscriptionDuration() ?: '',
                'subscription_duration_iterations' => $entry->subscriptionDurationIterations() ?? '',
            ],
        ];

        if ($entry->billingType() === 'recurring') {
            $interval = $entry->subscriptionIntervalUnit() ?: 'month';
            $intervalCount = $entry->subscriptionInterval() ?: 1;

            $allowedIntervals = ['day', 'week', 'month', 'year'];
            if (! in_array($interval, $allowedIntervals, true)) {
                logger()->warning('Stripe sync skipped: unsupported recurring interval.', [
                    'entry_id' => $entry->id(),
                    'interval' => $interval,
                ]);
                return null;
            }

            $payload['recurring'] = [
                'interval' => $interval,
                'interval_count' => max(1, (int) $intervalCount),
            ];
        }

        return $payload;
    }

    private function syncPrice(
        StripeClient $stripeClient,
        ?string $stripePriceId,
        array $pricePayload,
        bool $isPublished
    ): ?string {
        if ($stripePriceId) {
            $price = $stripeClient->prices->retrieve($stripePriceId, []);
            if ($this->priceMatches($price, $pricePayload)) {
                if ($price->active !== $isPublished) {
                    $stripeClient->prices->update($stripePriceId, [
                        'active' => $isPublished,
                    ]);
                }

                return $stripePriceId;
            }

            $stripeClient->prices->update($stripePriceId, [
                'active' => false,
            ]);
        }

        $newPrice = $stripeClient->prices->create($pricePayload);

        return $newPrice->id ?? null;
    }

    private function priceMatches($price, array $payload): bool
    {
        if (! $price || $price->currency !== $payload['currency']) {
            return false;
        }

        if ((int) $price->unit_amount !== (int) $payload['unit_amount']) {
            return false;
        }

        $payloadRecurring = $payload['recurring'] ?? null;
        if ($payloadRecurring) {
            if (! $price->recurring) {
                return false;
            }

            return $price->recurring->interval === $payloadRecurring['interval']
                && (int) $price->recurring->interval_count === (int) $payloadRecurring['interval_count'];
        }

        return $price->recurring === null;
    }

    private function persistStripeIds(ProductEntry $entry, ?string $productId, ?string $priceId): void
    {
        $changed = false;

        if ($productId && $entry->stripeProductId() !== $productId) {
            $entry->set(ProductEntry::STRIPE_PRODUCT_ID, $productId);
            $changed = true;
        }

        if ($priceId && $entry->stripePriceId() !== $priceId) {
            $entry->set(ProductEntry::STRIPE_PRICE_ID, $priceId);
            $changed = true;
        }

        if ($changed) {
            $entry->saveQuietly();
        }
    }

    private function archiveStripeProduct(
        StripeClient $stripeClient,
        ?string $productId,
        ?string $priceId
    ): void {
        if (! $productId) {
            return;
        }

        if ($priceId) {
            $stripeClient->prices->update($priceId, [
                'active' => false,
            ]);
        }

        $stripeClient->products->update($productId, [
            'active' => false,
        ]);
    }
}
