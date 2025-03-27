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
            $order = Order::findOrFail($id);

            if ($order->status != OrderStatus::ACTIVE->value) {
                throw new Exception('Order not active');
            }

            $order->update(['status' => OrderStatus::COMPLETED->value, 'completed_at' => Carbon::now()->format('Y-m-d H:i:s')]);
        } catch (Exception $e) {
            return response($e->getMessage(), 400);
        }
        return response(status: 200);
    }
}
