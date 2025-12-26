<?php

namespace Daugt\Commerce\Payments\DTO;

class ProductSyncData
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public ?float $price,
        public string $currency,
        public string $billingType,
        public ?string $intervalUnit,
        public ?int $intervalCount,
        public ?string $taxCode,
        public bool $published,
        public array $metadata = []
    ) {
    }
}
