<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add content dedup keys so re-uploading the same bank statement / GST report
 * does not insert duplicate rows (which would inflate credit-sums and double GST
 * tax, corrupting the finance dashboard and reconciliation).
 *
 * `row_hash` = sha1 of the normalized source row (computed by the parsers).
 * Existing rows are backfilled with a guaranteed-unique placeholder so the unique
 * index can be created without violating it; dedup therefore applies to all
 * imports going forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->char('row_hash', 40)->nullable()->after('raw_row');
        });
        DB::statement("UPDATE bank_transactions SET row_hash = lpad(id::text, 40, '0') WHERE row_hash IS NULL");
        DB::statement('CREATE UNIQUE INDEX bank_transactions_ws_rowhash_unique ON bank_transactions (workspace_id, row_hash)');

        Schema::table('gst_transactions', function (Blueprint $table) {
            $table->char('row_hash', 40)->nullable()->after('raw_row');
        });
        DB::statement("UPDATE gst_transactions SET row_hash = lpad(id::text, 40, '0') WHERE row_hash IS NULL");
        DB::statement('CREATE UNIQUE INDEX gst_transactions_ws_rowhash_unique ON gst_transactions (workspace_id, row_hash)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS bank_transactions_ws_rowhash_unique');
        DB::statement('DROP INDEX IF EXISTS gst_transactions_ws_rowhash_unique');
        Schema::table('bank_transactions', fn (Blueprint $t) => $t->dropColumn('row_hash'));
        Schema::table('gst_transactions', fn (Blueprint $t) => $t->dropColumn('row_hash'));
    }
};
