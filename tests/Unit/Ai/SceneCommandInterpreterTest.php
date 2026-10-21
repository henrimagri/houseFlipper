<?php

namespace Tests\Unit\Ai;

use App\Services\Visualization\SceneCommandInterpreter;
use App\Services\Visualization\SceneGraphFactory;
use Tests\TestCase;

class SceneCommandInterpreterTest extends TestCase
{
    public function test_lighting_command_changes_the_persistable_scene_graph(): void
    {
        $graph = $this->graph();
        $command = app(SceneCommandInterpreter::class)->interpret('Deixe a iluminação mais quente', $graph);

        $this->assertTrue($command['ok']);
        $this->assertSame('set_lighting', $command['actions'][0]['op']);
        $this->assertSame('warm', $command['actions'][0]['preset']);

        $updated = app(SceneGraphFactory::class)->apply($graph, $command['actions']);
        $this->assertSame('warm', $updated['lighting']['preset']);
        $this->assertSame('#ffd6a3', $updated['lighting']['color']);
    }

    public function test_selected_object_is_moved_rotated_and_scaled_with_bounded_actions(): void
    {
        $graph = $this->graph();
        $id = $graph['objects'][0]['id'];
        $command = app(SceneCommandInterpreter::class)->interpret(
            'Mova para a direita, gire e aumente este sofá',
            $graph,
            $id,
        );

        $this->assertTrue($command['ok']);
        $action = $command['actions'][0];
        $this->assertSame('transform', $action['op']);
        $this->assertSame($id, $action['id']);
        $this->assertSame(15.0, $action['rotation']);
        $this->assertSame(1.1, $action['scale']);

        $updated = app(SceneGraphFactory::class)->apply($graph, [$action]);
        $this->assertSame(-0.6, $updated['objects'][0]['position'][0]);
    }

    public function test_transform_command_requires_an_existing_selected_object(): void
    {
        $command = app(SceneCommandInterpreter::class)->interpret('Gire o móvel', $this->graph());

        $this->assertFalse($command['ok']);
        $this->assertSame([], $command['actions']);
    }

    public function test_wall_behind_sofa_is_painted_without_changing_other_walls(): void
    {
        $graph = $this->graph();
        $command = app(SceneCommandInterpreter::class)->interpret('Pinte apenas a parede atrás do sofá de azul', $graph, 'living-wall-north');

        $this->assertTrue($command['ok']);
        $this->assertSame('paint_surface', $command['actions'][0]['op']);
        $this->assertSame('living-wall-north', $command['actions'][0]['id']);

        $updated = app(SceneGraphFactory::class)->apply($graph, $command['actions']);
        $this->assertSame('#9bb7d4', collect($updated['surfaces'])->firstWhere('id', 'living-wall-north')['color']);
        $this->assertSame('#eeeeee', collect($updated['surfaces'])->firstWhere('id', 'living-wall-south')['color']);
    }

    public function test_wall_behind_sofa_requires_visual_selection_even_in_legacy_graphs(): void
    {
        $graph = [
            'active_room' => 'living',
            'surfaces' => [['id' => 'living-wall', 'kind' => 'wall', 'room' => 'living', 'color' => '#eeeeee']],
        ];

        $command = app(SceneCommandInterpreter::class)->interpret('Pinte apenas a parede atrás do sofá de azul', $graph);

        $this->assertFalse($command['ok']);
        $this->assertTrue($command['requires_confirmation']);
        $this->assertSame([], $command['actions']);
    }

    public function test_replacing_an_important_object_requests_selection_confirmation(): void
    {
        $command = app(SceneCommandInterpreter::class)->interpret('Substitua o sofá por um modelo moderno', $this->graph());

        $this->assertFalse($command['ok']);
        $this->assertTrue($command['requires_confirmation']);
        $this->assertSame([], $command['actions']);
    }

    public function test_user_room_dimensions_are_bounded_and_persisted(): void
    {
        $updated = app(SceneGraphFactory::class)->apply($this->graph(), [[
            'op' => 'set_room_dimensions',
            'room' => 'living',
            'dimensions' => ['width' => 6.4, 'depth' => 4.8, 'height' => 2.9],
        ]]);

        $this->assertSame(['width' => 6.4, 'depth' => 4.8, 'height' => 2.9, 'source' => 'user'], $updated['room_dimensions']['living']);
    }

    public function test_cost_estimate_uses_room_area_and_is_persisted_as_structured_note(): void
    {
        $graph = $this->graph();
        $graph['room_dimensions']['living'] = ['width' => 6, 'depth' => 4, 'height' => 2.8, 'source' => 'user'];
        $command = app(SceneCommandInterpreter::class)->interpret('Faça uma estimativa de custo considerando R$ 500,00 por m²', $graph);

        $this->assertTrue($command['ok']);
        $this->assertSame('set_project_brief', $command['actions'][0]['op']);
        $this->assertStringContainsString('24,00 m²', $command['message']);
        $this->assertStringContainsString('12.000,00', $command['message']);
        $this->assertStringContainsString('informado por você', $command['message']);
        $updated = app(SceneGraphFactory::class)->apply($graph, $command['actions']);
        $this->assertSame('cost_estimate', $updated['project_notes'][0]['category']);
        $this->assertSame('structured_ai_action', $updated['project_notes'][0]['source']);
    }

