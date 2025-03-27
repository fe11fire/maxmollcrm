<?php

namespace App\Actions\Order;

use Exception;
use Carbon\Carbon;
use App\Models\Order;
use Illuminate\Http\Response;
use App\Services\Enums\OrderStatus;

class CompleteAction
{
    public function __invoke(int $id): Response
    {
        try {
            /** 
             * Получение заказа для обновления
             */
            $order = Order::findOrFail($id);

            /**
             * Завершить можно только активный заказ
             */
            if ($order->status != OrderStatus::ACTIVE->value) {
                throw new Exception('Order not active');
            }

            /**
             * Обновляем статус заказа, выставляем дату
             */
            $order->update(['status' => OrderStatus::COMPLETED->value, 'completed_at' => Carbon::now()->format('Y-m-d H:i:s')]);
        } catch (Exception $e) {
            return response($e->getMessage(), 400);
        }
        return response(status: 200);
    }
}
