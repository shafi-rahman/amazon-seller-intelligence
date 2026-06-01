<?php

namespace App\Modules\Imports\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmMappingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'mapping'   => ['required', 'array'],
            'mapping.*' => ['nullable', 'string'],
        ];
    }
}
