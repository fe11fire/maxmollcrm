<?php
namespace App\Services\Enums;

use App\Services\Traits\EnumToArray;

enum HistoryStockStatus: string
{
    use EnumToArray;

    case CREATED = 'created';
    case TO_ORDER = 'to_order';
    case FROM_ORDER = 'from_order';
}