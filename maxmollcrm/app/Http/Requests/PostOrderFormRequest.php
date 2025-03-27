<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostOrderFormRequest extends FormRequest
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
            'customer' => 'required|string',
            'items' => 'required|array',
            'items.*.id' => 'required|exists:products',
            'items.*.count' => 'gt:0',
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ];
    }

    public function messages()
    {
        return [
            'customer.required' => 'Customer required',
            'customer.*' => 'Customer must be string',
            'items' => 'Product must be array of objects {product_id, count}',
            'items.*.id.required' => 'product_id required',
            'items.*.id.*' => 'Product not isset',
            'items.*.count' => 'Count must be greater than 0',
            'warehouse_id.*' => 'Warehouse not isset',
        ];
    }
}
