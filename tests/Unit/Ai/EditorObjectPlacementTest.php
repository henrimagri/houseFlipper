<?php

namespace Tests\Unit\Ai;

use App\Services\Visualization\SceneGraphFactory;
use InvalidArgumentException;
use Tests\TestCase;

class EditorObjectPlacementTest extends TestCase
{
    public function test_other_rooms_do_not_displace_new_catalog_objects(): void
    {
        $factory = app(SceneGraphFactory::class);
        $graph = $factory->addRoom([], 'living', ['width' => 4, 'depth' => 5, 'height' => 2.8], false);
        $target = $graph['active_room'];
        $withoutOthers = $factory->apply($graph, [['op' => 'add_object', 'room' => $target, 'kind' => 'chair']]);
        $graph = $factory->addRoom($graph, 'office', ['width' => 6, 'depth' => 6, 'height' => 2.8], false);
        $other = $graph['active_room'];
        for ($i = 0; $i < 30; $i++) {
            $graph = $factory->apply($graph, [['op' => 'add_object', 'room' => $other, 'kind' => 'chair']]);
        }
        $existing = $graph['objects'];
        $result = $factory->apply($graph, [['op' => 'add_object', 'room' => $target, 'kind' => 'chair']]);
        $this->assertSame($withoutOthers['objects'][0]['position'], $result['objects'][30]['position']);
        $this->assertSame($existing, array_slice($result['objects'], 0, 30));
    }

    public function test_many_new_objects_remain_inside_room_with_margin(): void
    {
        $factory = app(SceneGraphFactory::class);
        $graph = $factory->addRoom([], 'living', ['width' => 4, 'depth' => 5, 'height' => 2.8], false);
        for ($i = 0; $i < 24; $i++) {
            $graph = $factory->apply($graph, [['op' => 'add_object', 'room' => $graph['active_room'], 'kind' => 'chair']]);
            $position = $graph['objects'][$i]['position'];
            $this->assertLessThanOrEqual(1.5, abs($position[0]));
            $this->assertLessThanOrEqual(2.0, abs($position[2]));
            $this->assertSame(0, $position[1]);
        }
    }

    public function test_invalid_room_is_rejected_but_photo_evidence_placement_is_unchanged(): void
    {
        $factory = app(SceneGraphFactory::class);
        $graph = $factory->fromBrief(['ambientes' => [['nome' => 'living', 'elementos_visiveis' => ['cadeira', 'mesa']]]], [], []);
        $this->assertSame([-0.85, 0, 0.55], $graph['objects'][0]['position']);
        $this->assertSame([0.0, 0, 0.55], $graph['objects'][1]['position']);
        $this->expectException(InvalidArgumentException::class);
        $factory->apply($graph, [['op' => 'add_object', 'room' => 'missing', 'kind' => 'chair']]);
    }
}
