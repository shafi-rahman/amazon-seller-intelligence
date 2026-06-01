<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            // reconciliation_summary | missing_settlements | missing_credits
            // refund_impact | gst_mismatch | listing_analysis | keyword_gap | competitor_benchmark
            $table->string('title', 500);
            $table->jsonb('parameters')->default('{}');
            // { reconciliation_run_id, product_id, competitor_id, ... }
            $table->string('status', 20)->default('pending');
            // pending | generating | completed | failed
            $table->string('file_path', 1000)->nullable();
            $table->string('file_format', 10)->nullable(); // pdf | csv
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workspace_id', 'type', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
