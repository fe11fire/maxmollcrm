<?php

namespace App\Actions\Order;

use App\Models\Order;
use App\Services\Enums\OrderStatus;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CompleteAction
{
    public function __invoke(int $id): Response
    {
        try {
            DB::transaction(function () use ($id) {
                /**
                 * Получение заказа для обновления
                 */
                $order = Order::lockForUpdate()->findOrFail($id);

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
            }, 3);
        } catch (Exception $e) {
            return response($e->getMessage(), 400);
        }

        return response(status: 200);
    }
}
