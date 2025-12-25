<?php

namespace Daugt\Commerce\Enums;

enum AccessType: string
{
    case DATE_RANGE = 'date_range';

    case DURATION = 'duration';

    case PERMANENT = 'permanent';
}
