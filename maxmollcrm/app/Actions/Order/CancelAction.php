<?php

namespace App\Actions\Order;

use Exception;
use App\Models\Order;
use App\Models\Stock;
use App\Models\OrderItem;
use Illuminate\Http\Response;
use App\Services\Enums\OrderStatus;

class CancelAction
{

    /**
     * Cancel order
     *
     * @param int $id
     *
     * @return Response
     */
    public function __invoke(int $id): Response
    {
        try {
            /** 
             * Получение заказа для обновления
             */
            $order = Order::findOrFail($id);

            /**
             * Отменить можно только активный заказ
             */
            if ($order->status != OrderStatus::ACTIVE->value) {
                throw new Exception('Order not active');
            }

            /**
             * Получаем все товары из заказа
             */
            $canceled_items = OrderItem::where('order_id', $order->id)->get();
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
        } catch (Exception $e) {
            return response($e->getMessage(), 400);
        }
        return response(status: 200);
    }
}
