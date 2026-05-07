<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveWeeklySummarySelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selected_indices' => ['required', 'array', 'min:1', 'max:50'],
            'selected_indices.*' => ['integer', 'min:0'],
            'target_list_id' => ['nullable', 'integer'],
            'new_list_name' => ['nullable', 'string', 'max:80'],
        ];
    }
}
