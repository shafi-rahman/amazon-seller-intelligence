<?php

namespace App\Modules\Products\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Services\VisionService;
use App\Modules\Products\Jobs\AnalyzeProductJob;
use App\Modules\Products\Jobs\GenerateProductImagesJob;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductImage;
use App\Modules\Products\Resources\ProductDetailResource;
use App\Modules\Products\Resources\ProductResource;
use App\Modules\Products\Services\ProductIntelligenceService;
use App\Modules\Workspace\Models\Workspace;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        // Transform through ProductResource so the list exposes the same shape as
        // detail (UUID id, score_tier, …) instead of leaking raw model columns.
        return $this->paginatedThrough($query->paginate($this->perPage($request, 20)), ProductResource::class);
    }

    // GET /workspaces/{id}/products/{product}
    public function show(Request $request, int $workspaceId, Product $product): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);
        abort_unless($product->workspace_id === $workspaceId, 404);

        $product->load([
            'keywords' => fn($q) => $q->orderByDesc('frequency')->limit(50),
            'images',
        ]);

        return $this->success(new ProductDetailResource($product));
    }

    // POST /workspaces/{id}/products  — manually create a product
    public function store(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $data = $this->validateProduct($request, $workspaceId);
        $data['workspace_id'] = $workspaceId;
        $data['source_type']  = 'manual';
        $data['currency']     = $data['currency'] ?? 'INR';

        $product = Product::create($data);

        return $this->success(new ProductDetailResource($product->load(['keywords', 'images'])), 201);
    }

    // PUT /workspaces/{id}/products/{product}  — edit product details
    public function update(Request $request, int $workspaceId, Product $product): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);
        abort_unless($product->workspace_id === $workspaceId, 404);

        $data = $this->validateProduct($request, $workspaceId, $product->id);
        $product->update($data);

        return $this->success(new ProductDetailResource($product->load(['keywords', 'images'])));
    }

    /** Shared validation for create/update. ASIN unique per workspace. */
    private function validateProduct(Request $request, int $workspaceId, ?int $ignoreId = null): array
    {
        $asinUnique = Rule::unique('products', 'asin')->where(fn($q) => $q->where('workspace_id', $workspaceId));
        if ($ignoreId) {
            $asinUnique->ignore($ignoreId);
        }

        return $request->validate([
            'asin'         => ['required', 'string', 'max:50', $asinUnique],
            'sku'          => ['nullable', 'string', 'max:100'],
            'title'        => ['required', 'string', 'max:500'],
            'brand'        => ['nullable', 'string', 'max:200'],
            'category'     => ['nullable', 'string', 'max:200'],
            'sub_category' => ['nullable', 'string', 'max:200'],
            'bullet_1'     => ['nullable', 'string'],
            'bullet_2'     => ['nullable', 'string'],
            'bullet_3'     => ['nullable', 'string'],
            'bullet_4'     => ['nullable', 'string'],
            'bullet_5'     => ['nullable', 'string'],
            'description'  => ['nullable', 'string'],
            'price'        => ['nullable', 'numeric', 'min:0'],
            'currency'     => ['nullable', 'string', 'max:5'],
            'rating'       => ['nullable', 'numeric', 'min:0', 'max:5'],
            'review_count' => ['nullable', 'integer', 'min:0'],
        ]);
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

        return $this->success(new ProductDetailResource($updated->load(['keywords', 'images'])));
    }

    // POST /workspaces/{id}/products/{product}/images  (multiple files)
    public function uploadImages(Request $request, int $workspaceId, Product $product): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);
        abort_unless($product->workspace_id === $workspaceId, 404);

        $request->validate([
            'images'          => ['required', 'array', 'min:1', 'max:20'],
            'images.*'        => ['image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $nextOrder  = $product->images()->max('display_order') ?? -1;
        $isFirstEver = $product->images()->count() === 0;
        $uploaded   = [];

        foreach ($request->file('images') as $file) {
            $nextOrder++;
            $uuid      = (string) Str::uuid();
            $ext       = $file->getClientOriginalExtension();
            $path      = "products/{$workspaceId}/{$product->public_id}/{$uuid}.{$ext}";

            Storage::disk('s3')->put($path, $file->get());

            $isPrimary = ($isFirstEver && $nextOrder === 0);

            $image = ProductImage::create([
                'product_id'    => $product->id,
                'workspace_id'  => $workspaceId,
                'storage_path'  => $path,
                'file_name'     => $file->getClientOriginalName(),
                'display_order' => $nextOrder,
                'is_primary'    => $isPrimary,
            ]);

            // Keep products.image_path synced with primary image
            if ($isPrimary) {
                $product->update(['image_path' => $path]);
            }

            $uploaded[] = [
                'id'            => $image->public_id,
                'url'           => $image->url(),
                'file_name'     => $image->file_name,
                'display_order' => $image->display_order,
                'is_primary'    => $image->is_primary,
            ];
        }

        return $this->success(['uploaded' => $uploaded, 'count' => count($uploaded)], 201);
    }

    // POST /workspaces/{id}/products/{product}/images/generate
    // Generate several AI images for the product (NVIDIA FLUX), derived from the
    // product title/description, optionally guided by a user prompt. Runs async.
    //
    // Accepts multipart so it can ALSO take a reference image:
    //   - reference (uploaded file)       → "upload + describe" flow
    //   - reference_image_id (gallery UUID) → "make a variation of this image" flow
    // When a reference is given, a vision model describes it and that description
    // guides generation (reference-guided regenerate).
    public function generateImages(Request $request, int $workspaceId, Product $product, VisionService $vision): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);
        abort_unless($product->workspace_id === $workspaceId, 404);

        $validated = $request->validate([
            'count'              => ['sometimes', 'integer', 'min:1', 'max:5'],
            'prompt'             => ['sometimes', 'nullable', 'string', 'max:1000'],
            'reference'          => ['sometimes', 'nullable', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
            'reference_image_id' => ['sometimes', 'nullable', 'string'],
        ]);

        $count    = $validated['count'] ?? 4;
        $guidance = trim((string) ($validated['prompt'] ?? ''));

        // Resolve an optional reference image (uploaded file or existing gallery image).
        $refBytes = null;
        if ($request->hasFile('reference')) {
            $refBytes = $request->file('reference')->get();
        } elseif (!empty($validated['reference_image_id'])) {
            $refImg = $product->images()->where('public_id', $validated['reference_image_id'])->first();
            if ($refImg && $refImg->storage_path) {
                $refBytes = Storage::disk('s3')->get($refImg->storage_path);
            }
        }

        $refDesc = $refBytes ? $vision->describe($refBytes) : null;

        $prompts = $this->buildImagePrompts($product, $count, $guidance, $refDesc);
        GenerateProductImagesJob::dispatch($product->id, $prompts)->onQueue('ai');

        return $this->success([
            'status'        => 'generating',
            'count'         => $count,
            'existing'      => $product->images()->count(),
            'used_reference'=> $refDesc !== null,
        ], 202);
    }

    /**
     * Build $count distinct FLUX prompts for a product. When $refDesc is given
     * (a vision description of a reference image), generation is guided by it.
     *
     * @return string[]
     */
    private function buildImagePrompts(Product $product, int $count, string $guidance, ?string $refDesc): array
    {
        $desc    = trim(strip_tags((string) $product->description));
        $subject = trim(($product->brand ? $product->brand . ' ' : '') . ($product->title ?? $product->asin));

        if ($refDesc) {
            // Reference-guided: anchor on what the reference image looks like.
            $base = "Product photo of {$subject}, in the style of this reference: {$refDesc}."
                . ($guidance !== '' ? " {$guidance}." : '');
        } else {
            $base = $guidance !== ''
                ? "{$guidance}. Product: {$subject}."
                : "Professional product photography of {$subject}."
                    . ($desc !== '' ? ' ' . \Illuminate\Support\Str::limit($desc, 240, '') : '');
        }

        // Distinct angles/styles give a varied set the seller can choose from.
        $styles = [
            'front-facing studio shot on a clean white background, soft even lighting',
            'lifestyle shot in a real-world setting, natural light, in use',
            'close-up detail shot highlighting texture and material quality',
            'three-quarter angle on a wooden surface with soft shadows',
            'top-down flat lay on a minimal neutral background',
        ];
        $suffix  = 'high detail, commercial e-commerce product shot, sharp focus, no text, no watermark, no logo';

        $prompts = [];
        for ($i = 0; $i < $count; $i++) {
            $prompts[] = "{$base} {$styles[$i % count($styles)]}, {$suffix}";
        }
        return $prompts;
    }

    // DELETE /workspaces/{id}/products/{product}/images/{imagePublicId}
    public function deleteProductImage(Request $request, int $workspaceId, Product $product, string $imageId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);
        abort_unless($product->workspace_id === $workspaceId, 404);

        $image = ProductImage::where('product_id', $product->id)
            ->where('public_id', $imageId)
            ->firstOrFail();

        $wasPrimary = $image->is_primary;
        $image->deleteFromStorage();
        $image->delete();

        // Reorder remaining images
        $product->images()->orderBy('display_order')->each(function ($img, $i) {
            $img->update(['display_order' => $i]);
        });

        // If primary was deleted, promote next image
        if ($wasPrimary) {
            $next = $product->images()->orderBy('display_order')->first();
            if ($next) {
                $next->update(['is_primary' => true]);
                $product->update(['image_path' => $next->storage_path]);
            } else {
                $product->update(['image_path' => null]);
            }
        }

        return $this->noContent();
    }

    // PUT /workspaces/{id}/products/{product}/images/{imagePublicId}/primary
    public function setPrimaryImage(Request $request, int $workspaceId, Product $product, string $imageId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);
        abort_unless($product->workspace_id === $workspaceId, 404);

        $image = ProductImage::where('product_id', $product->id)
            ->where('public_id', $imageId)
            ->firstOrFail();

        // Unset all primaries, then set this one
        ProductImage::where('product_id', $product->id)->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);
        $product->update(['image_path' => $image->storage_path]);

        return $this->success(['id' => $imageId, 'is_primary' => true]);
    }

    // PUT /workspaces/{id}/products/{product}/images/reorder
    public function reorderImages(Request $request, int $workspaceId, Product $product): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);
        abort_unless($product->workspace_id === $workspaceId, 404);

        $validated = $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['string'], // array of public_ids in new order
        ]);

        foreach ($validated['order'] as $index => $publicId) {
            ProductImage::where('product_id', $product->id)
                ->where('public_id', $publicId)
                ->update(['display_order' => $index]);
        }

        return $this->success(['reordered' => true]);
    }

    // GET /workspaces/{id}/products/{product}/images
    public function listImages(Request $request, int $workspaceId, Product $product): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);
        abort_unless($product->workspace_id === $workspaceId, 404);

        $images = $product->images()->get()->map(fn($img) => [
            'id'            => $img->public_id,
            'url'           => $img->url(),
            'file_name'     => $img->file_name,
            'display_order' => $img->display_order,
            'is_primary'    => $img->is_primary,
        ]);

        return $this->success($images);
    }
}
