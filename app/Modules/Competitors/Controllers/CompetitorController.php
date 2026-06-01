<?php

namespace App\Modules\Competitors\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitors\Jobs\CompetitorAnalysisJob;
use App\Modules\Competitors\Models\Competitor;
use App\Modules\Competitors\Models\KeywordGap;
use App\Modules\Competitors\Resources\CompetitorDetailResource;
use App\Modules\Competitors\Resources\CompetitorResource;
use App\Modules\Competitors\Services\CompetitorAnalysisService;
use App\Modules\Products\Models\Product;
use App\Modules\Workspace\Models\Workspace;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompetitorController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CompetitorAnalysisService $analysisService) {}

    // GET /workspaces/{id}/products/{productId}/competitors
    public function index(Request $request, int $workspaceId, int $productId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $product = Product::where('workspace_id', $workspaceId)->findOrFail($productId);

        $competitors = Competitor::where('product_id', $product->id)
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('last_analyzed_at')
            ->paginate((int) $request->query('per_page', 20));

        return $this->paginated($competitors);
    }

    // GET /workspaces/{id}/products/{productId}/competitors/{competitorId}
    public function show(Request $request, int $workspaceId, int $productId, int $competitorId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $competitor = Competitor::where('workspace_id', $workspaceId)
            ->where('product_id', $productId)
            ->with(['keywords' => fn($q) => $q->orderByDesc('frequency')->limit(30), 'benchmark'])
            ->findOrFail($competitorId);

        return $this->success(new CompetitorDetailResource($competitor));
    }

    // POST /workspaces/{id}/products/{productId}/competitors/{competitorId}/analyze
    public function analyze(Request $request, int $workspaceId, int $productId, int $competitorId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $competitor = Competitor::where('workspace_id', $workspaceId)
            ->where('product_id', $productId)
            ->findOrFail($competitorId);

        CompetitorAnalysisJob::dispatch($competitor->id)->onQueue('ai');

        return $this->success(['competitor_id' => $competitor->id, 'status' => 'queued'], 202);
    }

    // GET /workspaces/{id}/products/{productId}/keyword-gaps
    public function keywordGaps(Request $request, int $workspaceId, int $productId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $product = Product::where('workspace_id', $workspaceId)->findOrFail($productId);

        $query = KeywordGap::where('product_id', $product->id)
            ->orderByDesc('priority_score');

        if ($gapType = $request->query('gap_type')) {
            $query->where('gap_type', $gapType);
        }
        if ($competitorId = $request->query('competitor_id')) {
            $query->where('competitor_id', $competitorId);
        }

        $paginator = $query->paginate((int) $request->query('per_page', 50));

        return $this->paginated($paginator);
    }

    // GET /workspaces/{id}/products/{productId}/benchmark
    public function benchmark(Request $request, int $workspaceId, int $productId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $product = Product::where('workspace_id', $workspaceId)->findOrFail($productId);

        $competitors = Competitor::where('product_id', $product->id)
            ->where('workspace_id', $workspaceId)
            ->with('benchmark')
            ->get();

        $benchmarks = $competitors
            ->filter(fn($c) => $c->benchmark !== null)
            ->map(fn($c) => array_merge(
                $c->benchmark->benchmark_data,
                ['competitor_id' => $c->id, 'asin' => $c->asin]
            ))
            ->values();

        // Aggregate: consensus quick wins
        $aggregateGaps = $this->analysisService->aggregateGaps($product);

        return $this->success([
            'product' => [
                'id'            => $product->id,
                'asin'          => $product->asin,
                'listing_score' => $product->listing_score,
                'price'         => $product->price,
                'rating'        => $product->rating,
                'review_count'  => $product->review_count,
            ],
            'competitors'      => $benchmarks,
            'consensus_gaps'   => $aggregateGaps,
            'competitor_count' => $competitors->count(),
        ]);
    }
}
