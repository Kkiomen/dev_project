<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('platform_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('platform'); // facebook, instagram
            $table->string('platform_user_id'); // Facebook User ID or Page ID
            $table->string('platform_user_name')->nullable(); // Page/account name
            $table->text('access_token'); // Encrypted
            $table->timestamp('token_expires_at')->nullable();
            $table->text('refresh_token')->nullable(); // Encrypted
            $table->json('metadata')->nullable();
            // Structure: {
            //     "page_id": "123456789",
            //     "instagram_business_id": "987654321",
            //     "facebook_page_id": "123456789" (for Instagram)
            // }
            $table->timestamps();

            $table->unique(['brand_id', 'platform']);
            $table->index('platform');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_credentials');
    }
};
