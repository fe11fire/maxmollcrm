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
            'customer' => 'nullable|string|max:255',
            'items' => 'nullable|array',
            'items.*.id' => 'required|integer|distinct|exists:products,id',
            'items.*.count' => 'required|integer|min:1|max:2147483647',
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
