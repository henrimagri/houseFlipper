<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_visualizations', function (Blueprint $table) {
            $table->string('scene_status', 40)->nullable();
            $table->json('scene_brief')->nullable();
            $table->json('scene_graph')->nullable();
        });

        Schema::table('property_photo_analyses', function (Blueprint $table) {
            $table->json('palette')->nullable();
            $table->json('pixel_stats')->nullable();
        });

        Schema::create('property_scene_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_visualization_id')->constrained('property_visualizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 120);
            $table->json('graph');
            $table->timestamps();
            $table->index(['property_visualization_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_scene_versions');
        Schema::table('property_photo_analyses', function (Blueprint $table) {
            $table->dropColumn(['palette', 'pixel_stats']);
        });
        Schema::table('property_visualizations', function (Blueprint $table) {
            $table->dropColumn(['scene_status', 'scene_brief', 'scene_graph']);
        });
    }
};
