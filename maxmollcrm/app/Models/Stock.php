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


    /**
     * Перенос товара со складов в заказ
     *
     * @param int $product_id
     * @param int $count
     * @param int $order_id для логгирования движения товара на складах
     *
     * @return void
     */

    public static function subStocks(int $product_id, int $count, int $order_id): void
    {
        /**
         * Получаем список складов, на которых есть товар
         */
        $stocks = Stock::where('product_id', $product_id)->with('warehouse')->get();

        /**
         * Проверяем наличие необходимого для заказа
         * числа товара на складах
         */
        if ($stocks->sum('stock') < $count) {
            throw new Exception('Not enouth stocks of product = ' . $product_id);
        }

        /**
         * Переносим товар со складов до тех пор, 
         * пока в заказе потребность в товаре не будет равно 0
         */
        $i = 0;
        while ($count > 0) {
            /**
             * Берем i-й склад и забираем с него весь товар (если потребность для заказа больше, числа товара на складе),
             * либо ту часть, которая необходима для заказа
             */

            /**
             * Число товаров, которое забираем с i-го склада
             */
            $difference = min($count, $stocks[$i]->stock);

            /**
             * Вычитаем товары из stocks
             */
            Stock::subStock($stocks[$i], $difference, $order_id);

            /**
             * Уменьшаем потребность товара в заказе на число изъятых со склада товаров
             */
            $count -= $difference;

            /**
             * Следующий склад
             */
            $i++;
        }
    }

    /**
     * Перенос товара со складов в заказ или из заказа на склад
     *
     * @param OrderItem $orderItem
     * @param int $difference если больше 0 - со складов в заказ, меньше 0 - из заказа на склад
     *
     * @return void
     */

    public static function diffStocks(OrderItem $orderItem, int $difference): void
    {

        if ($difference > 0) {
            /**
             * Перещаем товар в заказ со складов
             */
            self::subStocks($orderItem->product_id, $difference, $orderItem->order->id);
            return;
        }

        /**
         * Выбираем склад, на котором размещен заказ
         */
        $stock = Stock::where('product_id', $orderItem->product_id)->where('warehouse_id', $orderItem->order->warehouse_id)->first();

        /**
         * На складе товар отсутствует - добавляем его, количество делаем равным 0
         */
        if ($stock === null) {
            $stock = DB::table('stocks')->insert([
                'product_id' => $orderItem->product_id,
                'warehouse_id' => $orderItem->order->warehouse_id,
                'stock' => 0,
            ]);
            $stock = Stock::where('product_id', $orderItem->product_id)->where('warehouse_id', $orderItem->order->warehouse_id)->first();
        }

        /**
         * Увеличиваем число товара на складе
         */
        Stock::addStock($stock, abs($difference), $orderItem->order->id);
    }

    /**
     * Увеличение числа товара на складе,
     * логгирование
     *
     * @param Stock $stock
     * @param int $difference
     * @param int $order_id для логгирования движения товара на складе
     *
     * @return void
     */

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

    /**
     * Уменьшение числа товара на складе,
     * логгирование
     *
     * @param Stock $stock
     * @param int $difference
     * @param int $order_id для логгирования движения товара на складе
     *
     * @return void
     */
    public static function subStock(Stock $stock, int $difference, int $order_id): void
    {
        /**
         * При равенстве числа товаров на складе и вычитаемого значения,
         * удаляем товар со склада
         */
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
