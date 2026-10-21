<?php

namespace Tests\Unit\Ai;

use App\Services\Visualization\SceneGraphFactory;
use InvalidArgumentException;
use Tests\TestCase;

class SceneOpeningTest extends TestCase
{
    private function room(): array
    {
        return app(SceneGraphFactory::class)->addRoom([], 'living', ['width' => 4.0, 'depth' => 5.0, 'height' => 2.8], false);
    }

    private function opening(array $graph, array $overrides = []): array
    {
        return array_replace(['op' => 'add_opening', 'room' => $graph['active_room'], 'side' => 'south', 'kind' => 'window', 'width' => 1.2, 'height' => 1.0, 'offset' => 0.0, 'sill' => 1.0], $overrides);
    }

    public function test_opening_is_persisted_with_identity_and_exact_dimensions(): void
    {
        $factory = app(SceneGraphFactory::class);
        $graph = $this->room();
        $result = $factory->apply($graph, [$this->opening($graph)]);
        $this->assertCount(1, $result['openings'] ?? []);
        $this->assertSame('user', $result['openings'][0]['origin']);
        $this->assertSame(1.2, $result['openings'][0]['width']);
        $this->assertStringStartsWith('opening-', $result['openings'][0]['id']);
    }

    public function test_opening_cannot_exceed_the_wall_boundary(): void
    {
        $graph = $this->room();
        $this->expectException(InvalidArgumentException::class);
        app(SceneGraphFactory::class)->apply($graph, [$this->opening($graph, ['offset' => 1.5])]);
    }

    public function test_fit_uses_the_correct_span_for_all_four_walls(): void
    {
        $factory = app(SceneGraphFactory::class);
        $graph = $this->room();
        foreach (['north' => 3.8, 'south' => 3.8, 'west' => 4.8, 'east' => 4.8] as $side => $width) {
            $result = $factory->apply($graph, [$this->opening($graph, ['side' => $side, 'width' => $width, 'height' => 2.6, 'sill' => 0.1])]);
            $this->assertSame($width, $result['openings'][0]['width']);
        }
    }

