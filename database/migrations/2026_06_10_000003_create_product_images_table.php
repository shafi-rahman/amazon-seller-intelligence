<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('storage_path', 1000);
            $table->string('file_name', 500)->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'display_order']);
            $table->unique('public_id');
        });

        // Migrate existing single images from products.image_path → product_images
        \Illuminate\Support\Facades\DB::statement("
            INSERT INTO product_images (product_id, workspace_id, storage_path, file_name, display_order, is_primary, created_at)
            SELECT id, workspace_id, image_path, 'product.jpg', 0, TRUE, NOW()
            FROM products
            WHERE image_path IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
