<?php

namespace App\Http\Requests;

use App\Enums\ShareTokenMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateShareTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(ShareTokenMode::class)],
        ];
    }
}
