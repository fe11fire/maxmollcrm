<?php

namespace App\Models;

use App\Services\Enums\HistoryStockStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class Stock extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['product_id', 'warehouse_id', 'stock'];

    protected $hidden = ['warehouse_id', 'product_id'];

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
        self::validateQuantity($count);
        if ($count === 0) {
            return;
        }

        DB::transaction(function () use ($product_id, $count, $order_id) {
            // The product row also serializes movements when no stock row exists yet.
            Product::whereKey($product_id)->lockForUpdate()->firstOrFail();
            $stocks = self::where('product_id', $product_id)
                ->orderBy('warehouse_id')->lockForUpdate()->get();

            if ($stocks->sum('stock') < $count) {
                throw new RuntimeException('Not enough stocks of product = '.$product_id);
            }

            foreach ($stocks as $stock) {
                $quantity = min($count, $stock->stock);
                if ($quantity > 0) {
                    self::subStock($stock, $quantity, $order_id);
                    $count -= $quantity;
                }
                if ($count === 0) {
                    break;
                }
            }
        }, 3);
    }

    /** Positive difference reserves goods; negative difference returns them to the order warehouse. */
    public static function diffStocks(OrderItem $orderItem, int $difference): void
    {
        if ($difference === 0) {
            return;
        }
        $order = $orderItem->order;
        if ($difference > 0) {
            self::subStocks($orderItem->product_id, $difference, $order->id);

            return;
        }

        $stock = new self;
        $stock->product_id = $orderItem->product_id;
        $stock->warehouse_id = $order->warehouse_id;
        self::addStock($stock, abs($difference), $order->id);
    }

    public static function addStock(Stock $stock, int $difference, int $order_id): void
    {
        self::move($stock, $difference, $order_id, true);
    }

    public static function subStock(Stock $stock, int $difference, int $order_id): void
    {
        self::move($stock, $difference, $order_id, false);
    }

    private static function validateQuantity(int $quantity): void
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Stock quantity must not be negative.');
        }
    }

    private static function move(Stock $stock, int $quantity, int $order_id, bool $return): void
    {
        self::validateQuantity($quantity);
        if ($quantity === 0) {
            return;
        }

        DB::transaction(function () use ($stock, $quantity, $order_id, $return) {
            Product::whereKey($stock->product_id)->lockForUpdate()->firstOrFail();
            $query = DB::table('stocks')->where('product_id', $stock->product_id)
                ->where('warehouse_id', $stock->warehouse_id);
            $current = (clone $query)->lockForUpdate()->first();
            $before = (int) ($current->stock ?? 0);
            if (! $return && $before < $quantity) {
                throw new RuntimeException('Not enough stocks of product = '.$stock->product_id);
            }
            $difference = $return ? $quantity : -$quantity;
            $after = $before + $difference;
            if ($after === 0) {
                $query->delete();
            } elseif ($current === null) {
                DB::table('stocks')->insert([
                    'product_id' => $stock->product_id,
                    'warehouse_id' => $stock->warehouse_id,
                    'stock' => $after,
                ]);
            } else {
                $query->update(['stock' => $after]);
            }

            HistoryStock::create([
                'warehouse_id' => $stock->warehouse_id,
                'product_id' => $stock->product_id,
                'stock' => $after,
                'diff' => $difference,
                'status' => $return ? HistoryStockStatus::FROM_ORDER->value : HistoryStockStatus::TO_ORDER->value,
                'order_id' => $order_id,
            ]);
        }, 3);
    }
}
