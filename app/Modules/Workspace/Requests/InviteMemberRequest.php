<?php

namespace App\Modules\Workspace\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InviteMemberRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'role'  => ['nullable', 'string', 'in:owner,admin,editor,viewer,accountant'],
        ];
    }
}
