<?php

namespace App\Http\Controllers;

use App\Models\Product;

class StockController extends Controller
{
    public function __invoke()
    {
        return Product::with(['stocks', 'stocks.warehouse'])->get();
    }
}
