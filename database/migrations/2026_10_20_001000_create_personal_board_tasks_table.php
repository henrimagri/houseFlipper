<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('personal_board_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('backlog');
            $table->string('priority', 20)->default('normal');
            $table->date('due_date')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'status', 'position'], 'personal_board_scope_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_board_tasks');
    }
};
