<?php

namespace App\Models;

use App\Services\Enums\HistoryStockStatus;
use Exception;
use Ramsey\Uuid\Type\Integer;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Stock extends Model
{
    /** @use HasFactory<\Database\Factories\StockFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'stock',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function subStocks(int $product_id, int $count, int $order_id): void
    {
        $stocks = Stock::where('product_id', $product_id)->with('warehouse')->get();
        if ($stocks->sum('stock') < $count) {
            throw new Exception('Not enouth stocks of product = ' . $product_id);
        }

        $i = 0;
        $count = $count;

        while ($count > 0) {
            $difference = min($count, $stocks[$i]->stock);

            Stock::subStock($stocks[$i], $difference, $order_id);

            $count -= $difference;
            $i++;
        }
    }

    public static function diffStocks(OrderItem $orderItem, int $difference): void
    {
        if ($difference > 0) {
            self::subStocks($orderItem->product_id, $difference, $orderItem->order->id);
            return;
        }

        $stock = Stock::where('product_id', $orderItem->product_id)->where('warehouse_id', $orderItem->order->warehouse_id)->first();

        if ($stock === null) {
            $stock = DB::table('stocks')->insert([
                'product_id' => $orderItem->product_id,
                'warehouse_id' => $orderItem->order->warehouse_id,
                'stock' => 0,
            ]);
            $stock = Stock::where('product_id', $orderItem->product_id)->where('warehouse_id', $orderItem->order->warehouse_id)->first();
        }

        Stock::addStock($stock, abs($difference), $orderItem->order->id);
    }

    public static function addStock(Stock $stock, int $difference, int $order_id): void
    {
        DB::table('stocks')->where('product_id', $stock->product_id)->where('warehouse_id', $stock->warehouse_id)->increment('stock', $difference);

        HistoryStock::create(
            [
                'warehouse_id' => $stock->warehouse_id,
                'product_id' => $stock->product_id,
                'stock' => $stock->stock + $difference,
                'diff' => $difference,
                'status' => HistoryStockStatus::FROM_ORDER->value,
                'order_id' => $order_id,
            ]
        );
    }

    public static function subStock(Stock $stock, int $difference, int $order_id): void
    {
        if ($difference == $stock->stock) {
            DB::table('stocks')->where('product_id', $stock->product_id)->where('warehouse_id', $stock->warehouse_id)->delete();
        } else {
            DB::table('stocks')->where('product_id', $stock->product_id)->where('warehouse_id', $stock->warehouse_id)->decrement('stock', $difference);
        }

        HistoryStock::create(
            [
                'warehouse_id' => $stock->warehouse_id,
                'product_id' => $stock->product_id,
                'stock' => $stock->stock - $difference,
                'diff' => -$difference,
                'status' => HistoryStockStatus::TO_ORDER->value,
                'order_id' => $order_id,
            ]
        );
    }

    protected $hidden = [
        'warehouse_id',
        'product_id',
    ];
}
