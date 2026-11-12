<?php

namespace App\Actions\Order;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Stock;
use App\Services\Enums\OrderStatus;
use Exception;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ResumeAction
{
    public function __invoke(int $id): Response
    {
        try {
            /** Проводим серию транзакций */
            DB::transaction(function () use ($id) {
                /**
                 * Получение заказа для обновления
                 */
                $order = Order::lockForUpdate()->findOrFail($id);

                /**
                 * Восстановить можно только отмененный заказ
                 */
                if ($order->status != OrderStatus::CANCELED->value) {
                    throw new Exception('Order not canceled');
                }

                /**
                 * Получаем список товаров в заказе
                 */
                $items = OrderItem::where('order_id', $order->id)->orderBy('product_id')->get();

                foreach ($items as $order_item) {
                    /**
                     * Перемещаем товар со складов в заказ
                     */
                    Stock::subStocks($order_item->product_id, $order_item->count, $order->id);
                }

                /**
                 * Обновляем статус заказа, выставляем дату
                 */
                $order->update(['status' => OrderStatus::ACTIVE->value]);
            }, 3);
        } catch (Exception $e) {
            return response($e->getMessage(), 400);
        }

        return response(status: 200);
    }
}
