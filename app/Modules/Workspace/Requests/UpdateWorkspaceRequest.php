<?php

namespace App\Modules\Workspace\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'max:255'],
            'marketplace' => ['sometimes', 'string', 'max:10'],
            'currency'    => ['sometimes', 'string', 'size:3'],
            'settings'    => ['sometimes', 'array'],
        ];
    }
}
