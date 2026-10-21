<?php

namespace Tests\Unit\Ai;

use App\Services\Visualization\SceneGraphFactory;
use InvalidArgumentException;
use Tests\TestCase;

class EditableSceneHistoryTest extends TestCase
{
    public function test_history_survives_json_storage_and_restores_heterogeneous_wall_colors(): void
    {
        $graph = $this->graph();
        $graph['surfaces'][0]['color'] = '#ff0000';
        $graph['surfaces'][1]['color'] = '#00ff00';
        $graph['surfaces'][2]['material'] = 'wood';
        $painted = $this->apply($graph, [['op' => 'paint_kind', 'kind' => 'wall', 'room' => 'living', 'color' => '#abcdef', 'material' => 'paint']]);
        $reopened = json_decode(json_encode($painted, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        $this->assertCount(1, $reopened['editor_history']);
        $this->assertArrayNotHasKey('editor_history', $reopened['editor_history'][0]);
        $this->assertArrayNotHasKey('photo_assets', $reopened['editor_history'][0]);
        $undone = $this->apply($reopened, [['op' => 'undo_scene']]);
        $this->assertSame($graph['surfaces'], $undone['surfaces']);
        $redone = $this->apply($undone, [['op' => 'redo_scene']]);
        $this->assertSame($painted['surfaces'], $redone['surfaces']);
    }

    public function test_history_and_redo_stacks_are_bounded_to_twelve_changes(): void
    {
        $graph = $this->graph();
        for ($i = 1; $i <= 14; $i++) {
            $graph = $this->apply($graph, [['op' => 'paint_surface', 'id' => 'living-wall-south', 'color' => sprintf('#%06x', $i)]]);
        }
        $this->assertCount(12, $graph['editor_history']);
        for ($i = 0; $i < 12; $i++) {
            $graph = $this->apply($graph, [['op' => 'undo_scene']]);
        }
        $this->assertSame('#000002', $graph['surfaces'][0]['color']);
        $this->assertCount(0, $graph['editor_history']);
        $this->assertCount(12, $graph['editor_redo']);
        $this->assertSame($graph, $this->apply($graph, [['op' => 'undo_scene']]));
        for ($i = 0; $i < 12; $i++) {
            $graph = $this->apply($graph, [['op' => 'redo_scene']]);
        }
        $this->assertSame('#00000e', $graph['surfaces'][0]['color']);
        $this->assertCount(12, $graph['editor_history']);
        $this->assertCount(0, $graph['editor_redo']);
        $this->assertSame($graph, $this->apply($graph, [['op' => 'redo_scene']]));
    }

    public function test_dimensions_and_lighting_are_undone_as_one_command_batch(): void
    {
        $graph = $this->graph();
        $changed = $this->apply($graph, [
            ['op' => 'set_room_dimensions', 'room' => 'living', 'dimensions' => ['width' => 8, 'depth' => 5, 'height' => 3]],
            ['op' => 'set_lighting', 'preset' => 'warm'],
        ]);
        $this->assertCount(1, $changed['editor_history']);
        $this->assertSame('user', $changed['room_dimensions']['living']['source']);
        $undone = $this->apply($changed, [['op' => 'undo_scene']]);
        $this->assertSame($graph['room_dimensions'], $undone['room_dimensions']);
        $this->assertSame($graph['lighting'], $undone['lighting']);
        $redone = $this->apply($undone, [['op' => 'redo_scene']]);
        $this->assertSame($changed['room_dimensions'], $redone['room_dimensions']);
        $this->assertSame($changed['lighting'], $redone['lighting']);
    }

    public function test_add_remove_and_duplicate_restore_exact_object_identity_and_transform(): void
    {
        $graph = $this->graph();
        $added = $this->apply($graph, [['op' => 'add_object', 'kind' => 'chair', 'room' => 'living']]);
        $newObject = $added['objects'][array_key_last($added['objects'])];
        $this->assertCount(count($graph['objects']) + 1, $added['objects']);
        $undone = $this->apply($added, [['op' => 'undo_scene']]);
        $this->assertSame($graph['objects'], $undone['objects']);
        $restored = $this->apply($undone, [['op' => 'redo_scene']]);
        $this->assertSame($added['objects'], $restored['objects']);
        $duplicate = $this->apply($restored, [['op' => 'duplicate', 'id' => $newObject['id']]]);
        $this->assertCount(count($added['objects']) + 1, $duplicate['objects']);
        $this->assertNotSame($newObject['id'], $duplicate['objects'][array_key_last($duplicate['objects'])]['id']);
        $withoutDuplicate = $this->apply($duplicate, [['op' => 'undo_scene']]);
        $this->assertSame($added['objects'], $withoutDuplicate['objects']);
        $withDuplicate = $this->apply($withoutDuplicate, [['op' => 'redo_scene']]);
        $this->assertSame($duplicate['objects'], $withDuplicate['objects']);
        $removed = $this->apply($withDuplicate, [['op' => 'remove_object', 'id' => $newObject['id']]]);
        $this->assertNotContains($newObject['id'], array_column($removed['objects'], 'id'));
        $undoRemoval = $this->apply($removed, [['op' => 'undo_scene']]);
        $this->assertSame($withDuplicate['objects'], $undoRemoval['objects']);
        $redoRemoval = $this->apply($undoRemoval, [['op' => 'redo_scene']]);
        $this->assertSame($removed['objects'], $redoRemoval['objects']);
    }

    public function test_new_edit_clears_redo_while_noop_preserves_it(): void
    {
        $graph = $this->graph();
        $painted = $this->apply($graph, [['op' => 'paint_surface', 'id' => 'living-wall-south', 'color' => '#123456']]);
        $undone = $this->apply($painted, [['op' => 'undo_scene']]);
        $noop = $this->apply($undone, [['op' => 'paint_surface', 'id' => 'living-wall-south', 'color' => $graph['surfaces'][0]['color']]]);
        $this->assertSame($undone, $noop);
        $edited = $this->apply($noop, [['op' => 'set_lighting', 'preset' => 'evening']]);
        $this->assertSame([], $edited['editor_redo']);
        $this->assertCount(1, $edited['editor_history']);
        $this->assertSame($edited, $this->apply($edited, [['op' => 'redo_scene']]));
    }

    public function test_reconstruction_and_parametric_history_do_not_restore_each_others_state(): void
    {
        $graph = $this->apply($this->graph(), [['op' => 'paint_surface', 'id' => 'living-wall-south', 'color' => '#123456']]);
        $neural = $this->apply($graph, [['op' => 'set_reconstruction_material', 'id' => 'geometry-wall_candidate', 'color' => '#fedcba']]);
        $this->assertSame($graph['editor_history'], $neural['editor_history']);
        $this->assertArrayNotHasKey('reconstruction_overrides', $neural['editor_history'][0]);
        $sceneUndo = $this->apply($neural, [['op' => 'undo_scene']]);
        $this->assertSame($neural['reconstruction_overrides'], $sceneUndo['reconstruction_overrides']);
        $this->assertSame($neural['reconstruction_history'], $sceneUndo['reconstruction_history']);
        $reconstructionUndo = $this->apply($sceneUndo, [['op' => 'undo_reconstruction']]);
        $this->assertSame($sceneUndo['surfaces'], $reconstructionUndo['surfaces']);
        $this->assertSame($sceneUndo['editor_redo'], $reconstructionUndo['editor_redo']);
        $sceneRedo = $this->apply($reconstructionUndo, [['op' => 'redo_scene']]);
        $this->assertSame($reconstructionUndo['reconstruction_overrides'], $sceneRedo['reconstruction_overrides']);
        $this->assertSame($reconstructionUndo['reconstruction_redo'], $sceneRedo['reconstruction_redo']);
        $this->assertSame($graph['surfaces'], $sceneRedo['surfaces']);
    }

    public function test_history_operation_cannot_be_mixed_with_mutations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->apply($this->graph(), [['op' => 'undo_scene'], ['op' => 'set_lighting', 'preset' => 'warm']]);
    }

    public function test_undo_restores_absence_of_new_fields_in_legacy_saved_graphs(): void
    {
        $graph = $this->graph();
        unset($graph['project_notes'], $graph['room_dimensions']);
        $updated = $this->apply($graph, [
            ['op' => 'set_project_brief', 'category' => 'presentation', 'brief' => 'Proposta ilustrativa.'],
            ['op' => 'set_room_dimensions', 'room' => 'living', 'dimensions' => ['width' => 6, 'depth' => 4, 'height' => 3]],
        ]);
        $undone = $this->apply($updated, [['op' => 'undo_scene']]);
        $this->assertArrayNotHasKey('project_notes', $undone);
        $this->assertArrayNotHasKey('room_dimensions', $undone);
        $redone = $this->apply($undone, [['op' => 'redo_scene']]);
        $this->assertSame($updated['project_notes'], $redone['project_notes']);
        $this->assertSame($updated['room_dimensions'], $redone['room_dimensions']);
    }

    private function apply(array $graph, array $actions): array
    {
        return app(SceneGraphFactory::class)->apply($graph, $actions);
    }

    private function graph(): array
    {
        return app(SceneGraphFactory::class)->fromBrief(['ambientes' => [[
            'nome' => 'living', 'rotulo' => 'Sala', 'elementos_visiveis' => ['sofá'],
            'palette' => [['hex' => '#eeeeee'], ['hex' => '#aaaaaa']],
        ]]], [], ['title' => 'Histórico de teste']);
    }
}
