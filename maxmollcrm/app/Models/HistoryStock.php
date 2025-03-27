<?php

namespace App\Models;

use App\QueryBuilders\HistoryStockQueryBuilder;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static HistoryStock | HistoryStockQueryBuilder query()
 * 
 */

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

    public function newEloquentBuilder($query): HistoryStockQueryBuilder
    {
        return new HistoryStockQueryBuilder($query);
    }
}
