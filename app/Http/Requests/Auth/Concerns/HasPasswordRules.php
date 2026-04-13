<?php

namespace App\Http\Requests\Auth\Concerns;

trait HasPasswordRules
{
    protected function passwordRules(bool $confirmed = true): array
    {
        $rules = [
            'required',
            'string',
            'min:8',
            'regex:/[A-Z]/',
            'regex:/[0-9]/',
        ];

        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }

    protected function passwordMessages(): array
    {
        return [
            'password.regex' => 'La contraseña debe contener al menos una mayúscula y un número.',
        ];
    }
}
