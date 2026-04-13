<?php

namespace App\Http\Requests;

use App\Enums\ItemUnit;
use App\Enums\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'quantity' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'nullable', Rule::enum(ItemUnit::class)],
            'category' => ['sometimes', 'nullable', Rule::enum(ProductCategory::class)],
            'estimated_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
