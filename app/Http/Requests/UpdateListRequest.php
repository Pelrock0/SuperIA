<?php

namespace App\Http\Requests;

use App\Enums\ListCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:60'],
            'emoji' => ['sometimes', 'nullable', 'string', 'max:10'],
            'category' => ['sometimes', 'nullable', Rule::enum(ListCategory::class)],
        ];
    }
}
