<?php

namespace App\QueryBuilders;


use Illuminate\Database\Eloquent\Builder;

class OrderQueryBuilder extends Builder
{
    public function status(string $status = null): OrderQueryBuilder
    {
        return $status == null ? $this : $this->where('status', $status);
    }

    public function customer(string $customer = null): OrderQueryBuilder
    {
        return $customer == null ? $this : $this->where('customer', 'LIKE', '%' . $customer . '%');
    }
}
