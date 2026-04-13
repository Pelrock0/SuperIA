<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptReplenishmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'producto_nombre' => ['required', 'string', 'min:1', 'max:80'],
            'list_id' => ['required', 'integer', 'exists:shopping_lists,id'],
        ];
    }
}
