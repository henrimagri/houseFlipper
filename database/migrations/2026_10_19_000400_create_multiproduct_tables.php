<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_product_subscriptions', function (Blueprint $table) {
            $table->string('plan_slug', 80)->nullable()->after('product_slug');
            $table->timestamp('trial_starts_at')->nullable()->after('trial_ends_at');
            $table->timestamp('current_period_end')->nullable()->after('grace_until');
            $table->string('payment_provider', 40)->nullable()->after('current_period_end');
            $table->string('external_subscription_id', 120)->nullable()->after('payment_provider');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('last_product_slug', 40)->nullable()->after('tenant_id');
        });

        Schema::table('commercial_listings', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('tenant_id');
            $table->unsignedBigInteger('branch_id')->nullable()->after('organization_id');
            $table->index(['organization_id', 'branch_id']);
        });

        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('tenant_id');
            $table->unsignedBigInteger('branch_id')->nullable()->after('organization_id');
            $table->unsignedBigInteger('assigned_user_id')->nullable()->after('user_id');
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 80);
            $table->string('kind', 40)->default('imobiliaria');
            $table->boolean('ativo_flg')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id']);
            $table->unique(['slug']);
        });

        Schema::create('organization_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('role', 40)->default('agent');
            $table->string('status', 40)->default('active');
            $table->json('specialties')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('city', 120)->nullable();
            $table->string('state', 2)->nullable();
            $table->string('neighborhood', 120)->nullable();
            $table->boolean('ativo_flg')->default(true);
            $table->timestamps();
        });

        Schema::create('agency_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('slug', 80);
            $table->string('status', 40)->default('draft');
            $table->json('theme_json')->nullable();
            $table->timestamps();
            $table->unique(['slug']);
            $table->unique(['organization_id']);
        });

        Schema::create('crm_deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->unsignedBigInteger('crm_contact_id')->nullable();
            $table->unsignedBigInteger('commercial_listing_id')->nullable();
            $table->string('title');
            $table->string('stage', 40)->default('novo');
            $table->unsignedBigInteger('estimated_value_cents')->nullable();
            $table->timestamp('next_activity_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'stage']);
        });

        Schema::create('crm_deal_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('crm_deal_id')->constrained('crm_deals')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('from_stage', 40)->nullable();
            $table->string('to_stage', 40)->nullable();
            $table->text('body')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->unsignedBigInteger('crm_contact_id')->nullable();
            $table->unsignedBigInteger('commercial_listing_id')->nullable();
            $table->unsignedBigInteger('crm_deal_id')->nullable();
            $table->string('subject');
            $table->string('status', 40)->default('novo');
            $table->string('priority', 20)->default('normal');
            $table->string('channel', 40)->default('internal');
            $table->timestamp('return_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('crm_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('crm_ticket_id')->constrained('crm_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 40)->default('message');
            $table->text('body');
            $table->string('channel', 40)->default('internal');
            $table->json('delivery')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('crm_contact_id')->nullable();
            $table->unsignedBigInteger('commercial_listing_id')->nullable();
            $table->unsignedBigInteger('crm_deal_id')->nullable();
            $table->string('title');
            $table->unsignedBigInteger('amount_cents')->nullable();
            $table->string('status', 40)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('crm_contact_id')->nullable();
            $table->unsignedBigInteger('crm_deal_id')->nullable();
            $table->unsignedBigInteger('from_user_id')->nullable();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('strategy', 40);
            $table->string('reason', 160)->nullable();
            $table->timestamps();
        });

        Schema::create('lead_roulette_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('strategy', 40)->default('round_robin');
            $table->unsignedInteger('cursor')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'branch_id', 'strategy']);
        });

        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->decimal('broker_percent', 5, 2)->default(0);
            $table->decimal('agency_percent', 5, 2)->default(0);
            $table->decimal('branch_percent', 5, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('crm_deal_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->string('kind', 40);
            $table->string('status', 40)->default('projected');
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('saved_map_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('product_slug', 40);
            $table->string('name');
            $table->string('mode', 40)->default('properties');
            $table->json('geometry')->nullable();
            $table->json('filters')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_product_subscription_id')->constrained('tenant_product_subscriptions')->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->string('provider_reference', 120)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('feature_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('product_slug', 40);
            $table->string('feature_code', 80);
            $table->string('period', 20);
            $table->unsignedInteger('consumed')->default(0);
            $table->unsignedInteger('limit_value')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'product_slug', 'feature_code', 'period'], 'feature_usage_unique');
        });

        Schema::create('platform_admins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->timestamps();
            $table->unique(['user_id']);
            $table->unique(['email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_admins');
        Schema::dropIfExists('feature_usages');
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('saved_map_searches');
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('lead_roulette_states');
        Schema::dropIfExists('lead_assignments');
        Schema::dropIfExists('crm_proposals');
        Schema::dropIfExists('crm_ticket_messages');
        Schema::dropIfExists('crm_tickets');
        Schema::dropIfExists('crm_deal_activities');
        Schema::dropIfExists('crm_deals');
        Schema::dropIfExists('agency_sites');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('organization_memberships');
        Schema::dropIfExists('organizations');

        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->dropColumn(['organization_id', 'branch_id', 'assigned_user_id']);
        });
        Schema::table('commercial_listings', function (Blueprint $table) {
            $table->dropColumn(['organization_id', 'branch_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_product_slug');
        });
        Schema::table('tenant_product_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['plan_slug', 'trial_starts_at', 'current_period_end', 'payment_provider', 'external_subscription_id']);
        });
    }
};
