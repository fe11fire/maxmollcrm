<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PutOrderFormRequest extends FormRequest
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
            'id' => 'required|exists:orders,id',
            'customer' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.id' => 'required|exists:products',
            'items.*.count' => 'gt:0',
        ];
    }

    public function messages()
    {
        return [
            'id.required' => 'id of Order required',
            'id.*' => 'Order not isset',
            'items' => 'Product must be array of objects {product_id, count}',
            'customer' => 'Customer must be string or null',
            'items.*.id.required' => 'product_id required',
            'items.*.id.*' => 'Product not isset',
            'items.*.count' => 'Count must be greater than 0',
        ];
    }
}
