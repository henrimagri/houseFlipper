<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_id');
            $table->string('category');
            $table->decimal('estimated_cost', 12, 2);
            $table->timestamps();

            $table->foreign('work_id')->references('id')->on('projetos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_budgets');
    }
};
