<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Tenant;
use App\Models\TenantProductSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuctionImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_house_flipper_can_import_and_deduplicate_official_auction_file(): void
    {
        [$user, $token] = $this->userWithProduct('house_flipper', 'auction');
        $csv = "Lista de Imóveis da Caixa;;Data de geração:;20/10/2026;;;;;\n"
            ."N° do imóvel;UF;Cidade;Bairro;Endereço;Preço;Valor de avaliação;Desconto;Financiamento;Descrição;Modalidade de venda;Link de acesso\n"
            ."CX-001;SP;São Paulo;Perdizes;Rua Exemplo, 10;250.000,00;320.000,00;21;Sim;Apartamento, 50 m2, 2 quartos;Venda direta;https://venda-imoveis.caixa.gov.br/detalhe.asp?id=1\n";

        foreach ([1, 2] as $run) {
            $this->withToken($token)->post('/api/auctions/import', [
                'provider' => 'caixa',
                'file' => UploadedFile::fake()->createWithContent("caixa-{$run}.csv", $csv),
            ], ['Accept' => 'application/json'])->assertCreated();
        }

        $this->assertDatabaseCount('auction_listings', 1);
        $this->assertDatabaseHas('auction_listings', ['provider' => 'caixa', 'source_id' => 'CX-001', 'price_cents' => 25000000]);
        $this->withToken($token)->getJson('/api/auctions?state=SP&city=Paulo&neighborhood=Perdizes&property_type=Apartamento&max_price_cents=26000000')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_house_flipper_can_refresh_caixa_catalog_by_state(): void
    {
        [, $token] = $this->userWithProduct('house_flipper', 'automatic-auction');
        Http::fake([
            'https://venda-imoveis.caixa.gov.br/listaweb/Lista_imoveis_AC.csv' => Http::response(
                "Lista de Imóveis da Caixa;;Data de geração:;20/10/2026;;;;;\n"
                ."N° do imóvel;UF;Cidade;Bairro;Endereço;Preço;Valor de avaliação;Desconto;Financiamento;Descrição;Modalidade de venda;Link de acesso\n"
                ."10005120;AC;Cruzeiro do Sul;Centro;Rua do Purus;50.074,26;150.000,00;66;Não;Terreno, 600 m2;Venda Direta Online;https://venda-imoveis.caixa.gov.br/sistema/detalhe-imovel.asp?hdnimovel=10005120\n",
                200,
                ['Content-Type' => 'text/csv'],
            ),
        ]);

        $this->withToken($token)->postJson('/api/auctions/sync', ['state' => 'AC'])
            ->assertCreated()
            ->assertJsonPath('data.rows_imported', 1);
        $this->assertDatabaseHas('auction_listings', ['source_id' => '10005120', 'city' => 'Cruzeiro do Sul', 'property_type' => 'Terreno']);
    }

    public function test_non_house_flipper_subscription_cannot_import_auctions(): void
    {
        [, $token] = $this->userWithProduct('corretor', 'broker');
        $file = UploadedFile::fake()->createWithContent('caixa.csv', "id;titulo\n1;Teste\n");
        $this->withToken($token)->post('/api/auctions/import', ['provider' => 'caixa', 'file' => $file], ['Accept' => 'application/json'])->assertForbidden();
    }

    private function userWithProduct(string $product, string $slug): array
    {
        $tenant = Tenant::query()->create(['nome' => $slug, 'slug' => $slug]);
        $user = User::query()->create(['tenant_id' => $tenant->id, 'name' => $slug, 'email' => $slug.'@example.test', 'email_verified_at' => now(), 'password' => 'password']);
        TenantProductSubscription::query()->create(['tenant_id' => $tenant->id, 'product_slug' => $product, 'active' => true, 'status' => SubscriptionStatus::Active]);
        return [$user, $user->createToken('test')->plainTextToken];
    }
}
