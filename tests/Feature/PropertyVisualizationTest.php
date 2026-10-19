<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Propriedade;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PropertyPortalScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PropertyVisualizationTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $slug = 'viz'): User
    {
        $tenant = Tenant::create(['nome' => $slug, 'slug' => $slug]);

        return User::factory()->create(['tenant_id' => $tenant->id]);
    }

    public function test_property_with_rooms_exposes_parametric_maquette(): void
    {
        $owner = $this->account('viz-param');
        $this->actingAs($owner, 'sanctum');
        $property = Propriedade::create([
            'apelido' => 'Apto paramétrico',
            'tipo' => 'apartamento',
            'status' => 'prospeccao',
            'quartos' => 2,
            'banheiros' => 1,
            'vagas' => 1,
            'area_m2' => 78,
        ]);

        $this->getJson('/api/visualizations/propriedades/'.$property->id)
            ->assertOk()
            ->assertJsonPath('data.faithful', false)
            ->assertJsonPath('data.illustrative', true)
            ->assertJsonPath('data.level', 'parametric_spec')
            ->assertJsonPath('data.subject.property_type', 'apartamento');
    }

    public function test_property_page_returns_explicit_fallback_without_model(): void
    {
        $owner = $this->account();
        $this->actingAs($owner, 'sanctum');
        $property = Propriedade::create(['apelido' => 'Casa QA', 'status' => 'prospeccao']);

        $this->getJson('/api/visualizations/propriedades/'.$property->id)
            ->assertOk()
            ->assertJsonPath('data.faithful', false)
            ->assertJsonPath('data.illustrative', true)
            ->assertJsonPath('data.model', null)
            ->assertJsonPath('data.level', 'fallback_gallery');
    }

    public function test_valid_glb_becomes_faithful_model_and_stays_isolated(): void
    {
        Storage::fake('local');
        $owner = $this->account('viz-a');
        $this->actingAs($owner, 'sanctum');
        $property = Propriedade::create(['apelido' => 'Casa 3D', 'status' => 'prospeccao']);
        $file = UploadedFile::fake()->createWithContent('sala.glb', 'glTF'.str_repeat("\0", 64));

        $assetId = $this->post('/api/visualizations/propriedades/'.$property->id.'/assets', [
            'arquivo' => $file,
            'kind' => 'glb',
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        $this->getJson('/api/visualizations/propriedades/'.$property->id)
            ->assertOk()
            ->assertJsonPath('data.faithful', true)
            ->assertJsonPath('data.level', 'a_model')
            ->assertJsonPath('data.model.id', $assetId);

        $this->actingAs($this->account('viz-b'), 'sanctum');
        $this->getJson('/api/visualizations/propriedades/'.$property->id)->assertNotFound();
        $this->get('/api/visualizations/propriedades/'.$property->id.'/assets/'.$assetId.'/file')->assertNotFound();
    }

    public function test_opportunity_photo_analysis_records_origin_and_does_not_claim_measurements(): void
    {
        $owner = $this->account('photos');
        $this->actingAs($owner, 'sanctum');
        $opportunity = Opportunity::create([
            'user_id' => $owner->id, 'title' => 'Lead', 'property_type' => 'apartment', 'source' => 'manual',
        ]);
        $opportunity->photos()->create(['path' => 'opportunities/demo.jpg', 'original_name' => 'sala-estar-janela.jpg', 'order' => 0, 'is_cover' => true]);

        $analysis = $this->postJson('/api/opportunities/'.$opportunity->id.'/photo-analysis')
            ->assertOk()
            ->json('data.0');

        $this->assertSame('living', $analysis['room_guess']);
        $this->assertSame('filename_heuristic', $analysis['origin']);
        $this->assertLessThan(80, $analysis['confidence']);
        $this->assertTrue(collect($analysis['limitations'])->contains(fn ($line) => str_contains($line, 'medida') || str_contains($line, 'Medida')));
    }

    public function test_single_room_photo_builds_editable_scene_and_commands_mutate_it(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $owner = $this->account('viz-photo');
        $this->actingAs($owner, 'sanctum');
        $opportunity = Opportunity::create([
            'user_id' => $owner->id,
            'title' => 'Apto com sala fotografada',
            'property_type' => 'apartment',
            'source' => 'manual',
            'area_m2' => 94,
            'bedrooms' => 2,
            'bathrooms' => 1,
        ]);
        $this->putJpeg('public', 'opportunities/sala-estar-janela-sofa.jpg');
        $opportunity->photos()->create([
            'path' => 'opportunities/sala-estar-janela-sofa.jpg',
            'original_name' => 'sala-estar-janela-sofa.jpg',
            'order' => 0,
            'is_cover' => true,
        ]);

        $payload = $this->postJson('/api/visualizations/opportunities/'.$opportunity->id.'/rebuild')
            ->assertOk()
            ->json('data');

        $this->assertSame('photo_referenced', $payload['level']);
        $this->assertFalse($payload['faithful']);
        $this->assertNotEmpty($payload['scene_graph']['photo_planes']);
        $this->assertSame(['living'], array_column($payload['scene_graph']['rooms'], 'key'));
        $this->assertFalse($payload['scene_brief']['fachada_disponivel']);
        $this->assertSame('conceptual', $payload['scene_graph']['exterior']['kind']);
        $this->assertTrue(collect($payload['scene_brief']['limitations'])->contains(fn ($line) => str_contains($line, 'planta')));

        $paintedResponse = $this->postJson('/api/visualizations/opportunities/'.$opportunity->id.'/commands', [
            'message' => 'Quero paredes azuis',
        ])->assertOk();
        $this->assertTrue($paintedResponse->json('command.ok'), json_encode($paintedResponse->json('command')));
        $painted = $paintedResponse->json('data');
        $wall = collect($painted['scene_graph']['surfaces'])->firstWhere('kind', 'wall');
        $this->assertSame('#9bb7d4', $wall['color']);

        $originalId = collect($payload['versions'])->firstWhere('label', 'original')['id'] ?? null;
        $this->assertNotNull($originalId);
        $restored = $this->postJson('/api/visualizations/opportunities/'.$opportunity->id.'/versions/'.$originalId.'/restore')
            ->assertOk()
            ->json('data');
        $this->assertNotSame($wall['color'], collect($restored['scene_graph']['surfaces'])->firstWhere('kind', 'wall')['color']);

        $blocked = $this->postJson('/api/visualizations/opportunities/'.$opportunity->id.'/commands', [
            'message' => 'delete from users',
        ])->assertOk()->json('command');
        $this->assertFalse($blocked['ok']);
    }

    public function test_bedroom_photo_does_not_claim_a_faithful_floorplan(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $owner = $this->account('viz-bed');
        $this->actingAs($owner, 'sanctum');
        $opportunity = Opportunity::create([
            'user_id' => $owner->id, 'title' => 'Quarto só', 'property_type' => 'apartment', 'source' => 'manual',
        ]);
        $this->putJpeg('public', 'opportunities/quarto-cama.jpg', 90, 90, 110);
        $opportunity->photos()->create(['path' => 'opportunities/quarto-cama.jpg', 'original_name' => 'quarto-cama.jpg', 'order' => 0, 'is_cover' => true]);

        $payload = $this->postJson('/api/visualizations/opportunities/'.$opportunity->id.'/rebuild')->assertOk()->json('data');
        $this->assertSame(['bedroom'], array_column($payload['scene_graph']['rooms'], 'key'));
        $this->assertNotContains('living', array_column($payload['scene_graph']['rooms'], 'key'));
        $this->assertFalse($payload['faithful']);
        $this->assertSame('photo_referenced', $payload['scene_graph']['fidelity']);
    }

    public function test_scene_photos_stay_isolated_per_listing_and_tenant(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $owner = $this->account('viz-iso-a');
        $this->actingAs($owner, 'sanctum');
        $first = Opportunity::create(['user_id' => $owner->id, 'title' => 'Lead A', 'property_type' => 'house', 'source' => 'manual']);
        $second = Opportunity::create(['user_id' => $owner->id, 'title' => 'Lead B', 'property_type' => 'house', 'source' => 'manual']);
        $this->putJpeg('public', 'opportunities/fachada-a.jpg', 120, 90, 70);
        $this->putJpeg('public', 'opportunities/fachada-b.jpg', 40, 80, 140);
        $first->photos()->create(['path' => 'opportunities/fachada-a.jpg', 'original_name' => 'fachada-a.jpg', 'is_cover' => true]);
        $second->photos()->create(['path' => 'opportunities/fachada-b.jpg', 'original_name' => 'fachada-b.jpg', 'is_cover' => true]);

        $sceneA = $this->postJson('/api/visualizations/opportunities/'.$first->id.'/rebuild')->assertOk()->json('data');
        $sceneB = $this->postJson('/api/visualizations/opportunities/'.$second->id.'/rebuild')->assertOk()->json('data');
        $this->assertSame(['fachada-a.jpg'], array_column($sceneA['scene_graph']['photo_assets'], 'name'));
        $this->assertSame(['fachada-b.jpg'], array_column($sceneB['scene_graph']['photo_assets'], 'name'));
        $this->assertTrue($sceneA['scene_brief']['fachada_disponivel']);
        $this->assertNotEquals($sceneA['id'], $sceneB['id']);

        $this->actingAs($this->account('viz-iso-b'), 'sanctum');
        $this->getJson('/api/visualizations/opportunities/'.$first->id)->assertNotFound();
        $this->postJson('/api/visualizations/opportunities/'.$first->id.'/rebuild')->assertNotFound();
    }

    public function test_bedroom_filter_is_applied_on_existing_search_endpoint(): void
    {
        $this->actingAs($this->account('filter'), 'sanctum');
        $this->mock(PropertyPortalScraper::class, function ($mock): void {
            $mock->shouldReceive('scrape')->once()->andReturn([
                'source_name' => 'QA scraper',
                'diagnostics' => [],
                'items' => [[
                    'id' => 'one-bed', 'title' => 'Apartamento em Perdizes', 'address' => 'Perdizes',
                    'city' => 'São Paulo', 'neighborhood' => 'Perdizes', 'state' => 'SP',
                    'source' => 'OLX', 'source_key' => 'olx', 'source_url' => 'https://example.test/1',
                    'thumbnail' => null, 'asking_price' => 400000, 'estimated_market_price' => 500000,
                    'discount_percentage' => 0, 'area_m2' => 50, 'bedrooms' => 1,
                    'bathrooms' => 1, 'parking_spaces' => 1, 'price_per_m2' => 8000,
                    'score' => 50, 'signal' => 'Atenção',
                ], [
                    'id' => 'two-bed', 'title' => 'Apartamento amplo em Perdizes', 'address' => 'Perdizes',
                    'city' => 'São Paulo', 'neighborhood' => 'Perdizes', 'state' => 'SP',
                    'source' => 'OLX', 'source_key' => 'olx', 'source_url' => 'https://example.test/2',
                    'thumbnail' => null, 'asking_price' => 520000, 'estimated_market_price' => 600000,
                    'discount_percentage' => 0, 'area_m2' => 80, 'bedrooms' => 2,
                    'bathrooms' => 2, 'parking_spaces' => 2, 'price_per_m2' => 6500,
                    'score' => 70, 'signal' => 'Negociar',
                ]],
            ]);
        });

        $this->getJson('/api/ai-property-search?city=S%C3%A3o%20Paulo&neighborhood=Perdizes&state=SP&min_bedrooms=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'two-bed');
    }

    private function putJpeg(string $disk, string $path, int $r = 176, int $g = 152, int $b = 128): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD ausente');
        }
        $image = imagecreatetruecolor(96, 64);
        imagefilledrectangle($image, 0, 0, 95, 63, imagecolorallocate($image, $r, $g, $b));
        ob_start();
        imagejpeg($image, null, 80);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);
        Storage::disk($disk)->put($path, $bytes);
    }
}
