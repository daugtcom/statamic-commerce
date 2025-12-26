<?php

namespace Daugt\Commerce\Payments\Contracts;

use Daugt\Commerce\Payments\DTO\CustomerSyncData;
use Daugt\Commerce\Payments\DTO\ProductSyncData;
use Daugt\Commerce\Payments\DTO\CustomerSyncResult;
use Daugt\Commerce\Payments\DTO\ProductSyncResult;

interface PaymentProvider
{
    public function syncProduct(
        ProductSyncData $product,
        ?string $productId,
        ?string $priceId
    ): ProductSyncResult;

    public function archiveProduct(?string $productId, ?string $priceId): void;

    public function syncCustomer(
        CustomerSyncData $customer,
        ?string $customerId
    ): CustomerSyncResult;
}
