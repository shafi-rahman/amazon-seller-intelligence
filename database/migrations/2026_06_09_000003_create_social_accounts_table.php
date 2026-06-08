<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 30);
            // facebook | instagram | linkedin | google_business
            $table->string('account_name', 200)->nullable();   // display name
            $table->string('account_id', 200)->nullable();     // page_id / ig_user_id / urn etc.
            $table->text('access_token')->nullable();          // encrypted
            $table->text('access_token_secondary')->nullable(); // encrypted (e.g. page token)
            $table->timestamp('token_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_connected')->default(false);
            $table->jsonb('meta')->default('{}');
            // { page_id, ig_user_id, linkedin_author_urn, location_name, etc. }
            $table->timestamps();

            $table->unique(['workspace_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
