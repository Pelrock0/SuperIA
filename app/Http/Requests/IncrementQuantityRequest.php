<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IncrementQuantityRequest extends FormRequest
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
            'quantity' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
