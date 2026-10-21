<?php

namespace Tests\Unit\Ai;

use App\Services\Visualization\NeuralVisionEvidenceMapper;
use App\Services\Visualization\SceneBriefBuilder;
use App\Services\Visualization\SceneGraphFactory;
use Tests\TestCase;

class VisionEvidenceMapperTest extends TestCase
{
    public function test_no_objects_means_unclassified_instead_of_zero_percent(): void
    {
        $mapped = app(NeuralVisionEvidenceMapper::class)->map([
            'detection' => ['model' => 'DETR test', 'objects' => []],
        ]);

        $this->assertNull($mapped['detection_confidence']);
        $this->assertNull($mapped['room_guess']);
        $this->assertNull($mapped['room_confidence']);
        $this->assertSame('unclassified', $mapped['room_provenance']['classification_status']);
        $this->assertFalse($mapped['room_provenance']['is_neural_room_classifier']);
    }

    public function test_detection_confidence_is_distinct_from_explainable_room_inference(): void
    {
        $mapped = app(NeuralVisionEvidenceMapper::class)->map($this->workerResult([
            ['label' => 'couch', 'confidence' => 0.98, 'box_xyxy' => [480, 240, 900, 580]],
            ['label' => 'potted plant', 'confidence' => 0.81, 'box_xyxy' => [80, 170, 250, 570]],
        ]));

        $this->assertSame(98, $mapped['detection_confidence']);
        $this->assertSame('living', $mapped['room_guess']);
        $this->assertNotSame($mapped['detection_confidence'], $mapped['room_confidence']);
        $this->assertSame('object_evidence_rules_v1', $mapped['room_provenance']['method']);
        $this->assertFalse($mapped['room_provenance']['is_neural_room_classifier']);
        $this->assertSame('sofa', $mapped['elements'][0]['parametric_kind']);
        $this->assertSame([0.5, 0.375, 0.9375, 0.90625], $mapped['elements'][0]['box_normalized']);
    }

    public function test_ambiguous_chair_detection_remains_unclassified(): void
    {
        $mapped = app(NeuralVisionEvidenceMapper::class)->map($this->workerResult([
            ['label' => 'chair', 'confidence' => 0.99, 'box_xyxy' => [10, 10, 100, 200]],
        ]));

        $this->assertSame(99, $mapped['detection_confidence']);
        $this->assertNull($mapped['room_guess']);
        $this->assertNull($mapped['room_confidence']);
    }

    public function test_living_room_with_dining_area_uses_unique_object_evidence_without_duplicate_inflation(): void
    {
        $mapped = (new NeuralVisionEvidenceMapper)->map([
            'detection' => [
                'model' => 'facebook/detr-resnet-50',
                'objects' => [
                    ['label' => 'couch', 'confidence' => 0.997744],
                    ['label' => 'dining table', 'confidence' => 0.95258],
                    ['label' => 'chair', 'confidence' => 0.949308],
                    ['label' => 'chair', 'confidence' => 0.997965],
                    ['label' => 'potted plant', 'confidence' => 0.991229],
                    ['label' => 'potted plant', 'confidence' => 0.997876],
                ],
            ],
        ]);

        $this->assertSame('living', $mapped['room_guess']);
        $this->assertGreaterThanOrEqual(50, $mapped['room_confidence']);
        $this->assertSame('classified', $mapped['room_provenance']['classification_status']);
    }

    public function test_coco_objects_and_boxes_become_approximate_parametric_scene_objects(): void
    {
        $mapped = app(NeuralVisionEvidenceMapper::class)->map($this->workerResult([
            ['label' => 'dining table', 'confidence' => 0.94, 'box_xyxy' => [260, 300, 750, 610]],
            ['label' => 'chair', 'confidence' => 0.89, 'box_xyxy' => [180, 310, 320, 620]],
        ]));
        $brief = app(SceneBriefBuilder::class)->build([[
            'room_guess' => $mapped['room_guess'],
            'room_confidence' => $mapped['room_confidence'],
            'detection_confidence' => $mapped['detection_confidence'],
            'room_provenance' => $mapped['room_provenance'],
            'elements' => $mapped['elements'],
            'asset_id' => 10,
        ]], ['property_type' => 'apartment'], [['id' => 10, 'kind' => 'photo']]);
        $graph = app(SceneGraphFactory::class)->fromBrief($brief, [], ['title' => 'Teste']);

        $this->assertSame('dining', $graph['active_room']);
        $this->assertSame(['table', 'chair'], array_column($graph['objects'], 'kind'));
        $this->assertTrue($graph['objects'][0]['approximate_from_2d']);
        $this->assertSame([260.0, 300.0, 750.0, 610.0], $graph['objects'][0]['bounding_box']);
        $this->assertSame('worker_detection', $graph['objects'][0]['provenance']['source']);
    }

    private function workerResult(array $objects): array
    {
        return [
            'detection' => ['model' => 'DETR test', 'objects' => $objects],
            'segmentation' => ['dimensions' => ['width' => 960, 'height' => 640]],
        ];
    }
}
