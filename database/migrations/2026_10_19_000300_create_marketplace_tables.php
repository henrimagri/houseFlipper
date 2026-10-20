<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_product_subscriptions', function (Blueprint $table) {
            $table->string('status', 30)->default('active')->after('active');
            $table->timestamp('trial_ends_at')->nullable()->after('status');
            $table->timestamp('grace_until')->nullable()->after('trial_ends_at');
        });

        Schema::create('listing_sources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40);
            $table->string('external_id', 120)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('title');
            $table->unsignedBigInteger('price_cents')->nullable();
            $table->decimal('area_m2', 10, 2)->nullable();
            $table->json('payload_json')->nullable();
            $table->string('content_hash', 64);
            $table->timestamp('captured_at');
            $table->timestamps();
            $table->unique(['user_id', 'content_hash']);
            $table->index(['tenant_id', 'provider']);
        });

        Schema::create('commercialization_authorizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_source_id')->nullable()->constrained('listing_sources')->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('propriedade_id')->nullable();
            $table->string('owner_name', 180)->nullable();
            $table->string('owner_document_hash', 64)->nullable();
            $table->string('owner_contact', 180)->nullable();
            $table->string('scope', 40)->default('divulgar');
            $table->string('status', 30)->default('pending');
            $table->string('evidence_path', 255)->nullable();
            $table->text('evidence_notes')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['user_id', 'opportunity_id']);
        });

        Schema::create('broker_sites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 80)->unique();
            $table->string('status', 30)->default('trial');
            $table->json('theme_json')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();
            $table->unique('user_id');
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('commercial_listings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('authorization_id')->constrained('commercialization_authorizations')->restrictOnDelete();
            $table->foreignId('listing_source_id')->nullable()->constrained('listing_sources')->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug', 100)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('property_type', 30);
            $table->unsignedBigInteger('asking_price_cents')->nullable();
            $table->decimal('area_m2', 10, 2)->nullable();
            $table->unsignedSmallInteger('bedrooms')->nullable();
            $table->unsignedSmallInteger('bathrooms')->nullable();
            $table->unsignedSmallInteger('parking_spaces')->nullable();
            $table->string('address')->nullable();
            $table->string('neighborhood', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->char('state', 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 30)->default('draft');
            $table->boolean('highlight')->default(false);
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('unpublished_at')->nullable();
            $table->string('unpublish_reason', 120)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'status', 'user_id']);
            $table->index(['city', 'neighborhood']);
        });

        Schema::create('commercial_listing_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_listing_id')->constrained('commercial_listings')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->boolean('licensed_for_publication')->default(false);
            $table->boolean('is_cover')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commercial_listing_id')->nullable()->constrained('commercial_listings')->nullOnDelete();
            $table->string('kind', 20)->default('lead');
            $table->string('name', 180);
            $table->string('email', 180)->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('message')->nullable();
            $table->boolean('consent')->default(false);
            $table->string('source', 40)->default('public_form');
            $table->string('status', 30)->default('new');
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'status']);
        });

        Schema::create('marketplace_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('billing_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('provider_event_id', 120);
            $table->string('status', 30)->default('received');
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_webhook_events');
        Schema::dropIfExists('marketplace_audit_logs');
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('commercial_listing_photos');
        Schema::dropIfExists('commercial_listings');
        Schema::dropIfExists('broker_sites');
        Schema::dropIfExists('commercialization_authorizations');
        Schema::dropIfExists('listing_sources');
        Schema::table('tenant_product_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['status', 'trial_ends_at', 'grace_until']);
        });
    }
};
