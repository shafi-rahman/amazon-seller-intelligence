<?php

namespace App\Modules\Competitors\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competitors\Jobs\CompetitorAnalysisJob;
use App\Modules\Competitors\Models\Competitor;
use App\Modules\Competitors\Models\KeywordGap;
use App\Modules\Competitors\Resources\CompetitorDetailResource;
use App\Modules\Competitors\Resources\CompetitorResource;
use App\Modules\Competitors\Services\CompetitorAnalysisService;
use App\Modules\Imports\Services\ImportService;
use App\Modules\Products\Models\Product;
use App\Modules\Workspace\Models\Workspace;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use League\Csv\Reader;

class CompetitorController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CompetitorAnalysisService $analysisService) {}

    /** Shared workspace + product ownership guard. Product resolves by UUID. */
    private function guard(Request $request, int $workspaceId, Product $product): void
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);
        abort_unless($product->workspace_id === $workspaceId, 404);
    }

    // GET /workspaces/{id}/products/{product}/competitors
    public function index(Request $request, int $workspaceId, Product $product): JsonResponse
    {
        $this->guard($request, $workspaceId, $product);

        $competitors = Competitor::where('product_id', $product->id)
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('last_analyzed_at')
            ->paginate($this->perPage($request, 50));

        return $this->paginatedThrough($competitors, CompetitorResource::class);
    }

    // GET /workspaces/{id}/products/{product}/competitors/{competitorId}
    public function show(Request $request, int $workspaceId, Product $product, int $competitorId): JsonResponse
    {
        $this->guard($request, $workspaceId, $product);

        $competitor = Competitor::where('workspace_id', $workspaceId)
            ->where('product_id', $product->id)
            ->with(['keywords' => fn($q) => $q->orderByDesc('frequency')->limit(30), 'benchmark'])
            ->findOrFail($competitorId);

        return $this->success(new CompetitorDetailResource($competitor));
    }

    // POST /workspaces/{id}/products/{product}/competitors/html  — paste competitor HTML
    public function addHtml(Request $request, int $workspaceId, Product $product, ImportService $importService): JsonResponse
    {
        $this->guard($request, $workspaceId, $product);

        $validated = $request->validate([
            'html_content' => ['required', 'string', 'min:100'],
            'asin'         => ['nullable', 'string', 'max:50'],
        ]);

        $workspace = Workspace::findOrFail($workspaceId);
        $batch = $importService->uploadHtml(
            $workspace,
            $request->user()->id,
            $validated['html_content'],
            $product->id,
            $validated['asin'] ?? null,
        );

        return $this->success([
            'import_batch_id' => $batch->public_id,
            'status'          => $batch->status,
        ], 202);
    }

    // POST /workspaces/{id}/products/{product}/competitors/csv  — upload competitor CSV
    public function addCsv(Request $request, int $workspaceId, Product $product): JsonResponse
    {
        $this->guard($request, $workspaceId, $product);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
        ]);

        $reader = Reader::createFromPath($request->file('file')->getRealPath());
        $reader->setHeaderOffset(0);

        $created = 0; $skipped = 0;
        foreach ($reader->getRecords() as $row) {
            $get  = fn(array $names) => $this->pick($row, $names);
            $asin = $get(['asin', 'competitor asin', 'asin code']);
            if (empty($asin)) { $skipped++; continue; }

            $competitor = Competitor::updateOrCreate(
                ['workspace_id' => $workspaceId, 'product_id' => $product->id, 'asin' => $asin],
                [
                    'title'        => $get(['title', 'product title', 'name', 'product name']),
                    'brand'        => $get(['brand', 'manufacturer']),
                    'category'     => $get(['category']),
                    'price'        => $this->num($get(['price', 'selling price', 'mrp'])),
                    'currency'     => $get(['currency']) ?: 'INR',
                    'rating'       => $this->num($get(['rating', 'stars', 'star rating'])),
                    'review_count' => (int) preg_replace('/[^\d]/', '', (string) $get(['review_count', 'reviews', 'ratings count', 'number of reviews'])),
                    'bullet_1'     => $get(['bullet_1', 'bullet 1', 'bullet1', 'feature 1']),
                    'bullet_2'     => $get(['bullet_2', 'bullet 2', 'bullet2', 'feature 2']),
                    'bullet_3'     => $get(['bullet_3', 'bullet 3', 'bullet3', 'feature 3']),
                    'bullet_4'     => $get(['bullet_4', 'bullet 4', 'bullet4', 'feature 4']),
                    'bullet_5'     => $get(['bullet_5', 'bullet 5', 'bullet5', 'feature 5']),
                    'description'  => $get(['description', 'about', 'product description']),
                    'source_type'  => 'csv',
                ],
            );
            CompetitorAnalysisJob::dispatch($competitor->id)->onQueue('ai');
            $created++;
        }

        return $this->success(['imported' => $created, 'skipped' => $skipped], 202);
    }

    // POST /workspaces/{id}/products/{product}/competitors/{competitorId}/analyze
    public function analyze(Request $request, int $workspaceId, Product $product, int $competitorId): JsonResponse
    {
        $this->guard($request, $workspaceId, $product);

        $competitor = Competitor::where('workspace_id', $workspaceId)
            ->where('product_id', $product->id)
            ->findOrFail($competitorId);

        CompetitorAnalysisJob::dispatch($competitor->id)->onQueue('ai');

        return $this->success(['competitor_id' => $competitor->id, 'status' => 'queued'], 202);
    }

    // DELETE /workspaces/{id}/products/{product}/competitors/{competitorId}
    public function destroy(Request $request, int $workspaceId, Product $product, int $competitorId): JsonResponse
    {
        $this->guard($request, $workspaceId, $product);

        $competitor = Competitor::where('workspace_id', $workspaceId)
            ->where('product_id', $product->id)
            ->findOrFail($competitorId);

        // Clean up polymorphic RAG embeddings so deleted competitors don't keep
        // feeding stale chunks into the copilot (no FK cascade on embeddable_*).
        \App\Modules\AI\Models\Embedding::where('embeddable_type', Competitor::class)
            ->where('embeddable_id', $competitor->id)
            ->delete();

        $competitor->delete();

        return $this->noContent();
    }

    // GET /workspaces/{id}/products/{product}/keyword-gaps
    public function keywordGaps(Request $request, int $workspaceId, Product $product): JsonResponse
    {
        $this->guard($request, $workspaceId, $product);

        $query = KeywordGap::where('product_id', $product->id)->orderByDesc('priority_score');
        if ($gapType = $request->query('gap_type')) {
            $query->where('gap_type', $gapType);
        }
        if ($competitorId = $request->query('competitor_id')) {
            $query->where('competitor_id', $competitorId);
        }

        return $this->paginated($query->paginate($this->perPage($request, 50)));
    }

    // GET /workspaces/{id}/products/{product}/benchmark
    public function benchmark(Request $request, int $workspaceId, Product $product): JsonResponse
    {
        $this->guard($request, $workspaceId, $product);

        $competitors = Competitor::where('product_id', $product->id)
            ->where('workspace_id', $workspaceId)
            ->with('benchmark')
            ->get();

        $benchmarks = $competitors
            ->filter(fn($c) => $c->benchmark !== null)
            ->map(fn($c) => array_merge(
                $c->benchmark->benchmark_data,
                ['competitor_id' => $c->id, 'asin' => $c->asin, 'title' => $c->title]
            ))
            ->values();

        return $this->success([
            'product' => [
                'id'            => $product->public_id,
                'asin'          => $product->asin,
                'listing_score' => $product->listing_score,
                'price'         => $product->price,
                'rating'        => $product->rating,
                'review_count'  => $product->review_count,
            ],
            'competitors'      => $benchmarks,
            'consensus_gaps'   => $this->analysisService->aggregateGaps($product),
            'competitor_count' => $competitors->count(),
        ]);
    }

    /** Pick the first non-empty value from a row by trying several header names (case/space-insensitive). */
    private function pick(array $row, array $names): ?string
    {
        $norm = [];
        foreach ($row as $k => $v) {
            $norm[strtolower(trim(preg_replace('/[\s_]+/', ' ', (string) $k)))] = $v;
        }
        foreach ($names as $n) {
            $key = strtolower(trim(preg_replace('/[\s_]+/', ' ', $n)));
            if (isset($norm[$key]) && trim((string) $norm[$key]) !== '') {
                return trim((string) $norm[$key]);
            }
        }
        return null;
    }

    private function num(?string $v): ?float
    {
        if ($v === null || $v === '') return null;
        $clean = preg_replace('/[^\d.]/', '', $v);
        return $clean === '' ? null : (float) $clean;
    }
}
