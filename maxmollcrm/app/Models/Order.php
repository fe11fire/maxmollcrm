<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use App\QueryBuilders\OrderQueryBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @method static Order | OrderQueryBuilder query()
 * 
 */

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

    protected $table = 'orders';

    protected $fillable = [
        'warehouse_id',
        'customer',
        'status',
        'completed_at'
    ];

    public function newEloquentBuilder($query): OrderQueryBuilder
    {
        return new OrderQueryBuilder($query);
    }
}
