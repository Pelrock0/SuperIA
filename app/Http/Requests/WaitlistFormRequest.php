<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WaitlistFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'shopping_companion' => ['nullable', 'string', 'in:solo,pareja,familia,compañeros'],
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede superar los 100 caracteres.',
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email no es válido.',
            'shopping_companion.in' => 'La opción seleccionada no es válida.',
        ];
    }
}
