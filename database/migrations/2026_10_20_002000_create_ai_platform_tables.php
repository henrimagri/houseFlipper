<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_search_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('tenant_id');
            $table->string('title', 120)->nullable()->after('product_slug');
            $table->string('status', 20)->default('active')->after('title');
            $table->string('active_agent', 60)->nullable()->after('status');
            $table->string('prompt_version', 40)->nullable()->after('active_agent');
            $table->timestamp('expires_at')->nullable()->after('last_recommendation');
            $table->index(['tenant_id', 'product_slug', 'updated_at'], 'ai_sessions_scope_index');
        });

        Schema::table('ai_search_messages', function (Blueprint $table) {
            $table->json('citations')->nullable()->after('listing_ids');
            $table->json('ui_blocks')->nullable()->after('citations');
            $table->json('tool_events')->nullable()->after('ui_blocks');
        });

        Schema::create('ai_user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('product_slug', 40);
            $table->json('preferences');
            $table->timestamp('consented_at');
            $table->timestamps();
            $table->unique(['user_id', 'product_slug']);
        });

        Schema::create('ai_knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('product_slug', 40)->nullable();
            $table->string('title', 180);
            $table->string('category', 60)->default('platform');
            $table->string('source_url', 500)->nullable();
            $table->string('source_name', 160);
            $table->string('source_version', 80)->nullable();
            $table->string('license', 80)->nullable();
            $table->string('geography', 80)->default('BR');
            $table->string('content_hash', 64)->unique();
            $table->string('status', 20)->default('active');
            $table->timestamp('verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'product_slug', 'status'], 'ai_docs_scope_index');
        });

        Schema::create('ai_knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_knowledge_document_id')->constrained('ai_knowledge_documents')->cascadeOnDelete();
            $table->unsignedInteger('ordinal');
            $table->text('content');
            $table->text('search_text');
            $table->json('keywords')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['ai_knowledge_document_id', 'ordinal'], 'ai_chunk_document_ordinal_unique');
        });

        Schema::create('ai_response_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->foreignId('ai_search_session_id')->constrained('ai_search_sessions')->cascadeOnDelete();
            $table->foreignId('ai_search_message_id')->nullable()->constrained('ai_search_messages')->nullOnDelete();
            $table->string('product_slug', 40);
            $table->string('rating', 16);
            $table->string('reason', 80)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'product_slug', 'created_at'], 'ai_feedback_scope_index');
        });

        Schema::create('ai_tool_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->foreignId('ai_search_session_id')->nullable()->constrained('ai_search_sessions')->nullOnDelete();
            $table->string('product_slug', 40)->nullable();
            $table->string('agent', 60);
            $table->string('tool', 80);
            $table->string('status', 20);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('provider', 40)->nullable();
            $table->string('model', 100)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('estimated_cost_usd', 12, 6)->nullable();
            $table->string('input_hash', 64);
            $table->json('input_summary')->nullable();
            $table->unsignedInteger('result_count')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'product_slug', 'created_at'], 'ai_tool_runs_scope_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tool_runs');
        Schema::dropIfExists('ai_response_feedback');
        Schema::dropIfExists('ai_knowledge_chunks');
        Schema::dropIfExists('ai_knowledge_documents');
        Schema::dropIfExists('ai_user_preferences');
        Schema::table('ai_search_messages', fn (Blueprint $table) => $table->dropColumn(['citations', 'ui_blocks', 'tool_events']));
        Schema::table('ai_search_sessions', function (Blueprint $table) {
            $table->dropIndex('ai_sessions_scope_index');
            $table->dropColumn(['organization_id', 'title', 'status', 'active_agent', 'prompt_version', 'expires_at']);
        });
    }
};
