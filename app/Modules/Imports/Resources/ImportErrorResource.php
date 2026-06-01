<?php

namespace App\Modules\Imports\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportErrorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'row_number'   => $this->row_number,
            'error_type'   => $this->error_type,
            'error_message'=> $this->error_message,
            'raw_data'     => $this->raw_data,
            'created_at'   => $this->created_at?->toISOString(),
        ];
    }
}
