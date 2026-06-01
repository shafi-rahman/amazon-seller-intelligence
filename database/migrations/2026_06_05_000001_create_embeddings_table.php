<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('embeddable_type', 150);
            $table->unsignedBigInteger('embeddable_id');
            $table->unsignedSmallInteger('chunk_index')->default(0);
            $table->text('chunk_text');
            // vector(1536) column added below via raw SQL
            $table->string('model', 100)->default('text-embedding-3-small');
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['embeddable_type', 'embeddable_id', 'chunk_index'], 'embeddings_doc_chunk_unique');
            $table->index('workspace_id');
            $table->index(['embeddable_type', 'embeddable_id']);
        });

        // pgvector column — cannot be defined via Blueprint
        DB::statement('ALTER TABLE embeddings ADD COLUMN embedding vector(1536)');

        // HNSW index for approximate nearest-neighbor search (cosine distance)
        DB::statement('
            CREATE INDEX embeddings_hnsw
            ON embeddings USING hnsw (embedding vector_cosine_ops)
            WITH (m = 16, ef_construction = 64)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('embeddings');
    }
};
