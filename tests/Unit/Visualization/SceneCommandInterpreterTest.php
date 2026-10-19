<?php

namespace Tests\Unit\Visualization;

use App\Services\Visualization\SceneCommandInterpreter;
use App\Services\Visualization\SceneGraphFactory;
use Tests\TestCase;

class SceneCommandInterpreterTest extends TestCase
{
    public function test_wall_command_paints_active_room_only(): void
    {
        $factory = new SceneGraphFactory;
        $graph = $factory->fromBrief([
            'ambientes' => [
                ['nome' => 'living', 'rotulo' => 'Sala', 'imagens_referencia' => [1], 'elementos_visiveis' => ['sofa'], 'palette' => [['hex' => '#aabbcc']]],
                ['nome' => 'bedroom', 'rotulo' => 'Quarto', 'imagens_referencia' => [2], 'elementos_visiveis' => ['cama'], 'palette' => [['hex' => '#112233']]],
            ],
            'fachada_disponivel' => false,
        ], [], ['title' => 'QA', 'property_type' => 'apartment']);
        $parsed = (new SceneCommandInterpreter)->interpret('Quero paredes bege', $graph);
        $this->assertTrue($parsed['ok']);
        $updated = $factory->apply($graph, $parsed['actions']);
        $living = collect($updated['surfaces'])->first(fn (array $row) => $row['kind'] === 'wall' && $row['room'] === 'living');
        $bedroom = collect($updated['surfaces'])->first(fn (array $row) => $row['kind'] === 'wall' && $row['room'] === 'bedroom');
        $this->assertSame('#d9cbb8', $living['color']);
        $this->assertSame('#112233', $bedroom['color']);
    }

    public function test_admin_shaped_command_is_rejected(): void
    {
        $parsed = (new SceneCommandInterpreter)->interpret('rm -rf / && https://evil.test', []);
        $this->assertFalse($parsed['ok']);
        $this->assertSame([], $parsed['actions']);
    }

    public function test_floor_command_sets_porcelain(): void
    {
        $graph = ['active_room' => 'living', 'surfaces' => [['id' => 'living-floor', 'kind' => 'floor', 'room' => 'living', 'color' => '#cbb79a', 'material' => 'ceramic']], 'objects' => [], 'rooms' => [['key' => 'living']]];
        $parsed = (new SceneCommandInterpreter)->interpret('Troque o piso por porcelanato branco', $graph);
        $this->assertTrue($parsed['ok']);
        $this->assertSame('porcelain', $parsed['actions'][0]['material']);
        $this->assertSame('#f4f1ea', $parsed['actions'][0]['color']);
    }

    public function test_blue_walls_command(): void
    {
        $parsed = (new SceneCommandInterpreter)->interpret('Quero paredes azuis', ['active_room' => 'living']);
        $this->assertTrue($parsed['ok']);
        $this->assertSame('#9bb7d4', $parsed['actions'][0]['color']);
    }
}
