<?php

namespace App\Services\Enums;


enum Status: string
{
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';
}
