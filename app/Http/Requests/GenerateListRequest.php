<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateListRequest extends FormRequest
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
            'description' => ['required', 'string', 'max:500'],
            'people' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
