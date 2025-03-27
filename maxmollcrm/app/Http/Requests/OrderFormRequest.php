<?php

namespace App\Http\Requests;

use App\Services\Enums\OrderStatus;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Foundation\Http\FormRequest;

class OrderFormRequest extends FormRequest
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
            'customer' => 'nullable|string',
            'status' => ['nullable', new Enum(OrderStatus::class)],
            'per_page' => 'nullable|integer|gt:0',
            'page' => 'nullable|integer|gt:0',
        ];
    }

    public function messages()
    {
        return [
            'customer.required' => 'Customer required',
            'customer.*' => 'Customer must be string',
            'status.*' => 'Status must be correct value',
        ];
    }
}
