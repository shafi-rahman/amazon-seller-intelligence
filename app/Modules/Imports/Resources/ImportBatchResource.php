<?php

namespace App\Modules\Imports\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pct = $this->total_rows > 0
            ? (int) round($this->processed_rows / $this->total_rows * 100)
            : 0;

        return [
            'id'               => $this->id,
            'type'             => $this->type,
            'original_filename'=> $this->original_filename,
            'status'           => $this->status,
            'total_rows'       => $this->total_rows,
            'processed_rows'   => $this->processed_rows,
            'failed_rows'      => $this->failed_rows,
            'percent'          => $pct,
            'started_at'       => $this->started_at?->toISOString(),
            'completed_at'     => $this->completed_at?->toISOString(),
            'created_at'       => $this->created_at->toISOString(),
        ];
    }
}
