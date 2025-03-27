<?php

namespace App\Http\Controllers;

use App\Http\Requests\HistoryStockFormRequest;
use App\Models\HistoryStock;
use Carbon\Carbon;

class HistoryStockController extends Controller
{
    public function __invoke(HistoryStockFormRequest $request)
    {
        $query = HistoryStock::select();
        if (($warehouse_id = $request->safe()->warehouse_id) !== null) {
            $query = $query->where('warehouse_id', $warehouse_id);
        }
        if (($product_id = $request->safe()->product_id) !== null) {
            $query = $query->where('product_id', $product_id);
        }
        if (($period_start = $request->safe()->period_start) !== null) {
            $query = $query->where('created_at', '>=', Carbon::parse($period_start));
        }
        if (($period_end = $request->safe()->period_end) !== null) {
            $query = $query->where('created_at', '<=', Carbon::parse($period_end));
        }
        return $query->orderByDesc('id')->paginate();
    }
}
