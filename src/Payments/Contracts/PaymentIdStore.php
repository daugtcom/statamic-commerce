<?php

namespace Daugt\Commerce\Payments\Contracts;

use Daugt\Commerce\Entries\ProductEntry;
use Statamic\Contracts\Auth\User;

interface PaymentIdStore
{
    public function getProductIds(ProductEntry $entry): array;

    public function setProductIds(ProductEntry $entry, ?string $productId, ?string $priceId): void;

    public function getCustomerId(User $user): ?string;

    public function setCustomerId(User $user, ?string $customerId): void;
}