    public function test_invalid_parameters_and_unmeasured_or_absent_walls_are_rejected(): void
    {
        $factory = app(SceneGraphFactory::class);
        $graph = $this->room();
        foreach ([['width' => 0.29], ['height' => INF], ['offset' => NAN], ['sill' => -0.1], ['kind' => 'door', 'sill' => 0.1], ['sill' => 0.0], ['side' => 'ceiling'], ['kind' => 'arch'], ['room' => 'missing'], ['height' => 2.5, 'sill' => 0.5]] as $overrides) {
            try {
                $factory->apply($graph, [$this->opening($graph, $overrides)]);
                $this->fail('Invalid opening accepted: '.implode(',', array_keys($overrides)));
            } catch (InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
        $graph['room_dimensions'][$graph['active_room']]['source'] = 'estimated';
        try {
            $factory->apply($graph, [$this->opening($graph)]);
            $this->fail('Estimated room was treated as measured.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('medidas', $exception->getMessage());
        }
        $graph['room_dimensions'][$graph['active_room']]['source'] = 'user';
        $graph['surfaces'] = array_values(array_filter($graph['surfaces'], fn ($surface) => $surface['kind'] !== 'wall'));
        $this->expectException(InvalidArgumentException::class);
        $factory->apply($graph, [$this->opening($graph)]);
    }

    public function test_gap_allows_horizontal_or_vertical_separation_but_rejects_touching(): void
    {
        $factory = app(SceneGraphFactory::class);
        $graph = $this->room();
        $first = $this->opening($graph, ['width' => 0.5, 'height' => 0.5, 'offset' => -0.5, 'sill' => 0.2]);
        $base = $factory->apply($graph, [$first]);
        $horizontal = $factory->apply($base, [$this->opening($graph, ['width' => 0.5, 'height' => 0.5, 'offset' => 0.05, 'sill' => 0.2])]);
        $vertical = $factory->apply($base, [$this->opening($graph, ['width' => 0.5, 'height' => 0.5, 'offset' => -0.5, 'sill' => 0.75])]);
        $this->assertCount(2, $horizontal['openings']);
        $this->assertCount(2, $vertical['openings']);
        $this->expectException(InvalidArgumentException::class);
        $factory->apply($base, [$this->opening($graph, ['width' => 0.5, 'height' => 0.5, 'offset' => 0.0, 'sill' => 0.2])]);
    }

    public function test_update_and_delete_keep_identity_and_exact_undo_redo(): void
    {
        $factory = app(SceneGraphFactory::class);
        $graph = $this->room();
        $graph = $factory->apply($graph, [$this->opening($graph)]);
        $old = $graph['openings'][0];
        $updated = $factory->apply($graph, [$this->opening($graph, ['op' => 'update_opening', 'id' => $old['id'], 'kind' => 'door', 'side' => 'east', 'height' => 2.1, 'sill' => 0.0, 'origin' => 'measured_photo'])]);
        $this->assertSame($old['id'], $updated['openings'][0]['id']);
        $this->assertSame('user', $updated['openings'][0]['origin']);
        $undo = $factory->apply($updated, [['op' => 'undo_scene']]);
        $this->assertSame([$old], $undo['openings']);
        $redo = $factory->apply($undo, [['op' => 'redo_scene']]);
        $this->assertSame($updated['openings'], $redo['openings']);
        $removed = $factory->apply($redo, [['op' => 'remove_opening', 'id' => $old['id']]]);
        $this->assertSame([], $removed['openings']);
        $this->assertSame($redo['openings'], $factory->apply($removed, [['op' => 'undo_scene']])['openings']);
        $this->expectException(InvalidArgumentException::class);
        $factory->apply($graph, [['op' => 'update_opening', 'id' => $old['id'], 'offset' => 0.5]]);
    }

    public function test_resize_cannot_truncate_openings_and_legacy_history_defaults_to_empty(): void
    {
        $factory = app(SceneGraphFactory::class);
        $graph = $this->room();
        unset($graph['openings']);
        $graph['editor_history'] = [array_diff_key($graph, array_flip(['editor_history', 'editor_redo']))];
        $opened = $factory->apply($graph, [$this->opening($graph, ['width' => 3.5])]);
        $legacyUndo = $factory->apply($opened, [['op' => 'undo_scene']]);
        $this->assertSame([], $legacyUndo['openings']);
        $this->assertSame($opened['openings'], $factory->apply($legacyUndo, [['op' => 'redo_scene']])['openings']);
        $resized = $factory->apply($opened, [['op' => 'set_room_dimensions', 'room' => $graph['active_room'], 'dimensions' => ['width' => 5, 'depth' => 5, 'height' => 2.8]]]);
        $this->assertSame($opened['openings'], $resized['openings']);
        $this->expectException(InvalidArgumentException::class);
        $factory->apply($opened, [['op' => 'set_room_dimensions', 'room' => $graph['active_room'], 'dimensions' => ['width' => 3, 'depth' => 5, 'height' => 2.8]]]);
    }

    public function test_limits_are_enforced_per_room_and_project(): void
    {
        $factory = app(SceneGraphFactory::class);
        $graph = [];
        for ($roomIndex = 0; $roomIndex < 4; $roomIndex++) {
            $graph = $factory->addRoom($graph, 'living', ['width' => 4.0, 'depth' => 5.0, 'height' => 2.8], false);
            $actions = [];
            foreach (['south', 'north', 'west', 'east'] as $side) {
                for ($index = 0; $index < 6; $index++) {
                    $actions[] = $this->opening($graph, ['side' => $side, 'width' => 0.3, 'height' => 0.3, 'offset' => -0.875 + $index * 0.35, 'sill' => 0.1]);
                }
            }
            $graph = $factory->apply($graph, $actions);
            if ($roomIndex === 0) {
                try {
                    $factory->apply($graph, [$this->opening($graph, ['width' => 0.3, 'height' => 0.3, 'offset' => 1.4, 'sill' => 1.0])]);
                    $this->fail('Room limit not enforced');
                } catch (InvalidArgumentException $exception) {
                    $this->assertStringContainsString('24', $exception->getMessage());
                }
            }
        }
        $this->assertCount(96, $graph['openings']);
        $graph = $factory->addRoom($graph, 'living', ['width' => 4.0, 'depth' => 5.0, 'height' => 2.8], false);
        $this->expectExceptionMessage('96');
        $factory->apply($graph, [$this->opening($graph)]);
    }
}
