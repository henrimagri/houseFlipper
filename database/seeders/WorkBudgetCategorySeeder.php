<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkBudgetCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categorias = [
            'Demolição',
            'Elétrica',
            'Hidráulica',
            'Acabamento',
        ];

        foreach ($categorias as $cat) {
            DB::table('work_budgets')->updateOrInsert(
                [
                    'work_id' => 0, // 0 = modelo/padrão, não vinculado a obra real
                    'category' => $cat,
                ],
                [
                    'estimated_cost' => 0.00,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
