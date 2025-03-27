<?php

namespace App\QueryBuilders;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class HistoryStockQueryBuilder extends Builder
{
    public function warehouse(int $warehouse_id = null): HistoryStockQueryBuilder
    {
        return $warehouse_id == null ? $this : $this->where('warehouse_id', $warehouse_id);
    }

    public function product(int $product_id = null): HistoryStockQueryBuilder
    {
        return $product_id == null ? $this : $this->where('product_id', $product_id);
    }

    public function period_start(string $period_start = null): HistoryStockQueryBuilder
    {
        return $period_start == null ? $this : $this->where('created_at', '>=', Carbon::parse($period_start));
    }

    public function period_end(string $period_end = null): HistoryStockQueryBuilder
    {
        return $period_end == null ? $this : $this->where('created_at', '<=', Carbon::parse($period_end));
    }
}
