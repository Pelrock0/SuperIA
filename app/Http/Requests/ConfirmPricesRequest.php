<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('api')->check();
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'total' => ['nullable', 'numeric', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
