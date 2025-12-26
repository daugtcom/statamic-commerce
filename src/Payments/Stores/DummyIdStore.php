<?php

namespace Daugt\Commerce\Payments\Stores;

use Daugt\Commerce\Entries\ProductEntry;
use Daugt\Commerce\Payments\Contracts\PaymentIdStore;
use Statamic\Contracts\Auth\User;

class DummyIdStore implements PaymentIdStore
{
    private static array $productIds = [];
    private static array $customerIds = [];

    public static function reset(): void
    {
        self::$productIds = [];
        self::$customerIds = [];
    }

    public function getProductIds(ProductEntry $entry): array
    {
        return self::$productIds[$entry->id()] ?? [
            'product_id' => null,
            'price_id' => null,
        ];
    }

    public function setProductIds(ProductEntry $entry, ?string $productId, ?string $priceId): void
    {
        self::$productIds[$entry->id()] = [
            'product_id' => $productId,
            'price_id' => $priceId,
        ];
    }

    public function getCustomerId(User $user): ?string
    {
        return self::$customerIds[$user->id()] ?? null;
    }

    public function setCustomerId(User $user, ?string $customerId): void
    {
        self::$customerIds[$user->id()] = $customerId;
    }
}
