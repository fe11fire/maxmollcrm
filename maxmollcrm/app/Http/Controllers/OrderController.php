<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\OrderFormRequest;
use App\Http\Resources\OrderCollection;
use App\Http\Requests\PostOrderFormRequest;
use App\Models\Stock;

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

    public function create(PostOrderFormRequest $request)
    {
        foreach ($request->items as $product) {
            $stocks = Stock::where('product_id', $product['id'])->with('warehouse')->get();
            if ($stocks->sum('stock') < $product['count']) {
                return false;
            }

            $i = 0;
            $count = $product['count'];
            while ($count > 0) {
                $difference = min($count, $stocks[$i]->stock);
                $count -= $difference;
                Stock::where('product_id', $stocks[$i]->product_id)->where('stock', $stocks[$i]->stock)->where('warehouse_id', $stocks[$i]->warehouse_id)->decrement('stock', $difference);
                $i++;
            }


            dd($stocks->sum('stock'));
            dd(Stock::where('product_id', $product['id'])->with('warehouse')->get());
        }

        DB::transaction(function () use ($request) {
            foreach ($request->items as $product) {
            }
            // $alice = User::lockForUpdate()->find(1); // 'balance' => 100
            // $bob = User::lockForUpdate()->find(2); // 'balance' => 0

            // Bank::sendMoney($alice, $bob, 100); // true
        });


        dd($request->items);
    }
}
