<?php

namespace Daugt\Commerce\Payments\Providers;

use Daugt\Commerce\Payments\Contracts\PaymentProvider;
use Daugt\Commerce\Payments\DTO\CustomerSyncData;
use Daugt\Commerce\Payments\DTO\ProductSyncData;
use Daugt\Commerce\Payments\DTO\CustomerSyncResult;
use Daugt\Commerce\Payments\DTO\ProductSyncResult;

class DummyProvider implements PaymentProvider
{
    public function syncProduct(
        ProductSyncData $product,
        ?string $productId,
        ?string $priceId
    ): ProductSyncResult {
        if (! $product->published && ! $productId) {
            return new ProductSyncResult(null, null);
        }

        $productId = $productId ?: "dummy_prod_{$product->id}";
        $priceId = $priceId ?: "dummy_price_{$product->id}";

        return new ProductSyncResult($productId, $priceId);
    }

    public function archiveProduct(?string $productId, ?string $priceId): void
    {
        // No-op for dummy provider.
    }

    public function syncCustomer(
        CustomerSyncData $customer,
        ?string $customerId
    ): CustomerSyncResult {
        $customerId = $customerId ?: "dummy_cus_{$customer->id}";

        return new CustomerSyncResult($customerId);
    }
}
