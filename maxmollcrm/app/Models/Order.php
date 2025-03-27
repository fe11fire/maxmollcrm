<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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

    public function updateOrInsertItem(int $product_id, int $count): void
    {
        DB::table('order_items')->updateOrInsert(
            [
                'order_id' => $this->id,
                'product_id' => $product_id,
                'count' => $count,
            ],
            ['count' => DB::raw('count + ' . $count)]
        );
    }
}
