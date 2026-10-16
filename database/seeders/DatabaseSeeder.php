<?php

namespace Database\Seeders;

use App\Models\CategoriaDespesa;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo'],
            ['nome' => 'Tenant Demo', 'email_contato' => 'demo@houseflipper.local'],
        );

        User::firstOrCreate(
            ['email' => 'admin@houseflipper.local'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Admin Demo',
                'password' => 'password',
            ],
        );

        $categorias = [
            ['nome' => 'Aquisição', 'tipo' => 'aquisicao'],
            ['nome' => 'Mão de obra', 'tipo' => 'reforma'],
            ['nome' => 'Materiais', 'tipo' => 'reforma'],
            ['nome' => 'Projeto e Engenharia', 'tipo' => 'reforma'],
            ['nome' => 'IPTU', 'tipo' => 'holding'],
            ['nome' => 'Condomínio', 'tipo' => 'holding'],
            ['nome' => 'Energia/Água', 'tipo' => 'holding'],
            ['nome' => 'ITBI', 'tipo' => 'taxas'],
            ['nome' => 'Cartório', 'tipo' => 'taxas'],
            ['nome' => 'Comissão Corretor', 'tipo' => 'venda'],
            ['nome' => 'Marketing', 'tipo' => 'venda'],
        ];

        foreach ($categorias as $cat) {
            CategoriaDespesa::firstOrCreate(
                ['tenant_id' => $tenant->id, 'nome' => $cat['nome'], 'deletado_flg' => false],
                ['tipo' => $cat['tipo']],
            );
        }
    }
}
