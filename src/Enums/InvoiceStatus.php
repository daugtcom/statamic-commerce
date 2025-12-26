<?php

namespace Daugt\Commerce\Enums;

enum InvoiceStatus: string
{
    case PENDING = 'pending';
    case FAILED = 'failed';
    case PAID = 'paid';
}
