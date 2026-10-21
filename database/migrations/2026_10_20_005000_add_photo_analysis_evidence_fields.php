<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_photo_analyses', function (Blueprint $table) {
            $table->unsignedTinyInteger('confidence')->nullable()->default(null)->change();
            $table->unsignedTinyInteger('detection_confidence')->nullable()->after('confidence');
            $table->unsignedTinyInteger('room_confidence')->nullable()->after('detection_confidence');
            $table->json('room_provenance')->nullable()->after('room_confidence');
        });
    }

    public function down(): void
    {
        Schema::table('property_photo_analyses', function (Blueprint $table) {
            $table->dropColumn(['detection_confidence', 'room_confidence', 'room_provenance']);
            $table->unsignedTinyInteger('confidence')->default(0)->nullable(false)->change();
        });
    }
};
