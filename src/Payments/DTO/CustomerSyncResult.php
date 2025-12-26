<?php

namespace Daugt\Commerce\Payments\DTO;

class CustomerSyncResult
{
    public function __construct(public ?string $customerId)
    {
    }
}
