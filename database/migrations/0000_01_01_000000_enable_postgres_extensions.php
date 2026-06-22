<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ensure required PostgreSQL extensions exist before any migration that depends
 * on them (pgvector `vector` columns/HNSW indexes; `pg_trgm` GIN indexes).
 *
 * Previously these were created only by docker/postgres/init.sql, which runs ONCE
 * on first volume init and only for the main DB — so a fresh test DB, a managed
 * Postgres/RDS, or a restored volume would fail `migrate` with
 * "type vector does not exist" / "gin_trgm_ops does not exist".
 *
 * Runs first (0000_ prefix). The migration role needs CREATE EXTENSION privilege
 * (e.g. rds_superuser on RDS).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    }

    public function down(): void
    {
        // Intentionally NOT dropping extensions — other objects may depend on them
        // and they are cheap to leave in place.
    }
};
