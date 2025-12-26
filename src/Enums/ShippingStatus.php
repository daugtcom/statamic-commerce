<?php

namespace Daugt\Commerce\Enums;

enum ShippingStatus: string
{
    case PENDING = 'pending';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
}
