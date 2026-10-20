<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->text('notes')->nullable();
            $table->json('preferences')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->string('priority', 20)->default('normal');
        });
        Schema::table('crm_deals', function (Blueprint $table) {
            $table->string('priority', 20)->default('normal');
            $table->unsignedInteger('lock_version')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->dropColumn(['notes', 'preferences', 'next_action_at', 'priority']);
        });
        Schema::table('crm_deals', function (Blueprint $table) {
            $table->dropColumn(['priority', 'lock_version']);
        });
    }
};
