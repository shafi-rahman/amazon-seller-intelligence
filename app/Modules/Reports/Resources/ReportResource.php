<?php

namespace App\Modules\Reports\Resources;

use App\Modules\Reports\Services\ReportGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'type'         => $this->type,
            'title'        => $this->title,
            'file_format'  => $this->file_format,
            'status'       => $this->status,
            'parameters'   => $this->parameters,
            'generated_at' => $this->generated_at?->toISOString(),
            // Null-safe: Report has $timestamps=false and created_at is set by a DB
            // default, so a freshly-created (un-refreshed) model has null here → 500.
            'created_at'   => $this->created_at?->toISOString(),
            'has_file'     => !empty($this->file_path),
        ];
    }
}
