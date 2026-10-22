<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('platform_mfa_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('secret');
            $table->text('recovery_hashes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('pending_expires_at')->nullable();
            $table->bigInteger('last_step')->default(-1);
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->timestamp('platform_mfa_at')->nullable();
            $table->timestamp('platform_recent_auth_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', fn (Blueprint $table) => $table->dropColumn(['platform_mfa_at', 'platform_recent_auth_at']));
        Schema::dropIfExists('platform_mfa_credentials');
    }
};
