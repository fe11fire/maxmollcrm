<?php

namespace App\Http\Controllers;

use App\Http\Requests\HistoryStockFormRequest;
use App\Models\HistoryStock;
use Carbon\Carbon;

class HistoryStockController extends Controller
{
    public function __invoke(HistoryStockFormRequest $request)
    {
        $query = HistoryStock::query()
            ->warehouse($request->safe()->warehouse_id)
            ->product($request->safe()->warehouse_id)
            ->period_start($request->safe()->period_start)
            ->period_end($request->safe()->period_end);

        return $query->orderByDesc('id')->paginate();
    }
}
