<?php

namespace App\Modules\Reconciliation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reconciliation\Jobs\ExportReportJob;
use App\Modules\Reconciliation\Jobs\ReconciliationJob;
use App\Modules\Reconciliation\Models\ReconciliationReport;
use App\Modules\Reconciliation\Models\ReconciliationRun;
use App\Modules\Reconciliation\Resources\ReconciliationRunResource;
use App\Modules\Reconciliation\Services\ReportExporter;
use App\Modules\Workspace\Models\Workspace;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReconciliationController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ReportExporter $exporter) {}

    // POST /workspaces/{id}/reconciliation/run
    public function run(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $validated = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $run = ReconciliationRun::create([
            'workspace_id' => $workspaceId,
            'user_id'      => $request->user()->id,
            'period_start' => $validated['period_start'],
            'period_end'   => $validated['period_end'],
            'status'       => 'pending',
        ]);

        ReconciliationJob::dispatch($run->id)->onQueue('reconciliation');

        return $this->success(['reconciliation_run_id' => $run->id, 'status' => 'pending'], 202);
    }

    // GET /workspaces/{id}/reconciliation
    public function index(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $runs = ReconciliationRun::where('workspace_id', $workspaceId)
            ->orderByDesc('created_at')
            ->paginate(10);

        return $this->paginated($runs);
    }

    // GET /workspaces/{id}/reconciliation/{runId}
    public function show(Request $request, int $workspaceId, int $runId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $run = ReconciliationRun::where('workspace_id', $workspaceId)
            ->with('reports')
            ->findOrFail($runId);

        return $this->success(new ReconciliationRunResource($run));
    }

    // GET /workspaces/{id}/reconciliation/{runId}/status
    public function status(Request $request, int $workspaceId, int $runId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $run = ReconciliationRun::where('workspace_id', $workspaceId)->findOrFail($runId);

        return $this->success([
            'id'           => $run->id,
            'status'       => $run->status,
            'summary'      => $run->summary,
            'started_at'   => $run->started_at?->toISOString(),
            'completed_at' => $run->completed_at?->toISOString(),
        ]);
    }

    // GET /workspaces/{id}/reconciliation/{runId}/reports/{type}
    public function report(Request $request, int $workspaceId, int $runId, string $type): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $report = ReconciliationReport::where('reconciliation_run_id', $runId)
            ->where('workspace_id', $workspaceId)
            ->where('report_type', $type)
            ->firstOrFail();

        // Support pagination on rows array
        $data = $report->report_data ?? [];
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;

        if (isset($data['rows'])) {
            $allRows       = $data['rows'];
            $total         = count($allRows);
            $data['rows']  = array_slice($allRows, ($page - 1) * $perPage, $perPage);
            $data['meta']  = ['page' => $page, 'per_page' => $perPage, 'total' => $total];
        }

        return $this->success([
            'id'          => $report->id,
            'report_type' => $report->report_type,
            'data'        => $data,
            'export_path' => $report->export_path,
            'created_at'  => $report->created_at?->toISOString(),
        ]);
    }

    // POST /workspaces/{id}/reconciliation/{runId}/reports/{type}/export
    public function export(Request $request, int $workspaceId, int $runId, string $type): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $validated = $request->validate([
            'format' => ['required', Rule::in(['csv', 'pdf'])],
        ]);

        $report = ReconciliationReport::where('reconciliation_run_id', $runId)
            ->where('workspace_id', $workspaceId)
            ->where('report_type', $type)
            ->firstOrFail();

        ExportReportJob::dispatch($report->id, $validated['format'])->onQueue('reports');

        return $this->success(['report_id' => $report->id, 'status' => 'generating'], 202);
    }

    // GET /workspaces/{id}/reconciliation/reports/{reportId}/download
    public function download(Request $request, int $workspaceId, int $reportId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $report = ReconciliationReport::where('workspace_id', $workspaceId)
            ->findOrFail($reportId);

        abort_if(empty($report->export_path), 404, 'Export not generated yet.');

        $url = $this->exporter->presignedUrl($report->export_path);

        return $this->success(['url' => $url, 'expires_in' => 3600]);
    }
}
