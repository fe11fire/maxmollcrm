<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Order;
use App\Models\Stock;
use App\Models\OrderItem;
use App\Models\Warehouse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\OrderFormRequest;
use App\Http\Resources\OrderCollection;
use App\Http\Requests\PutOrderFormRequest;
use App\Http\Requests\PostOrderFormRequest;
use App\Services\Enums\OrderStatus;
use Carbon\Carbon;

class OrderController extends Controller
{
    public function index(OrderFormRequest $request)
    {
        $orders = Order::select();
        if ($request->safe()->input('status') !== null) {
            $orders = $orders->where('status', $request->safe()->input('status'));
        }
        if ($request->safe()->input('customer') !== null) {
            $orders = $orders->where('customer', 'LIKE', '%' . $request->safe()->input('customer') . '%');
        }

        return new OrderCollection(
            $orders->paginate($request->safe()->input('per_page', default: 5))
        );
    }

    public function resume(PutOrderFormRequest $request)
    {
        try {
            DB::transaction(function () use ($request) {
                $order = Order::findOrFail($request->id);

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

    public function cancel(PutOrderFormRequest $request)
    {
        try {
            $order = Order::findOrFail($request->id);

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

    public function complete(PutOrderFormRequest $request)
    {
        try {
            $order = Order::findOrFail($request->id);

            if ($order->status != OrderStatus::ACTIVE->value) {
                throw new Exception('Order not active');
            }

            $order->update(['status' => OrderStatus::COMPLETED->value, 'completed_at' => Carbon::now()->format('Y-m-d H:i:s')]);
        } catch (Exception $e) {
            return response($e->getMessage(), 400);
        }
        return response(status: 200);
    }

    public function update(PutOrderFormRequest $request)
    {
        try {
            DB::transaction(function () use ($request) {
                $order = Order::findOrFail($request->id);

                if ($order->status != OrderStatus::ACTIVE->value) {
                    throw new Exception('Order not active');
                }

                if (($items = $request->safe()->items) !== null) {
                    $null_items = OrderItem::whereNotIn('product_id', Arr::pluck($items, 'id'))->where('order_id', $order->id)->get();
                    foreach ($null_items as $order_item) {
                        Stock::diffStocks($order_item, -$order_item->count);
                        $order_item->delete();
                    }

                    foreach ($items as $item) {
                        $order_item = OrderItem::where('product_id', $item['id'])->where('order_id', $order->id)->first();
                        if ($order_item === null) {
                            OrderItem::create([
                                'order_id' => $order->id,
                                'product_id' => $item['id'],
                                'count' => $item['count'],
                            ]);
                        } else {
                            if (($difference = ($item['count'] - $order_item->count)) <> 0) {
                                Stock::diffStocks($order_item, $difference);
                                $order_item->update(['count' => DB::raw('count + ' . $difference)]);
                            }
                        }
                    }

                    $order_item = OrderItem::where('product_id', $item['id'])->where('order_id', $order->id)->first();
                }

                if (($customer = $request->safe()->customer) !== null) {
                    $order->update(['customer' => $customer]);
                }
            });
        } catch (\Exception $e) {
            return response($e->getMessage(), 400);
        }
        return response(status: 200);
    }

    public function create(PostOrderFormRequest $request)
    {
        try {
            DB::transaction(function () use ($request) {
                $warehouse_id = $request->safe()->warehouse_id == null ? Warehouse::first()->id : $request->safe()->warehouse_id;

                /** @var Order $order */
                $order = Order::create([
                    'warehouse_id' => $warehouse_id,
                    'customer' => $request->customer,
                ]);

                foreach ($request->items as $product) {
                    Stock::subStocks($product['id'], $product['count'], $order->id);
                }

                foreach ($request->items as $product) {
                    $order->updateOrInsertItem($product['id'], $product['count']);
                }
            });
        } catch (\Exception $e) {
            return response($e->getMessage(), 400);
        }
        return response(status: 200);
    }
}
