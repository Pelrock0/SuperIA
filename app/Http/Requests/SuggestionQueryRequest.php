<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SuggestionQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:60'],
            'include_ai' => ['sometimes', 'boolean'],
        ];
    }
}
