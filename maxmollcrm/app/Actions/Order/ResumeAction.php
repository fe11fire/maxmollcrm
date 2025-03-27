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
            DB::transaction(function () use ($id) {
                $order = Order::findOrFail($id);

                if ($order->status != OrderStatus::CANCELED->value) {
                    throw new Exception('Order not canceled');
                }

                $items = OrderItem::where('order_id', $order->id)->get();

                foreach ($items as $order_item) {
                    $stocks = Stock::where('product_id', $order_item->product_id)->get();
                    if ($stocks->sum('stock') < $order_item->count) {
                        throw new Exception('Not enouth stocks of product = ' . $order_item->product_id);
                    }

                    Stock::subStocks($order_item->product_id, $order_item->count, $order->id);
                }

                $order->update(['status' => OrderStatus::ACTIVE->value]);
            });
        } catch (Exception $e) {
            return response($e->getMessage(), 400);
        }
        return response(status: 200);
    }
}
