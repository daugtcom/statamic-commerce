<?php

namespace Daugt\Commerce\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case FAILED = 'failed';
    case PAID = 'paid';
}
