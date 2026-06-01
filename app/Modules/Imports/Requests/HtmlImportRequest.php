<?php

namespace App\Modules\Imports\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HtmlImportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
            'html_content' => ['required', 'string', 'min:200'],
            'product_id'   => ['nullable', 'integer', 'exists:products,id'],
            'asin'         => ['nullable', 'string', 'max:20'],
        ];
    }
}
