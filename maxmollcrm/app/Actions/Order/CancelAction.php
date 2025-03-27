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
    public function __invoke(int $id): Response
    {
        try {
            $order = Order::findOrFail($id);

            if ($order->status != OrderStatus::ACTIVE->value) {
                throw new Exception('Order not active');
            }

            $canceled_items = OrderItem::where('order_id', $order->id)->get();
            foreach ($canceled_items as $order_item) {
                Stock::diffStocks($order_item, -$order_item->count);
            }

            $order->update(['status' => OrderStatus::CANCELED->value]);
        } catch (Exception $e) {
            return response($e->getMessage(), 400);
        }
        return response(status: 200);
    }
}
