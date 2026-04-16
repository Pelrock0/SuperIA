<?php

namespace App\Http\Requests\Auth\Webauthn;

use Illuminate\Foundation\Http\FormRequest;

class BeginAuthenticationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }
}
