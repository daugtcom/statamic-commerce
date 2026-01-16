<?php

namespace Daugt\Commerce\Services;

use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Enums\ShippingCalculationMethod;
use Daugt\Commerce\Support\AddonEdition;
use Daugt\Commerce\Support\AddonSettings;
use Daugt\Commerce\Support\CountryCodeMapper;
use Daugt\Commerce\Support\StripePayload;
use Statamic\Facades\Term;

class ShippingRateCalculator
{
    public function __construct(private StripeLineItemProductResolver $productResolver)
    {
    }

    public function calculateAmountForCountry(string $country, array $lineItems): ?int
    {
        $country = $this->normalizeCountry($country);
        $total = 0.0;
        $max = 0.0;
        $requiresShipping = false;

        foreach ($lineItems as $lineItem) {
            $product = $this->resolveProduct($lineItem);
            if (! $product || ! $product->shipping()) {
                continue;
            }

            $requiresShipping = true;
            $quantity = StripePayload::int($lineItem, 'quantity') ?? 1;
            $rate = $this->rateForProduct($product, $country);

            if ($rate === null) {
                return null;
            }

            $total += $rate * max(1, $quantity);
            $max = max($max, $rate);
        }

        if (! $requiresShipping) {
            return null;
        }

        $method = AddonSettings::firstValue('shipping_calculation_method')
            ?: ShippingCalculationMethod::SUM->value;

        $amount = $method === ShippingCalculationMethod::MAX->value ? $max : $total;

        return (int) round($amount * 100);
    }

    public function buildStripeShippingOption(int $amount): array
    {
        $currency = strtolower(AddonSettings::firstValue('currency') ?? 'EUR');

        return [
            'shipping_rate_data' => [
                'display_name' => __('daugt-commerce::products.fields.shipping'),
                'type' => 'fixed_amount',
                'fixed_amount' => [
                    'amount' => max(0, $amount),
                    'currency' => $currency,
                ],
            ],
        ];
    }

    public function isCountryAllowed(string $country): bool
    {
        $allowed = $this->allowedCountries();

        if ($allowed === []) {
            return true;
        }

        return in_array($country, $allowed, true);
    }

    public function normalizeCountry(?string $country): string
    {
        return CountryCodeMapper::toIso2($country);
    }

    private function rateForProduct(ProductEntry $product, string $country): ?float
    {
        if (! AddonEdition::isPro()) {
            return $this->flatRate();
        }

        $sizeId = $product->shippingSizeId();
        if (! $sizeId) {
            return $this->flatRate();
        }

        $term = $this->resolveShippingSizeTerm($sizeId);
        if (! $term) {
            return $this->flatRate();
        }

        $rate = $this->rateForCountry($term->get('shipping_rates'), $country);
        if ($rate !== null) {
            return $rate;
        }

        $fallback = $term->get('shipping_fallback_rate');
        if (is_numeric($fallback)) {
            return (float) $fallback;
        }

        return $this->flatRate();
    }

    private function rateForCountry(mixed $rates, string $country): ?float
    {
        if (! is_array($rates)) {
            return null;
        }

        foreach ($rates as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowCountry = $row['country'] ?? null;
            if (is_array($rowCountry)) {
                $rowCountry = $rowCountry[0] ?? null;
            }

            $rowCountry = CountryCodeMapper::toIso2(is_string($rowCountry) ? $rowCountry : null);
            if ($rowCountry === '' || $rowCountry !== $country) {
                continue;
            }

            $amount = $row['amount'] ?? null;
            if (is_numeric($amount)) {
                return (float) $amount;
            }
        }

        return null;
    }

    private function flatRate(): ?float
    {
        $rate = AddonSettings::get('shipping_flat_rate');

        if (is_numeric($rate)) {
            return (float) $rate;
        }

        return null;
    }

    private function allowedCountries(): array
    {
        $allowed = AddonSettings::get('shipping_allowed_countries');

        if (! is_array($allowed)) {
            return [];
        }

        return CountryCodeMapper::normalizeList($allowed);
    }

    private function resolveProduct(array $lineItem): ?ProductEntry
    {
        return $this->productResolver->resolve($lineItem);
    }

    private function resolveShippingSizeTerm(string $sizeId)
    {
        $term = Term::find($sizeId);
        if ($term) {
            return $term;
        }

        if (! str_contains($sizeId, '::')) {
            $term = Term::find('shipping_sizes::' . $sizeId);
            if ($term) {
                return $term;
            }
        }

        return Term::query()
            ->where('taxonomy', 'shipping_sizes')
            ->where('slug', $sizeId)
            ->first();
    }
}
