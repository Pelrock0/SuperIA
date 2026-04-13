<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ComplementQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product' => ['required', 'string', 'min:1', 'max:80'],
            'list_id' => ['required', 'integer', 'exists:shopping_lists,id'],
        ];
    }
}
