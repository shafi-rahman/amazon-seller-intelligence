<?php

namespace App\Modules\Imports\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadImportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
            'type'         => ['required', 'string', Rule::in([
                'orders', 'settlements', 'bank_statement', 'gst_report',
                'products', 'competitors_csv',
            ])],
            'file'         => ['required', 'file', 'max:51200', 'mimetypes:text/plain,text/csv,application/vnd.ms-excel,application/octet-stream,application/csv'],
        ];
    }
}
