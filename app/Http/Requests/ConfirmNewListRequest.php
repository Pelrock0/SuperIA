<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmNewListRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.nombre' => ['required', 'string', 'max:80'],
            'items.*.cantidad_tipica' => ['nullable', 'numeric', 'min:0'],
            'items.*.unidad_tipica' => ['nullable', 'string', 'max:10'],
            'items.*.categoria' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:60'],
        ];
    }
}
