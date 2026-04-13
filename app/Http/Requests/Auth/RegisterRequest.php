<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Auth\Concerns\HasPasswordRules;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    use HasPasswordRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
            'privacy_accepted' => ['required', 'accepted'],
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            ...$this->passwordMessages(),
            'privacy_accepted.accepted' => 'Debes aceptar la politica de privacidad.',
        ];
    }
}
