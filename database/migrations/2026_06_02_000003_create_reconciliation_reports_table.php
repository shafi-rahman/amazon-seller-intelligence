<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('report_type', 50);
            // missing_settlements | missing_credits | refund_impact
            // return_impact | gst_mismatch | summary
            $table->jsonb('report_data');
            $table->string('export_path', 1000)->nullable(); // MinIO path for PDF/CSV
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workspace_id', 'report_type']);
            $table->index('reconciliation_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_reports');
    }
};
