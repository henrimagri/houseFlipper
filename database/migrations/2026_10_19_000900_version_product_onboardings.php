<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_onboardings', function (Blueprint $table) {
            $table->unsignedSmallInteger('tour_version')->default(1)->after('product_slug');
        });
    }

    public function down(): void
    {
        Schema::table('product_onboardings', function (Blueprint $table) {
            $table->dropColumn('tour_version');
        });
    }
};
