<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_knowledge_documents', function (Blueprint $table) {
            $table->string('source_key', 64)->nullable()->after('product_slug');
            $table->index('source_key');
        });
        Schema::table('ai_knowledge_chunks', function (Blueprint $table) {
            $table->string('vector_point_id', 64)->nullable()->after('ordinal');
            $table->string('embedding_provider', 40)->nullable()->after('metadata');
            $table->string('embedding_model', 100)->nullable()->after('embedding_provider');
            $table->unsignedInteger('embedding_dimensions')->nullable()->after('embedding_model');
            $table->string('embedding_hash', 64)->nullable()->after('embedding_dimensions');
            $table->string('vector_status', 20)->default('pending')->after('embedding_hash');
            $table->timestamp('vector_indexed_at')->nullable()->after('vector_status');
            $table->text('vector_error')->nullable()->after('vector_indexed_at');
            $table->unique('vector_point_id');
            $table->index(['vector_status', 'updated_at'], 'ai_chunks_vector_status_index');
        });

        DB::table('ai_knowledge_documents')->orderBy('id')->each(function ($document): void {
            DB::table('ai_knowledge_documents')->where('id', $document->id)->update([
                'source_key' => hash('sha256', implode('|', [
                    $document->tenant_id ?? 'public',
                    $document->product_slug ?? '*',
                    $document->source_url ?: $document->source_name,
                    $document->title,
                ])),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('ai_knowledge_chunks', function (Blueprint $table) {
            $table->dropIndex('ai_chunks_vector_status_index');
            $table->dropUnique(['vector_point_id']);
            $table->dropColumn(['vector_point_id', 'embedding_provider', 'embedding_model', 'embedding_dimensions', 'embedding_hash', 'vector_status', 'vector_indexed_at', 'vector_error']);
        });
        Schema::table('ai_knowledge_documents', function (Blueprint $table) {
            $table->dropIndex(['source_key']);
            $table->dropColumn('source_key');
        });
    }
};
