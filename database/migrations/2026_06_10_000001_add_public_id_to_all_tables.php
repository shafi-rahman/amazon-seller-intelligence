<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Tables exposed in browser URLs — must never show sequential integers */
    private array $tables = [
        'seo_campaigns',
        'import_batches',
        'reconciliation_runs',
        'products',
        'competitors',
        'ai_conversations',
        'reports',
        'workspaces',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            // Add public_id column with auto-generated UUID for existing rows
            DB::statement("
                ALTER TABLE {$table}
                ADD COLUMN IF NOT EXISTS public_id UUID NOT NULL DEFAULT gen_random_uuid()
            ");

            // Add unique index
            DB::statement("
                CREATE UNIQUE INDEX IF NOT EXISTS {$table}_public_id_unique
                ON {$table} (public_id)
            ");
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            DB::statement("DROP INDEX IF EXISTS {$table}_public_id_unique");
            DB::statement("ALTER TABLE {$table} DROP COLUMN IF EXISTS public_id");
        }
    }
};
