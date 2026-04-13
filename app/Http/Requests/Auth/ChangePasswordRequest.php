<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Auth\Concerns\HasPasswordRules;
use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    use HasPasswordRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => $this->passwordRules(),
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return $this->passwordMessages();
    }
}
