<?php

namespace Daugt\Commerce\Payments\DTO;

class CustomerSyncData
{
    public function __construct(
        public string $id,
        public ?string $email,
        public ?string $name,
        public array $metadata = []
    ) {
    }
}
