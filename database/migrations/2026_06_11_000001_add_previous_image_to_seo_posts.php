<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_posts', function (Blueprint $table) {
            // Holds the image this post had before the last change, so the user
            // can revert if a newly applied image doesn't look good.
            $table->string('previous_image_path', 500)->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('seo_posts', function (Blueprint $table) {
            $table->dropColumn('previous_image_path');
        });
    }
};
