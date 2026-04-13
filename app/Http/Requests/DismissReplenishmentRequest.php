<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DismissReplenishmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'producto_nombre' => ['required', 'string', 'min:1', 'max:80'],
        ];
    }
}
