<?php

namespace App\Services\Enums;

use App\Services\Traits\EnumToArray;

enum OrderStatus: string
{
    use EnumToArray;

    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';
}
