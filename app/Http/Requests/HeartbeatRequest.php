<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_uuid' => ['required', 'string', 'size:36', 'uuid'],
        ];
    }
}
