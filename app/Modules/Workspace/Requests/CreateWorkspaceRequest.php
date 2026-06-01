<?php

namespace App\Modules\Workspace\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateWorkspaceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'marketplace' => ['nullable', 'string', 'max:10'],
            'currency'    => ['nullable', 'string', 'size:3'],
            'settings'    => ['nullable', 'array'],
        ];
    }
}
