<?php

namespace Daugt\Commerce\Payments\Providers;

use Daugt\Commerce\Payments\Contracts\PaymentProvider;
use Daugt\Commerce\Payments\DTO\CustomerSyncData;
use Daugt\Commerce\Payments\DTO\ProductSyncData;
use Daugt\Commerce\Payments\DTO\CustomerSyncResult;
use Daugt\Commerce\Payments\DTO\ProductSyncResult;
use Stripe\StripeClient;

class StripeProvider implements PaymentProvider
{
    public function __construct(private StripeClient $stripeClient)
    {
    }

    public function syncProduct(
        ProductSyncData $product,
        ?string $productId,
        ?string $priceId
    ): ProductSyncResult {
        if (! $product->published && ! $productId) {
            return new ProductSyncResult(null, null);
        }

        $productPayload = $this->buildProductPayload($product);

        if ($productId) {
            $this->stripeClient->products->update($productId, $productPayload);
        } else {
            $stripeProduct = $this->stripeClient->products->create($productPayload);
            $productId = $stripeProduct->id;
        }

        $pricePayload = $this->buildPricePayload($product, $productId);
        if (! $pricePayload) {
            return new ProductSyncResult($productId, $priceId);
        }

        $priceId = $this->syncPrice($priceId, $pricePayload, $product->published);

        return new ProductSyncResult($productId, $priceId);
    }

    public function archiveProduct(?string $productId, ?string $priceId): void
    {
        if (! $productId) {
            return;
        }

        if ($priceId) {
            $this->stripeClient->prices->update($priceId, [
                'active' => false,
            ]);
        }

        $this->stripeClient->products->update($productId, [
            'active' => false,
        ]);
    }

    public function syncCustomer(
        CustomerSyncData $customer,
        ?string $customerId
    ): CustomerSyncResult {
        $payload = [
            'metadata' => $customer->metadata,
        ];

        if ($customer->email) {
            $payload['email'] = $customer->email;
        }

        if ($customer->name) {
            $payload['name'] = $customer->name;
        }

        if ($customerId) {
            $this->stripeClient->customers->update($customerId, $payload);
            return new CustomerSyncResult($customerId);
        }

        $stripeCustomer = $this->stripeClient->customers->create($payload);

        return new CustomerSyncResult($stripeCustomer?->id);
    }

    private function buildProductPayload(ProductSyncData $product): array
    {
        $payload = [
            'name' => $product->title,
            'active' => $product->published,
            'metadata' => $product->metadata,
            'tax_code' => $product->taxCode,
        ];

        if ($product->description) {
            $payload['description'] = $product->description;
        }

        return $payload;
    }

    private function buildPricePayload(ProductSyncData $product, string $productId): ?array
    {
        if ($product->price === null) {
            logger()->warning('Stripe sync skipped: missing price.', [
                'entry_id' => $product->id,
            ]);
            return null;
        }

        $unitAmount = (int) round($product->price * 100);

        $payload = [
            'currency' => $product->currency,
            'unit_amount' => $unitAmount,
            'product' => $productId,
            'active' => $product->published,
            'metadata' => [
                'statamic_entry_id' => $product->id,
                'billing_type' => $product->billingType,
            ],
        ];

        if ($product->billingType === 'recurring') {
            $interval = $product->intervalUnit ?: 'month';
            $intervalCount = $product->intervalCount ?: 1;

            $allowedIntervals = ['day', 'week', 'month', 'year'];
            if (! in_array($interval, $allowedIntervals, true)) {
                logger()->warning('Stripe sync skipped: unsupported recurring interval.', [
                    'entry_id' => $product->id,
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

    private function syncPrice(?string $priceId, array $payload, bool $isPublished): ?string
    {
        if ($priceId) {
            $price = $this->stripeClient->prices->retrieve($priceId, []);
            if ($this->priceMatches($price, $payload)) {
                if ($price->active !== $isPublished) {
                    $this->stripeClient->prices->update($priceId, [
                        'active' => $isPublished,
                    ]);
                }

                return $priceId;
            }

            $this->stripeClient->prices->update($priceId, [
                'active' => false,
            ]);
        }

        $newPrice = $this->stripeClient->prices->create($payload);

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
}
