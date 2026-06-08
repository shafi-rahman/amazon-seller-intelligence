<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')
                ->constrained('seo_campaigns')
                ->cascadeOnDelete();
            $table->string('platform', 30);
            // instagram | facebook | linkedin | google_business
            $table->text('caption')->nullable();
            $table->text('edited_caption')->nullable(); // user-edited version
            $table->text('hashtags')->nullable();
            $table->text('image_prompt')->nullable();   // sent to image AI
            $table->string('image_path', 500)->nullable(); // MinIO path (Phase 2)
            $table->string('status', 20)->default('draft');
            // draft | approved | rejected | published | failed
            $table->string('platform_post_id', 200)->nullable(); // returned by social API
            $table->timestamp('published_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['campaign_id', 'platform']);
            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_posts');
    }
};
