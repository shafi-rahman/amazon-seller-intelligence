<?php

namespace App\Modules\SEO\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Products\Models\Product;
use App\Modules\SEO\Jobs\RunSeoAgentJob;
use App\Modules\SEO\Models\SeoCampaign;
use App\Modules\SEO\Models\SeoPost;
use App\Modules\SEO\Resources\SeoCampaignResource;
use App\Modules\Workspace\Models\Workspace;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoCampaignController extends Controller
{
    use ApiResponse;

    // POST /workspaces/{workspaceId}/products/{productId}/seo/tag
    public function tag(Request $request, int $workspaceId, int $productId): JsonResponse
    {
        $workspace = Workspace::findOrFail($workspaceId);
        abort_unless($workspace->hasMember($request->user()), 403);

        $product = Product::where('workspace_id', $workspaceId)->findOrFail($productId);

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
