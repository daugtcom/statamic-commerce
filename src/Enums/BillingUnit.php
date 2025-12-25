<?php

namespace Daugt\Commerce\Enums;

enum BillingUnit: string
{
    case HOUR = 'hour';

    case DAY = 'day';

    case WEEK = 'week';

    case MONTH = 'month';

    case YEAR = 'year';
}