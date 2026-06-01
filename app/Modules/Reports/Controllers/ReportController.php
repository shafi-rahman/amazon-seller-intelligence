<?php

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Models\Report;
use App\Modules\Reports\Resources\ReportResource;
use App\Modules\Reports\Services\ReportGeneratorService;
use App\Modules\Workspace\Models\Workspace;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ReportGeneratorService $generator) {}

    // GET /workspaces/{id}/reports
    public function index(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $query = Report::where('workspace_id', $workspaceId)
            ->orderByDesc('created_at');

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $this->paginated($query->paginate(20));
    }

    // POST /workspaces/{id}/reports
    public function store(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $validated = $request->validate([
            'type'       => ['required', Rule::in(array_keys(ReportGeneratorService::REPORT_TYPES))],
            'format'     => ['required', Rule::in(['pdf', 'csv'])],
            'parameters' => ['nullable', 'array'],
        ]);

        $report = $this->generator->request(
            $workspaceId,
            $request->user()->id,
            $validated['type'],
            $validated['format'],
            $validated['parameters'] ?? [],
        );

        return $this->created(new ReportResource($report));
    }

    // GET /workspaces/{id}/reports/{reportId}
    public function show(Request $request, int $workspaceId, int $reportId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $report = Report::where('workspace_id', $workspaceId)->findOrFail($reportId);

        return $this->success(new ReportResource($report));
    }

    // GET /workspaces/{id}/reports/{reportId}/download
    public function download(Request $request, int $workspaceId, int $reportId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $report = Report::where('workspace_id', $workspaceId)->findOrFail($reportId);

        $url = $this->generator->presignedUrl($report);

        return $this->success([
            'url'       => $url,
            'format'    => $report->file_format,
            'expires_in'=> 3600,
        ]);
    }

    // GET /workspaces/{id}/reports/types
    public function types(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        return $this->success(ReportGeneratorService::REPORT_TYPES);
    }
}
