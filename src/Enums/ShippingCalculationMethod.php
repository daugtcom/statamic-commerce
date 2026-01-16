<?php

namespace Daugt\Commerce\Enums;

enum ShippingCalculationMethod: string
{
    case SUM = 'sum';
    case MAX = 'max';
}
