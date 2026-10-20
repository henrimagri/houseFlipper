<?php

namespace Database\Seeders;

use App\Models\CategoriaDespesa;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use App\Services\Workspace\OrganizationService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

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

        TenantProductSubscription::firstOrCreate(
            ['tenant_id' => $tenant->id, 'product_slug' => 'house_flipper'],
            ['active' => true, 'status' => 'active', 'plan_slug' => 'house_flipper_mensal'],
        );

        $this->seedPersona('corretor@houseflipper.local', 'Corretor Demo', 'corretor-demo', 'corretor', 'trial');
        $this->seedPersona('imobiliaria@houseflipper.local', 'Imobiliária Demo', 'imobiliaria-demo', 'imobiliaria', 'trial');
        $comboTenant = $this->seedPersona('combo@houseflipper.local', 'Combo Demo', 'combo-demo', 'corretor', 'trial');
        TenantProductSubscription::firstOrCreate(
            ['tenant_id' => $comboTenant->id, 'product_slug' => 'house_flipper'],
            ['active' => true, 'status' => 'active', 'plan_slug' => 'house_flipper_mensal'],
        );
        $this->seedPersona('expirado@houseflipper.local', 'Expirado Demo', 'expirado-demo', 'corretor', 'cancelled');
        $platformTenant = Tenant::firstOrCreate(
            ['slug' => 'platform-ops'],
            ['nome' => 'Operação da plataforma', 'email_contato' => 'platform@houseflipper.local'],
        );
        $platform = User::firstOrCreate(
            ['email' => 'platform@houseflipper.local'],
            [
                'tenant_id' => $platformTenant->id,
                'name' => 'Platform Admin',
                'password' => 'password',
            ],
        );
        PlatformAdmin::firstOrCreate(
            ['user_id' => $platform->id],
            ['email' => $platform->email],
        );

        $this->seedCategories($tenant);
    }

    private function seedPersona(string $email, string $name, string $slug, string $product, string $status): Tenant
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => $slug],
            ['nome' => $name, 'email_contato' => $email],
        );
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'tenant_id' => $tenant->id,
                'name' => $name,
                'password' => 'password',
            ],
        );
        $trial = $status === 'trial';
        TenantProductSubscription::firstOrCreate(
            ['tenant_id' => $tenant->id, 'product_slug' => $product],
            [
                'active' => $trial,
                'status' => $status,
                'plan_slug' => $product.'_trial',
                'trial_starts_at' => $trial ? now() : now()->subDays(20),
                'trial_ends_at' => $trial ? now()->addDays(7) : now()->subDays(6),
            ],
        );
        if ($product === 'imobiliaria') {
            app(OrganizationService::class)->ensureForTenant($user);
        }

        return $tenant;
    }

    private function seedCategories(Tenant $tenant): void
    {
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