    public function test_scandinavian_style_is_a_bounded_persistable_action_set(): void
    {
        $command = app(SceneCommandInterpreter::class)->interpret('Aplique um estilo escandinavo', $this->graph());

        $this->assertTrue($command['ok']);
        $this->assertSame(['paint_kind', 'paint_kind', 'set_lighting'], array_column($command['actions'], 'op'));
        $updated = app(SceneGraphFactory::class)->apply($this->graph(), $command['actions']);
        $this->assertSame('soft', $updated['lighting']['preset']);
    }

    public function test_reconstruction_material_changes_only_selected_region_and_can_be_undone(): void
    {
        $graph = $this->graph();
        $selected = 'geometry-wall_candidate';
        $command = app(SceneCommandInterpreter::class)->interpret('Pinte esta parede de azul', $graph, $selected);

        $this->assertTrue($command['ok']);
        $this->assertSame('set_reconstruction_material', $command['actions'][0]['op']);
        $updated = app(SceneGraphFactory::class)->apply($graph, $command['actions']);
        $this->assertSame('#9bb7d4', $updated['reconstruction_overrides'][$selected]['color']);
        $this->assertCount(1, $updated['reconstruction_overrides']);

        $undo = app(SceneCommandInterpreter::class)->interpret('Desfaça a última alteração', $updated, $selected);
        $restored = app(SceneGraphFactory::class)->apply($updated, $undo['actions']);
        $this->assertArrayNotHasKey($selected, $restored['reconstruction_overrides']);

        $redo = app(SceneCommandInterpreter::class)->interpret('Refaça a última alteração', $restored, $selected);
        $reapplied = app(SceneGraphFactory::class)->apply($restored, $redo['actions']);
        $this->assertSame('#9bb7d4', $reapplied['reconstruction_overrides'][$selected]['color']);
    }

    public function test_wall_command_rejects_selected_ceiling_candidate(): void
    {
        $command = app(SceneCommandInterpreter::class)->interpret(
            'Pinte esta parede de azul',
            $this->graph(),
            'geometry-ceiling_candidate',
        );

        $this->assertFalse($command['ok']);
        $this->assertTrue($command['requires_confirmation']);
        $this->assertSame([], $command['actions']);
        $this->assertStringContainsString('teto candidato', $command['message']);
    }

    public function test_ceiling_and_generic_surface_commands_accept_selected_ceiling(): void
    {
        $interpreter = app(SceneCommandInterpreter::class);
        $ceiling = $interpreter->interpret('Pinte este teto de azul', $this->graph(), 'geometry-ceiling_candidate');
        $generic = $interpreter->interpret('Pinte esta superfície de azul', $this->graph(), 'geometry-ceiling_candidate');

        $this->assertTrue($ceiling['ok']);
        $this->assertSame('ceiling_candidate', $ceiling['actions'][0]['expected_kind']);
        $this->assertTrue($generic['ok']);
        $this->assertNull($generic['actions'][0]['expected_kind']);
    }

    public function test_backend_revalidates_reconstruction_surface_kind(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(SceneGraphFactory::class)->apply($this->graph(), [[
            'op' => 'set_reconstruction_material',
            'id' => 'geometry-ceiling_candidate',
            'expected_kind' => 'wall_candidate',
            'color' => '#9bb7d4',
        ]]);
    }

    public function test_singular_wall_requires_selection_and_explicit_color(): void
    {
        $interpreter = app(SceneCommandInterpreter::class);
        foreach ([['Pinte esta parede de azul', null], ['Pinte esta parede', 'living-wall-north']] as [$text, $selection]) {
            $result = $interpreter->interpret($text, $this->graph(), $selection);
            $this->assertFalse($result['ok']);
            $this->assertTrue($result['requires_confirmation']);
            $this->assertSame([], $result['actions']);
        }
        $graph = $this->graph();
        $result = $interpreter->interpret('Pinte esta parede de azul', $graph, 'living-wall-north');
        $updated = app(SceneGraphFactory::class)->apply($graph, $result['actions']);
        $this->assertSame('#9bb7d4', collect($updated['surfaces'])->firstWhere('id', 'living-wall-north')['color']);
        $this->assertSame('#eeeeee', collect($updated['surfaces'])->firstWhere('id', 'living-wall-south')['color']);
    }

