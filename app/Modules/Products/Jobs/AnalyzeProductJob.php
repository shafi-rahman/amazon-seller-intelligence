<?php

namespace App\Modules\Products\Jobs;

use App\Modules\Products\Models\Product;
use App\Modules\Products\Services\ProductIntelligenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyzeProductJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;
    public int $tries   = 2;

    public function __construct(private readonly int $productId) {}

    public function handle(ProductIntelligenceService $service): void
    {
        $product = Product::find($this->productId);

        if (!$product) {
            return;
        }

        $service->analyze($product);
    }
}
