<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->index(['created_at', 'id'], 'platform_users_created'));
        Schema::table('tenant_product_subscriptions', function (Blueprint $t) {
            $t->index(['status', 'trial_ends_at'], 'platform_subscription_trial');
            $t->index(['created_at', 'id'], 'platform_subscription_created');
        });
        Schema::table('property_processing_jobs', fn (Blueprint $t) => $t->index(['created_at', 'id'], 'platform_processing_created'));
        Schema::table('ai_tool_runs', fn (Blueprint $t) => $t->index(['created_at', 'product_slug'], 'platform_tools_created'));
        Schema::table('platform_events', fn (Blueprint $t) => $t->index(['category', 'status', 'created_at'], 'platform_category_status_time'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropIndex('platform_users_created'));
        Schema::table('tenant_product_subscriptions', function (Blueprint $t) {
            $t->dropIndex('platform_subscription_trial');
            $t->dropIndex('platform_subscription_created');
        });
        Schema::table('property_processing_jobs', fn (Blueprint $t) => $t->dropIndex('platform_processing_created'));
        Schema::table('ai_tool_runs', fn (Blueprint $t) => $t->dropIndex('platform_tools_created'));
        Schema::table('platform_events', fn (Blueprint $t) => $t->dropIndex('platform_category_status_time'));
    }
};
