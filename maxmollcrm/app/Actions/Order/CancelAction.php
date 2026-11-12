<?php

namespace App\Actions\Order;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Stock;
use App\Services\Enums\OrderStatus;
use Exception;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CancelAction
{
    /**
     * Cancel order
     */
    public function __invoke(int $id): Response
    {
        try {
            DB::transaction(function () use ($id) {
                /**
                 * Получение заказа для обновления
                 */
                $order = Order::lockForUpdate()->findOrFail($id);

                /**
                 * Отменить можно только активный заказ
                 */
                if ($order->status != OrderStatus::ACTIVE->value) {
                    throw new Exception('Order not active');
                }

                /**
                 * Получаем все товары из заказа
                 */
                $canceled_items = OrderItem::where('order_id', $order->id)->orderBy('product_id')->get();
                foreach ($canceled_items as $order_item) {
                    /**
                     * Возвращаем на склад товар из заказа
                     */
                    Stock::diffStocks($order_item, -$order_item->count);
                }

                /**
                 * Обновляем статус заказа
                 */
                $order->update(['status' => OrderStatus::CANCELED->value]);
            }, 3);
        } catch (Exception $e) {
            return response($e->getMessage(), 400);
        }

        return response(status: 200);
    }
}
