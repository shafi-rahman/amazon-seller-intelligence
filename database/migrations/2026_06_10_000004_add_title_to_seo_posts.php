<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_posts', function (Blueprint $table) {
            $table->string('title', 300)->nullable()->after('platform');
        });
    }

    public function down(): void
    {
        Schema::table('seo_posts', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
