<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Services\FinanceDashboardService;
use App\Modules\Workspace\Models\Workspace;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly FinanceDashboardService $dashboard) {}

    public function index(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $from = $request->query('date_from', now()->startOfMonth()->toDateString());
        $to   = $request->query('date_to',   now()->toDateString());

        return $this->success($this->dashboard->summary($workspaceId, $from, $to));
    }
}
