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
        dd($request->validated());
        $validated = $request->validated();
        // $orders = Order::paginate($request->input('per_page', default: 5));


        return new OrderCollection(Order::paginate($request->safe()->input('per_page', default: 5)));
    }
}
