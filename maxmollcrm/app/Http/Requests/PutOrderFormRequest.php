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
            'id.required' => 'Order_id not found',
            'id.*' => 'Order not isset',
            'items' => 'items',
            'customer' => 'customer',
            'items.*.id.required' => 'items id required',
            'items.*.id.*' => 'not isset',
        ];
    }
}
