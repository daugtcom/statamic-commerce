<?php

namespace Daugt\Commerce\Payments\DTO;

class ProductSyncResult
{
    public function __construct(
        public ?string $productId,
        public ?string $priceId
    ) {
    }
}
