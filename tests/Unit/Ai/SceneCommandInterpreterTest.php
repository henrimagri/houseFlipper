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
        $command = app(SceneCommandInterpreter::class)->interpret('Pinte apenas a parede atrás do sofá de azul', $graph);

        $this->assertTrue($command['ok']);
        $this->assertSame('paint_surface', $command['actions'][0]['op']);
        $this->assertSame('living-wall-south', $command['actions'][0]['id']);

        $updated = app(SceneGraphFactory::class)->apply($graph, $command['actions']);
        $this->assertSame('#9bb7d4', collect($updated['surfaces'])->firstWhere('id', 'living-wall-south')['color']);
        $this->assertSame('#eeeeee', collect($updated['surfaces'])->firstWhere('id', 'living-wall-north')['color']);
    }

    public function test_wall_behind_sofa_supports_legacy_single_wall_graphs(): void
    {
        $graph = [
            'active_room' => 'living',
            'surfaces' => [['id' => 'living-wall', 'kind' => 'wall', 'room' => 'living', 'color' => '#eeeeee']],
        ];

        $command = app(SceneCommandInterpreter::class)->interpret('Pinte apenas a parede atrás do sofá de azul', $graph);

        $this->assertTrue($command['ok']);
        $this->assertSame('living-wall', $command['actions'][0]['id']);
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
