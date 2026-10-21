<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_processing_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_visualization_id')->constrained('property_visualizations')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('worker_job_id')->nullable()->unique();
            $table->string('kind', 40);
            $table->string('status', 40)->default('queued');
            $table->json('input_asset_ids')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'property_visualization_id', 'created_at'], 'processing_jobs_tenant_visualization_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_processing_jobs');
    }
};
