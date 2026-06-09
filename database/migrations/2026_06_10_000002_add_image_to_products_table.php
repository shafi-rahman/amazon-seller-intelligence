<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_path', 500)->nullable()->after('source_type');
            // MinIO path: products/{workspace_id}/{product_public_id}/{filename}
        });

        Schema::table('competitors', function (Blueprint $table) {
            $table->string('product_image_path', 500)->nullable()->after('raw_html');
            // Competitor product image (uploaded manually or from parse)
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
        Schema::table('competitors', function (Blueprint $table) {
            $table->dropColumn('product_image_path');
        });
    }
};
