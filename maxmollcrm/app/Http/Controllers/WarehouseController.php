<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;

class WarehouseController extends Controller
{
    public function __invoke()
    {
        return Warehouse::all();
    }
}
