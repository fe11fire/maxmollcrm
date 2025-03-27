<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoryStock extends Model
{
    protected $fillable = [
        'warehouse_id',
        'product_id',
        'diff',
        'order_id',
        'stock',
        'status'
    ];
}
