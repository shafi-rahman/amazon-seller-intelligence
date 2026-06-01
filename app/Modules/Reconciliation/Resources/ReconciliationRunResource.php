<?php

namespace App\Modules\Reconciliation\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'period_start' => $this->period_start?->toDateString(),
            'period_end'   => $this->period_end?->toDateString(),
            'status'       => $this->status,
            'summary'      => $this->summary,
            'started_at'   => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at'   => $this->created_at->toISOString(),
            'reports'      => $this->whenLoaded('reports', fn() =>
                $this->reports->map(fn($r) => [
                    'id'          => $r->id,
                    'report_type' => $r->report_type,
                    'count'       => $r->report_data['count'] ?? $r->report_data['total_refunds'] ?? $r->report_data['total_returns'] ?? 0,
                    'export_path' => $r->export_path,
                ])
            ),
        ];
    }
}
