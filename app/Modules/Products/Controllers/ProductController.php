<?php

namespace App\Modules\Products\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Products\Jobs\AnalyzeProductJob;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Resources\ProductDetailResource;
use App\Modules\Products\Resources\ProductResource;
use App\Modules\Products\Services\ProductIntelligenceService;
use App\Modules\Workspace\Models\Workspace;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ProductIntelligenceService $intelligence) {}

    // GET /workspaces/{id}/products
    public function index(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $query = Product::where('workspace_id', $workspaceId)
            ->orderByDesc('listing_score')
            ->orderByDesc('updated_at');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('asin', 'ILIKE', "%{$search}%")
                  ->orWhere('title', 'ILIKE', "%{$search}%")
                  ->orWhere('sku', 'ILIKE', "%{$search}%");
            });
        }
        if ($brand = $request->query('brand')) {
            $query->where('brand', $brand);
        }
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }
        if ($minScore = $request->query('min_score')) {
            $query->where('listing_score', '>=', (int) $minScore);
        }
        if ($maxScore = $request->query('max_score')) {
            $query->where('listing_score', '<=', (int) $maxScore);
        }
        if ($request->query('needs_analysis')) {
            $query->whereNull('listing_score')->orWhere('last_analyzed_at', '<', now()->subDays(7));
        }

        return $this->paginated($query->paginate((int) $request->query('per_page', 20)));
    }

    // GET /workspaces/{id}/products/{product}
    public function show(Request $request, int $workspaceId, Product $product): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);
        abort_unless($product->workspace_id === $workspaceId, 404);

        $product->load(['keywords' => fn($q) => $q->orderByDesc('frequency')->limit(50)]);

        return $this->success(new ProductDetailResource($product));
    }

    // POST /workspaces/{id}/products/{product}/analyze
    public function analyze(Request $request, int $workspaceId, Product $product): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);
        abort_unless($product->workspace_id === $workspaceId, 404);

        AnalyzeProductJob::dispatch($product->id)->onQueue('ai');

        return $this->success(['product_id' => $product->id, 'status' => 'queued'], 202);
    }

    // POST /workspaces/{id}/products/{product}/rewrite
    public function rewrite(Request $request, int $workspaceId, Product $product): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);
        abort_unless($product->workspace_id === $workspaceId, 404);

        if (!$this->intelligence->isAiConfigured()) {
            return $this->error(
                'AI rewrite requires ANTHROPIC_API_KEY to be configured. Add your key to .env to enable this feature.',
                422
            );
        }

        $rewrite = $this->intelligence->generateRewrite($product);

        if ($rewrite === null) {
            return $this->error('AI rewrite generation failed. Please try again.', 500);
        }

        return $this->success(['rewrite' => $rewrite]);
    }

    // POST /workspaces/{id}/products/{product}/apply-rewrite
    public function applyRewrite(Request $request, int $workspaceId, Product $product): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);
        abort_unless($product->workspace_id === $workspaceId, 404);

        $validated = $request->validate([
            'title'       => ['sometimes', 'string', 'max:500'],
            'bullet_1'    => ['sometimes', 'nullable', 'string'],
            'bullet_2'    => ['sometimes', 'nullable', 'string'],
            'bullet_3'    => ['sometimes', 'nullable', 'string'],
            'bullet_4'    => ['sometimes', 'nullable', 'string'],
            'bullet_5'    => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        $updated = $this->intelligence->applyRewrite($product, $validated);

        return $this->success(new ProductDetailResource($updated->load('keywords')));
    }

    // POST /workspaces/{id}/products/{product}/image
    public function uploadImage(Request $request, int $workspaceId, Product $product): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);
        abort_unless($product->workspace_id === $workspaceId, 404);

        $request->validate([
            'image' => ['required', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ]);

        // Delete old image if exists
        if ($product->image_path) {
            Storage::disk('s3')->delete($product->image_path);
        }

        $file      = $request->file('image');
        $ext       = $file->getClientOriginalExtension();
        $path      = "asip-uploads/products/{$workspaceId}/{$product->public_id}/product.{$ext}";

        Storage::disk('s3')->put($path, $file->get(), 'public');

        $product->update(['image_path' => $path]);

        return $this->success([
            'image_path' => $path,
            'image_url'  => Storage::disk('s3')->temporaryUrl($path, now()->addHours(24)),
        ]);
    }

    // DELETE /workspaces/{id}/products/{product}/image
    public function deleteImage(Request $request, int $workspaceId, Product $product): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);
        abort_unless($product->workspace_id === $workspaceId, 404);

        if ($product->image_path) {
            Storage::disk('s3')->delete($product->image_path);
            $product->update(['image_path' => null]);
        }

        return $this->noContent();
    }

    // GET /workspaces/{id}/products/{product}/image-url
    // Returns a fresh presigned URL for the product image
    public function imageUrl(Request $request, int $workspaceId, Product $product): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);
        abort_unless($product->workspace_id === $workspaceId, 404);

        if (!$product->image_path) {
            return $this->success(['image_url' => null]);
        }

        return $this->success([
            'image_url' => Storage::disk('s3')->temporaryUrl($product->image_path, now()->addHours(24)),
        ]);
    }
}
