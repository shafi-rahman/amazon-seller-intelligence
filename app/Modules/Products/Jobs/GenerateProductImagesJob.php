<?php

namespace App\Modules\Products\Jobs;

use App\Modules\AI\Services\ImageGenerationService;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductImage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates several AI product images (NVIDIA FLUX) in the background and saves
 * each as a ProductImage. Runs on the 'ai' queue; the frontend polls the product
 * to see new images appear.
 */
class GenerateProductImagesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;
    public int $tries   = 1;

    /**
     * @param string[] $prompts one prompt per image to generate
     */
    public function __construct(
        private readonly int $productId,
        private readonly array $prompts,
    ) {}

    public function handle(ImageGenerationService $images): void
    {
        $product = Product::find($this->productId);
        if (!$product) {
            return;
        }

        foreach (array_values($this->prompts) as $i => $prompt) {
            // Vary the seed per image so the set is diverse.
            $binary = $images->generate($prompt, seed: $i + 1);
            if ($binary === null) {
                Log::warning('Product image generation returned nothing', [
                    'product_id' => $this->productId, 'index' => $i,
                ]);
                continue;
            }

            $path = "products/{$product->workspace_id}/{$product->public_id}/ai-" . Str::uuid() . '.jpg';
            Storage::disk('s3')->put($path, $binary);

            $nextOrder   = $product->images()->max('display_order');
            $nextOrder   = $nextOrder === null ? 0 : $nextOrder + 1;
            $isFirstEver = $product->images()->count() === 0;

            $image = ProductImage::create([
                'product_id'    => $product->id,
                'workspace_id'  => $product->workspace_id,
                'storage_path'  => $path,
                'file_name'     => 'ai-generated.jpg',
                'display_order' => $nextOrder,
                'is_primary'    => $isFirstEver,
            ]);

            if ($isFirstEver) {
                $product->update(['image_path' => $path]);
            }
        }
    }
}
