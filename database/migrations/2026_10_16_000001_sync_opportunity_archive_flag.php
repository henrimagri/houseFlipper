<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('opportunities')->where('status', 'archived')->update(['is_archived' => true]);
        DB::table('opportunities')->where('status', '!=', 'archived')->update(['is_archived' => false]);
    }

    public function down(): void
    {
        // Derived data correction: historical inconsistent values cannot be reconstructed.
    }
};
