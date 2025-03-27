<?php

namespace App\Http\Requests;

use App\Services\Enums\OrderStatus;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Foundation\Http\FormRequest;

class HistoryStockFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'product_id' => 'nullable|exists:products,id',
            'period_start' => 'nullable|multi_date_format:Y-m-d,Y-m-d H:i:s,Y-m,Y',
            'period_end' => 'nullable|multi_date_format:Y-m-d,Y-m-d H:i:s,Y-m,Y',
            'per_page' => 'nullable|integer|gt:0',
            'page' => 'nullable|integer|gt:0',
        ];
    }

    public function messages()
    {
        return [
            'warehouse_id.*' => 'Warehouse not isset',
            'product_id.*' => 'Product not isset',
            'period_start.*' => 'period_start wrong format',
            'period_end.*' => 'period_end wrong format',
        ];
    }
}
