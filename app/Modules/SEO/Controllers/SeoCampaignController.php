<?php

namespace App\Modules\SEO\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Products\Models\Product;
use App\Modules\SEO\Jobs\RunSeoAgentJob;
use App\Modules\SEO\Models\SeoCampaign;
use App\Modules\SEO\Models\SeoPost;
use App\Modules\SEO\Resources\SeoCampaignResource;
use App\Modules\AI\Services\VisionService;
use App\Modules\SEO\Services\SeoImageService;
use App\Modules\Workspace\Models\Workspace;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SeoCampaignController extends Controller
{
    use ApiResponse;

    // POST /workspaces/{workspaceId}/products/{productId}/seo/tag  (productId = UUID)
    public function tag(Request $request, int $workspaceId, string $productId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $product = Product::findByPublicId($productId, $workspace->id);

        // Prevent duplicate active campaigns
        $active = SeoCampaign::where('product_id', $product->id)
            ->whereIn('status', ['pending', 'generating', 'awaiting_approval'])
            ->first();

        if ($active) {
            return $this->success(new SeoCampaignResource($active->load('posts')), 200);
        }

        $campaign = SeoCampaign::create([
            'product_id'   => $product->id,
            'workspace_id' => $workspaceId,
            'user_id'      => $request->user()->id,
            'status'       => 'pending',
        ]);

        RunSeoAgentJob::dispatch($campaign->id)->onQueue('ai');

        return $this->created(new SeoCampaignResource($campaign));
    }

    // GET /workspaces/{workspaceId}/seo/campaigns
    public function index(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $query = SeoCampaign::where('workspace_id', $workspaceId)
            ->with(['product', 'posts'])
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $this->paginated($query->paginate(20));
    }

    // GET /workspaces/{workspaceId}/seo/campaigns/{uuid}
    public function show(Request $request, int $workspaceId, string $id): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $campaign = SeoCampaign::findByPublicId($id, $workspace->id);
        $campaign->load(['product', 'posts']);

        return $this->success(new SeoCampaignResource($campaign));
    }

    // POST /seo/posts/{postId}/approve
    public function approvePost(Request $request, int $postId): JsonResponse
    {
        $post = SeoPost::with('campaign')->findOrFail($postId);
        $workspace = Workspace::findOrFail($post->campaign->workspace_id);
        abort_unless($workspace->hasMember($request->user()), 403);

        $validated = $request->validate([
            'edited_caption' => ['nullable', 'string'],
        ]);

        $post->update([
            'status'         => 'approved',
            'edited_caption' => $validated['edited_caption'] ?? null,
        ]);

        // If all posts approved → mark campaign approved
        $campaign = $post->campaign;
        $allApproved = $campaign->posts()->where('status', '!=', 'rejected')->count() > 0
            && $campaign->posts()->whereNotIn('status', ['approved', 'rejected'])->count() === 0;

        if ($allApproved) {
            $campaign->update(['status' => 'approved']);
        }

        return $this->success([
            'post_id' => $post->id,
            'status'  => 'approved',
        ]);
    }

    // POST /seo/posts/{postId}/reject
    public function rejectPost(Request $request, int $postId): JsonResponse
    {
        $post = SeoPost::with('campaign')->findOrFail($postId);
        $workspace = Workspace::findOrFail($post->campaign->workspace_id);
        abort_unless($workspace->hasMember($request->user()), 403);

        $post->update(['status' => 'rejected']);

        return $this->success(['post_id' => $post->id, 'status' => 'rejected']);
    }

    // Authorize a post belongs to a workspace the user can access; returns the post.
    private function authorizePost(Request $request, int $postId): SeoPost
    {
        $post = SeoPost::with('campaign')->findOrFail($postId);
        $workspace = Workspace::findOrFail($post->campaign->workspace_id);
        abort_unless($workspace->hasMember($request->user()), 403);
        return $post;
    }

    // PUT /seo/posts/{postId}  — edit all content fields (title, caption, hashtags)
    public function updatePost(Request $request, int $postId): JsonResponse
    {
        $post = $this->authorizePost($request, $postId);

        $validated = $request->validate([
            'title'    => ['sometimes', 'nullable', 'string', 'max:300'],
            'caption'  => ['sometimes', 'nullable', 'string'],
            'hashtags' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        // The caption edit lives in edited_caption so the original AI copy is preserved.
        $update = [];
        if (array_key_exists('title', $validated))    $update['title']          = $validated['title'];
        if (array_key_exists('caption', $validated))  $update['edited_caption'] = $validated['caption'];
        if (array_key_exists('hashtags', $validated)) $update['hashtags']       = $validated['hashtags'];

        $post->update($update);

        return $this->success([
            'post_id'        => $post->id,
            'title'          => $post->title,
            'edited_caption' => $post->edited_caption,
            'hashtags'       => $post->hashtags,
            'image_url'      => $post->imageUrl(),
        ]);
    }

    // POST /seo/posts/{postId}/image/upload  — upload an image from the user's computer
    public function uploadPostImage(Request $request, int $postId): JsonResponse
    {
        $post = $this->authorizePost($request, $postId);

        $request->validate([
            'image' => ['required', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ]);

        // Remove a previously AI-generated/uploaded image for this post if it was
        // unique to this post (not the shared campaign image used by siblings).
        $wsId = $post->campaign->workspace_id;
        $file = $request->file('image');
        $path = "seo/{$wsId}/{$post->campaign->public_id}/upload-" . Str::uuid() . '.' . $file->getClientOriginalExtension();

        Storage::disk('s3')->put($path, $file->get());
        $post->applyNewImage($path);

        return $this->imageResponse($post);
    }

    // POST /seo/posts/{postId}/image/generate  — (re)generate the image via NVIDIA FLUX,
    // optionally guided by a user-supplied reference prompt.
    public function regeneratePostImage(Request $request, int $postId, SeoImageService $imageService): JsonResponse
    {
        $post = $this->authorizePost($request, $postId);

        $validated = $request->validate([
            'prompt' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        // User reference prompt takes priority; fall back to the post's stored prompt.
        $prompt = trim((string) ($validated['prompt'] ?? '')) ?: ($post->image_prompt ?? '');
        abort_if($prompt === '', 422, 'Provide a prompt describing the image you want.');

        $wsId = $post->campaign->workspace_id;
        $path = $imageService->generate($prompt, $wsId, $post->campaign->public_id);

        abort_if(!$path, 502, 'Image generation failed. Please try again in a moment.');

        // Persist the prompt the user gave so it shows next time, and the new image.
        $post->applyNewImage($path, $prompt);

        return $this->imageResponse($post);
    }

    // POST /seo/posts/{postId}/image/from-reference  — upload a reference image +
    // a description; a vision model describes it and FLUX regenerates guided by both.
    public function regenerateFromReference(Request $request, int $postId, VisionService $vision, SeoImageService $imageService): JsonResponse
    {
        $post = $this->authorizePost($request, $postId);

        $validated = $request->validate([
            'reference' => ['required', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
            'prompt'    => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $refDesc  = $vision->describe($request->file('reference')->get());
        abort_if(!$refDesc, 502, 'Could not analyse the reference image. Please try again.');

        $guidance = trim((string) ($validated['prompt'] ?? ''));
        $prompt   = "In the style of this reference: {$refDesc}."
            . ($guidance !== '' ? " {$guidance}." : '')
            . ' high detail, social media post image, no text, no watermark';

        $wsId = $post->campaign->workspace_id;
        $path = $imageService->generate($prompt, $wsId, $post->campaign->public_id);
        abort_if(!$path, 502, 'Image generation failed. Please try again in a moment.');

        $post->applyNewImage($path, $prompt);

        return $this->imageResponse($post);
    }

    // POST /seo/posts/{postId}/image/copy  — reuse the image from a sibling post
    // (same campaign), so the user doesn't have to re-upload/regenerate it.
    public function copyPostImage(Request $request, int $postId): JsonResponse
    {
        $post = $this->authorizePost($request, $postId);

        $validated = $request->validate([
            'source_post_id' => ['required', 'integer'],
        ]);

        $source = SeoPost::where('campaign_id', $post->campaign_id)
            ->findOrFail($validated['source_post_id']);

        abort_if(empty($source->image_path), 422, 'The selected post has no image to copy.');

        // Both posts simply point at the same stored object — no duplication needed.
        // applyNewImage remembers the post's prior image so it can be reverted.
        $post->applyNewImage($source->image_path, $source->image_prompt ?? $post->image_prompt);

        return $this->imageResponse($post);
    }

    // POST /seo/posts/{postId}/image/revert  — restore the image the post had
    // before the last change. Swaps current <-> previous so it can toggle back.
    public function revertPostImage(Request $request, int $postId): JsonResponse
    {
        $post = $this->authorizePost($request, $postId);

        abort_if(empty($post->previous_image_path), 422, 'No previous image to restore.');

        $current = $post->image_path;
        $post->image_path          = $post->previous_image_path;
        $post->previous_image_path = $current;
        $post->save();

        return $this->imageResponse($post);
    }

    // Shared response shape for all image-mutating endpoints.
    private function imageResponse(SeoPost $post): JsonResponse
    {
        return $this->success([
            'post_id'             => $post->id,
            'image_url'           => $post->imageUrl(),
            'image_path'          => $post->image_path,
            'image_prompt'        => $post->image_prompt,
            'previous_image_url'  => $post->previousImageUrl(),
            'previous_image_path' => $post->previous_image_path,
        ]);
    }

    // GET /seo/campaigns/{uuid}/product-data  (for OpenClaw skill)
    public function productData(Request $request, string $id): JsonResponse
    {
        $token = $request->query('token') ?? $request->header('X-Webhook-Token');
        abort_unless($token === config('app.seo_webhook_token'), 401);

        $campaign = SeoCampaign::findByPublicId($id);
        $campaign->load('product.keywords');
        $product  = $campaign->product;

        return response()->json([
            'campaign_id'   => $campaign->id,
            'status'        => $campaign->status,
            'product' => [
                'asin'         => $product->asin,
                'title'        => $product->title,
                'brand'        => $product->brand,
                'category'     => $product->category,
                'price'        => $product->price,
                'rating'       => $product->rating,
                'review_count' => $product->review_count,
                'bullet_1'     => $product->bullet_1,
                'bullet_2'     => $product->bullet_2,
                'description'  => $product->description,
                'listing_score'=> $product->listing_score,
                'top_keywords' => $product->keywords
                    ->sortByDesc('frequency')
                    ->take(15)
                    ->pluck('keyword'),
            ],
        ]);
    }

    // POST /seo/webhook/notify  (OpenClaw calls this to push notification status)
    public function webhookNotify(Request $request): JsonResponse
    {
        $token = $request->header('X-Webhook-Token');
        abort_unless($token === config('app.seo_webhook_token'), 401);

        $validated = $request->validate([
            'campaign_id' => ['required', 'integer'],
            'event'       => ['required', 'string'],  // 'notified' | 'posted'
            'platform'    => ['nullable', 'string'],
            'post_id'     => ['nullable', 'string'],
        ]);

        $campaign = SeoCampaign::findOrFail($validated['campaign_id']);

        if ($validated['event'] === 'posted' && $validated['platform']) {
            $post = $campaign->posts()
                ->where('platform', $validated['platform'])
                ->first();

            $post?->update([
                'status'          => 'published',
                'platform_post_id'=> $validated['post_id'] ?? null,
                'published_at'    => now(),
            ]);
        }

        return response()->json(['received' => true]);
    }
}
