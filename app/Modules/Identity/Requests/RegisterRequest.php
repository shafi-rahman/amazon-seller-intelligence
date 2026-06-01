<?php

namespace App\Modules\Identity\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'workspace_name'        => ['nullable', 'string', 'max:255'],
            'marketplace'           => ['nullable', 'string', 'max:10'],
            'currency'              => ['nullable', 'string', 'size:3'],
        ];
    }
}
