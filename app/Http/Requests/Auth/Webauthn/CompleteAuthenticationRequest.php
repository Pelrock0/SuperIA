<?php

namespace App\Http\Requests\Auth\Webauthn;

use Illuminate\Foundation\Http\FormRequest;

class CompleteAuthenticationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'handle' => ['required', 'string', 'uuid'],
            'credential' => ['required', 'array'],
        ];
    }
}
