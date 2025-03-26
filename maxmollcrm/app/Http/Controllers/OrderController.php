<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderFormRequest;
use App\Models\Order;
use App\Http\Resources\OrderCollection;
use Illuminate\Http\Request;

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

    public function create(Request $request)
    {

        dd(json_decode($request->items));
    }
}
