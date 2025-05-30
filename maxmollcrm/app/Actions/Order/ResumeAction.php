<?php

namespace App\Actions\Order;

use Exception;
use App\Models\Order;
use App\Models\Stock;
use App\Models\OrderItem;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Services\Enums\OrderStatus;

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
                $order = Order::findOrFail($id);

                /**
                 * Восстановить можно только отмененный заказ
                 */
                if ($order->status != OrderStatus::CANCELED->value) {
                    throw new Exception('Order not canceled');
                }

                /**
                 * Получаем список товаров в заказе
                 */
                $items = OrderItem::where('order_id', $order->id)->get();

                foreach ($items as $order_item) {
                    /**
                     * Проверяем наличие необходимого количества товара для заказа
                     */
                    $stocks = Stock::where('product_id', $order_item->product_id)->get();
                    if ($stocks->sum('stock') < $order_item->count) {
                        throw new Exception('Not enouth stocks of product = ' . $order_item->product_id);
                    }

                    /**
                     * Перемещаем товар со складов в заказ
                     */
                    Stock::subStocks($order_item->product_id, $order_item->count, $order->id);
                }

                /**
                 * Обновляем статус заказа, выставляем дату
                 */
                $order->update(['status' => OrderStatus::ACTIVE->value]);
            });
        } catch (Exception $e) {
            return response($e->getMessage(), 400);
        }
        return response(status: 200);
    }
}
