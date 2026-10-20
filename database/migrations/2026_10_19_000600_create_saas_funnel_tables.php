<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_leads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->string('email', 180);
            $table->string('phone', 40)->nullable();
            $table->string('product_slug', 40)->nullable();
            $table->string('business_type', 80)->nullable();
            $table->string('intent', 40)->default('demo');
            $table->text('message')->nullable();
            $table->string('source_path', 180)->nullable();
            $table->string('status', 40)->default('novo');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->boolean('consent')->default(false);
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
            $table->index(['status', 'product_slug']);
            $table->index('created_at');
        });

        Schema::create('saas_lead_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saas_lead_id')->constrained('saas_leads')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->text('body')->nullable();
            $table->timestamps();
        });

        Schema::create('product_onboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id');
            $table->string('product_slug', 40);
            $table->unsignedTinyInteger('current_step')->default(0);
            $table->json('completed_steps')->nullable();
            $table->boolean('dismissed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'product_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_onboardings');
        Schema::dropIfExists('saas_lead_events');
        Schema::dropIfExists('saas_leads');
    }
};
