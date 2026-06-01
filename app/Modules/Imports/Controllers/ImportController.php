<?php

namespace App\Modules\Imports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Imports\Requests\ConfirmMappingRequest;
use App\Modules\Imports\Requests\HtmlImportRequest;
use App\Modules\Imports\Requests\UploadImportRequest;
use App\Modules\Imports\Resources\ImportBatchResource;
use App\Modules\Imports\Resources\ImportErrorResource;
use App\Modules\Imports\Services\ImportService;
use App\Modules\Workspace\Models\Workspace;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ImportService $importService) {}

    public function upload(UploadImportRequest $request): JsonResponse
    {
        $workspace = Workspace::findOrFail($request->validated('workspace_id'));
        abort_unless($workspace->hasMember($request->user()), 403);

        $batch = $this->importService->upload(
            $workspace,
            $request->user()->id,
            $request->validated('type'),
            $request->file('file'),
        );

        return $this->created([
            'import_batch_id'        => $batch->id,
            'status'                 => $batch->status,
            'total_rows'             => $batch->total_rows,
            'detected_columns'       => $batch->meta['detected_columns'] ?? [],
            'suggested_mapping'      => $batch->column_mapping,
            'row_sample'             => $batch->meta['row_sample'] ?? [],
            'requires_confirmation'  => true,
        ]);
    }

    public function uploadHtml(HtmlImportRequest $request): JsonResponse
    {
        $workspace = Workspace::findOrFail($request->validated('workspace_id'));
        abort_unless($workspace->hasMember($request->user()), 403);

        $batch = $this->importService->uploadHtml(
            $workspace,
            $request->user()->id,
            $request->validated('html_content'),
            $request->validated('product_id'),
            $request->validated('asin'),
        );

        return $this->created([
            'import_batch_id' => $batch->id,
            'status'          => $batch->status,
            'type'            => 'competitors_html',
        ]);
    }

    public function confirmMapping(ConfirmMappingRequest $request, ImportBatch $importBatch): JsonResponse
    {
        abort_unless($importBatch->workspace->hasMember($request->user()), 403);
        abort_unless($importBatch->status === 'awaiting_mapping', 422, 'Batch is not awaiting mapping confirmation.');

        $batch = $this->importService->confirmMapping($importBatch, $request->validated('mapping'));

        return $this->success([
            'import_batch_id' => $batch->id,
            'status'          => $batch->status,
        ], 202);
    }

    public function status(Request $request, ImportBatch $importBatch): JsonResponse
    {
        abort_unless($importBatch->workspace->hasMember($request->user()), 403);

        $pct = $importBatch->total_rows > 0
            ? (int) round($importBatch->processed_rows / $importBatch->total_rows * 100)
            : 0;

        return $this->success([
            'id'             => $importBatch->id,
            'type'           => $importBatch->type,
            'status'         => $importBatch->status,
            'total_rows'     => $importBatch->total_rows,
            'processed_rows' => $importBatch->processed_rows,
            'failed_rows'    => $importBatch->failed_rows,
            'percent'        => $pct,
            'started_at'     => $importBatch->started_at?->toISOString(),
            'completed_at'   => $importBatch->completed_at?->toISOString(),
            'meta'           => $importBatch->meta,
        ]);
    }

    public function errors(Request $request, ImportBatch $importBatch): JsonResponse
    {
        abort_unless($importBatch->workspace->hasMember($request->user()), 403);

        $errors = $importBatch->errors()->paginate(50);

        return $this->paginated($errors);
    }

    public function index(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $query = ImportBatch::where('workspace_id', $workspaceId)
            ->orderByDesc('created_at');

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $this->paginated($query->paginate(20));
    }
}
