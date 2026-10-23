<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_consent_attributions', function (Blueprint $t) {
            $t->id();
            $t->string('session_hash', 64)->unique();
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->string('policy', 30);
            $t->json('utm');
            $t->timestamp('consented_at');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_consent_attributions');
    }
};
