<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_resource_usage', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->string('product', 40);
            $t->string('resource', 40);
            $t->string('period', 7);
            $t->unsignedBigInteger('used')->default(0);
            $t->unique(['tenant_id', 'product', 'resource', 'period'], 'resource_scope_period');
        });
        Schema::create('platform_resource_admissions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->string('product', 40);
            $t->string('resource', 40);
            $t->uuid('operation_id');
            $t->timestamp('created_at');
            $t->unique(['tenant_id', 'product', 'resource', 'operation_id'], 'resource_operation');
        });
        Schema::create('platform_storage_inventory', function (Blueprint $t) {
            $t->id();
            $t->string('disk', 30);
            $t->string('path_hash', 64);
            $t->unsignedBigInteger('bytes');
            $t->string('kind', 30);
            $t->boolean('referenced');
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->string('product', 40)->nullable();
            $t->timestamp('modified_at')->nullable();
            $t->timestamp('observed_at');
            $t->unique(['disk', 'path_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_storage_inventory');
        Schema::dropIfExists('platform_resource_admissions');
        Schema::dropIfExists('platform_resource_usage');
    }
};
