<?php

namespace Daugt\Commerce\Support;

final class StripeAddress
{
    public static function fromCustomerDetails(?array $details): array
    {
        if (! is_array($details)) {
            return [];
        }

        return self::map($details['address'] ?? null, $details['name'] ?? null, $details['phone'] ?? null);
    }

    public static function fromShippingDetails(?array $details): array
    {
        if (! is_array($details)) {
            return [];
        }

        return self::map($details['address'] ?? null, $details['name'] ?? null, $details['phone'] ?? null);
    }

    public static function fromCustomer(?array $customer): array
    {
        if (! is_array($customer)) {
            return [];
        }

        return self::map($customer['address'] ?? null, $customer['name'] ?? null, $customer['phone'] ?? null);
    }

    public static function fromShipping(?array $shipping): array
    {
        if (! is_array($shipping)) {
            return [];
        }

        return self::map($shipping['address'] ?? null, $shipping['name'] ?? null, $shipping['phone'] ?? null);
    }

    private static function map(mixed $address, ?string $name, ?string $phone): array
    {
        if (! is_array($address)) {
            return [];
        }

        return array_filter([
            'name' => $name,
            'phone' => $phone,
            'line1' => $address['line1'] ?? null,
            'line2' => $address['line2'] ?? null,
            'city' => $address['city'] ?? null,
            'state' => $address['state'] ?? null,
            'postal_code' => $address['postal_code'] ?? null,
            'country' => $address['country'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');
    }
}
