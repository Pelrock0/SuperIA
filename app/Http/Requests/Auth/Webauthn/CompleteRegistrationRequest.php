<?php

namespace App\Http\Requests\Auth\Webauthn;

use Illuminate\Foundation\Http\FormRequest;

class CompleteRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'handle' => ['required', 'string', 'uuid'],
            'name' => ['required', 'string', 'min:1', 'max:50'],
            'credential' => ['required', 'array'],
        ];
    }
}
