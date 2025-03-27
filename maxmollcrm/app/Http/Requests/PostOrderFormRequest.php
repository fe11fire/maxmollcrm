<?php

namespace App\Http\Requests;

use App\Services\Enums\Status;
use Illuminate\Validation\Rules\Enum;
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
            'items' => 'json',
            'customer' => 'customer',
            'items.*.id.required' => 'items id required',
            'items.*.id.*' => 'not isset',
        ];
    }
}