    public function test_selection_mismatch_never_changes_another_surface_or_room(): void
    {
        $graph = $this->graph();
        $graph['surfaces'][] = ['id' => 'bedroom-wall', 'kind' => 'wall', 'room' => 'bedroom', 'color' => '#123456'];
        foreach (['living-floor', 'bedroom-wall', 'missing-wall'] as $id) {
            $result = app(SceneCommandInterpreter::class)->interpret('Pinte esta parede de azul', $graph, $id);
            $this->assertFalse($result['ok']);
            $this->assertSame([], $result['actions']);
        }
        foreach (['Pinte todas as paredes de azul', 'Aplique um estilo escandinavo', 'Aplique um estilo moderno', 'Aplique um estilo minimalista', 'Aplique um estilo de luxo'] as $text) {
            $result = app(SceneCommandInterpreter::class)->interpret($text, $graph);
            $updated = app(SceneGraphFactory::class)->apply($graph, $result['actions']);
            $this->assertSame('#123456', collect($updated['surfaces'])->firstWhere('id', 'bedroom-wall')['color']);
        }
    }

    public function test_cost_estimates_do_not_invent_prices_or_dimensions(): void
    {
        $graph = $this->graph();
        $graph['room_dimensions']['living'] = ['width' => 6, 'depth' => 4, 'source' => 'user'];
        $missingPrice = app(SceneCommandInterpreter::class)->interpret('Faça uma estimativa de custo', $graph);
        $missingDimensions = app(SceneCommandInterpreter::class)->interpret('Faça uma estimativa de custo com R$ 500 por m²', $this->graph());
        foreach ([$missingPrice, $missingDimensions] as $result) {
            $this->assertFalse($result['ok']);
            $this->assertTrue($result['requires_confirmation']);
            $this->assertSame([], $result['actions']);
        }
    }

    public function test_generic_undo_does_not_claim_or_change_reconstruction_history(): void
    {
        $graph = app(SceneGraphFactory::class)->apply($this->graph(), [['op' => 'set_reconstruction_material', 'id' => 'geometry-wall_candidate', 'color' => '#123456']]);
        foreach (['Desfaça a última alteração', 'Refaça a última alteração'] as $text) {
            $result = app(SceneCommandInterpreter::class)->interpret($text, $graph, 'living-wall-north');
            $this->assertFalse($result['ok']);
            $this->assertSame([], $result['actions']);
        }
        $empty = app(SceneCommandInterpreter::class)->interpret('Desfaça a última alteração', $this->graph(), 'geometry-wall_candidate');
        $this->assertFalse($empty['ok']);
    }

    public function test_scene_undo_and_redo_restore_the_parametric_scene_without_changing_reconstruction(): void
    {
        $factory = app(SceneGraphFactory::class);
        $interpreter = app(SceneCommandInterpreter::class);
        $graph = $factory->apply($this->graph(), [['op' => 'set_reconstruction_material', 'id' => 'geometry-wall_candidate', 'color' => '#123456']]);
        $paint = $interpreter->interpret('Pinte esta parede de azul', $graph, 'living-wall-north');
        $painted = $factory->apply($graph, $paint['actions']);
        $undo = $interpreter->interpret('Desfaça a última alteração', $painted, 'living-wall-north');
        $this->assertSame('undo_scene', $undo['actions'][0]['op']);
        $restored = $factory->apply($painted, $undo['actions']);
        $this->assertSame('#eeeeee', collect($restored['surfaces'])->firstWhere('id', 'living-wall-north')['color']);
        $this->assertSame($graph['reconstruction_overrides'], $restored['reconstruction_overrides']);
        $redo = $interpreter->interpret('Refaça a última alteração', $restored);
        $this->assertSame('redo_scene', $redo['actions'][0]['op']);
        $reapplied = $factory->apply($restored, $redo['actions']);
        $this->assertSame('#9bb7d4', collect($reapplied['surfaces'])->firstWhere('id', 'living-wall-north')['color']);
        $this->assertSame($graph['reconstruction_overrides'], $reapplied['reconstruction_overrides']);
    }

    public function test_selection_only_and_unspecified_material_commands_do_not_mutate_the_graph(): void
    {
        foreach ([['Selecione apenas a parede atrás do sofá', 'living-wall-north'], ['Troque o material do piso', 'geometry-floor_candidate'], ['Troque o material do piso', 'living-floor']] as [$text, $selection]) {
            $result = app(SceneCommandInterpreter::class)->interpret($text, $this->graph(), $selection);
            $this->assertFalse($result['ok']);
            $this->assertTrue($result['requires_confirmation']);
            $this->assertSame([], $result['actions']);
        }
    }

    private function graph(): array
    {
        return app(SceneGraphFactory::class)->fromBrief([
            'ambientes' => [[
                'nome' => 'living',
                'rotulo' => 'Sala',
                'elementos_visiveis' => ['sofá'],
                'palette' => [['hex' => '#eeeeee'], ['hex' => '#aaaaaa']],
            ]],
        ], [], ['title' => 'Imóvel de teste']);
    }
}
