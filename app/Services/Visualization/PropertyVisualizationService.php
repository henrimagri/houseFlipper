<?php

namespace App\Services\Visualization;

use App\Exceptions\DocumentRejectedException;
use App\Exceptions\DocumentStorageException;
use App\Models\Opportunity;
use App\Models\PropertyPhotoAnalysis;
use App\Models\PropertyProcessingJob;
use App\Models\PropertySceneVersion;
use App\Models\PropertyVisualization;
use App\Models\PropertyVisualizationAsset;
use App\Models\Propriedade;
use App\Models\User;
use App\Services\Ai\AiWorkerClient;
use App\Services\Ai\ProductEntitlement;
use App\Services\DocumentMalwareScanner;
use App\Support\OpportunityPhotoPathPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyVisualizationService
{
    public function __construct(
        private readonly DocumentMalwareScanner $scanner,
        private readonly ProductEntitlement $entitlement,
        private readonly AiWorkerClient $worker,
        private readonly NeuralVisionEvidenceMapper $visionEvidence,
        private readonly PhotoSceneAnalyzer $photos,
        private readonly SceneBriefBuilder $briefs,
        private readonly SceneGraphFactory $graphs,
        private readonly SceneCommandInterpreter $commands,
    ) {}

    public function show(User $user, string $subjectType, int $subjectId): PropertyVisualization
    {
        $this->entitlement->assert($user, 'visualization_3d');
        $this->assertOwned($user, $subjectType, $subjectId);

        $visualization = PropertyVisualization::query()->firstOrCreate(
            ['subject_type' => $subjectType, 'subject_id' => $subjectId],
            [
                'tenant_id' => (int) $user->tenant_id,
                'user_id' => $user->id,
                'status' => 'insufficient',
                'level' => 'fallback_gallery',
                'origin_label' => 'Nenhum modelo 3D autorizado',
                'limitations' => 'Não há geometria tridimensional confiável para este imóvel. A página permanece disponível com fotos, dados cadastrais e envio posterior de GLB/planta.',
            ],
        );

        return $visualization->load('assets');
    }

    public function payload(PropertyVisualization $visualization, User $user, string $subjectType, int $subjectId): array
    {
        $subject = $this->subject($user, $subjectType, $subjectId);
        $photos = $this->photos->existing($subjectType, $subjectId);
        $candidateModel = $visualization->assets->whereIn('kind', ['glb', 'gltf'])->sortByDesc('is_primary')->first();
        $photogrammetryJob = $visualization->processingJobs()->where('kind', 'photogrammetry')->latest('id')->first();
        $geometryQuality = data_get($photogrammetryJob?->result, 'geometry_quality');
        $isSparseCandidate = $candidateModel?->room_key === 'reconstruction_preview';
        $reconstructionViewerEligible = $isSparseCandidate
            ? data_get($geometryQuality, 'viewer_eligible') === true
            : null;
        $model = $isSparseCandidate && ! $reconstructionViewerEligible ? null : $candidateModel;
        $images = $visualization->assets->whereIn('kind', [
            'photo', 'floorplan', 'panorama', 'depth_map', 'segmentation_mask', 'vision_overlay',
            'photo_simulation', 'photo_comparison', 'point_cloud', 'reconstruction_mesh',
        ])->values();
        $hasFloorplan = $visualization->assets->contains(fn (PropertyVisualizationAsset $asset) => $asset->kind === 'floorplan');
        $hasMetadata = (int) ($subject['bedrooms'] ?? 0) > 0 || (float) ($subject['area_m2'] ?? 0) > 0;
        $hasPhotoScene = ($visualization->scene_graph['fidelity'] ?? null) === 'photo_referenced';
        $isReconstructionPreview = $model?->room_key === 'reconstruction_preview';
        $level = $model
            ? ($isReconstructionPreview ? 'photogrammetry_preview' : 'a_model')
            : ($hasPhotoScene
                ? 'photo_referenced'
                : ($hasFloorplan ? 'reconstructed_floorplan' : ($hasMetadata ? 'parametric_spec' : 'fallback_gallery')));
        $status = $model ? 'available' : ($hasPhotoScene ? 'photo_referenced' : ($hasMetadata ? 'parametric' : 'insufficient'));
        $illustrative = ! $model || $isReconstructionPreview;
        $photoSimulations = $visualization->assets->where('kind', 'photo_simulation')->sortByDesc('id')->map(function (PropertyVisualizationAsset $simulation) use ($visualization, $subjectType, $subjectId): array {
            $sourceId = str_starts_with((string) $simulation->room_key, 'source_') ? (int) substr((string) $simulation->room_key, 7) : 0;
            $source = $visualization->assets->firstWhere('id', $sourceId);
            $job = $visualization->processingJobs->where('kind', 'photo_simulation')
                ->filter(fn (PropertyProcessingJob $item) => in_array($sourceId, $item->input_asset_ids ?? [], true))
                ->sortByDesc('id')->first();
            $result = $job?->result ?? [];
            $promptPoints = collect(data_get($result, 'segmentation.prompt_points', []));
            $promptLabels = collect(data_get($result, 'segmentation.prompt_labels', []));

            return [
                'id' => 'asset-'.$simulation->id,
                'status' => 'completed',
                'source_asset_id' => $sourceId,
                'source_url' => $source ? '/api/visualizations/'.$subjectType.'/'.$subjectId.'/assets/'.$source->id.'/file' : null,
                'result_url' => '/api/visualizations/'.$subjectType.'/'.$subjectId.'/assets/'.$simulation->id.'/file',
                'comparison_url' => optional($visualization->assets->where('kind', 'photo_comparison')->where('room_key', $simulation->room_key)->sortByDesc('id')->first(), fn (PropertyVisualizationAsset $asset) => '/api/visualizations/'.$subjectType.'/'.$subjectId.'/assets/'.$asset->id.'/file'),
                'color' => data_get($result, 'composition.color', '#d8c6ae'),
                'opacity' => data_get($result, 'composition.opacity', 0.72),
                'material' => data_get($result, 'composition.material', 'paint'),
                'points' => $promptPoints->map(fn ($point, int $index) => [
                    'x' => (float) ($point[0] ?? 0),
                    'y' => (float) ($point[1] ?? 0),
                    'label' => (int) ($promptLabels->get($index, 1)),
                ])->all(),
                'created_at' => optional($simulation->created_at)->toISOString(),
                'label' => 'Simulação '.$simulation->id,
            ];
        })->values()->all();

        if ($visualization->level !== $level || $visualization->status !== $status) {
            $visualization->fill([
                'level' => $level,
                'status' => $status,
                'quality_score' => $model ? ($isReconstructionPreview ? 60 : 80) : ($hasMetadata ? 45 : 20),
                'origin_label' => $model
                    ? ($isReconstructionPreview ? 'Prévia fotogramétrica esparsa gerada das fotos' : 'Modelo 3D enviado e validado')
                    : ($hasPhotoScene
                        ? 'Cena referenciada nas fotografias deste registro'
                        : ($hasFloorplan
                            ? 'Planta 2D no registro; volume 3D ainda paramétrico'
                            : ($hasMetadata ? 'Maquete paramétrica gerada da ficha' : 'Fallback de galeria'))),
                'limitations' => $model
                    ? ($isReconstructionPreview
                        ? 'Reconstrução COLMAP esparsa com malha Delaunay de prévia. Não é uma superfície densa pronta nem substitui medição profissional.'
                        : 'A navegação usa o arquivo enviado. Não é uma reconstrução fotogramétrica automática. A maquete ao lado lê só metadados deste imóvel.')
                    : ($hasPhotoScene
                        ? 'Plano fotográfico e paleta vêm das imagens deste imóvel. A geometria é aproximada. Não é fotogrametria nem planta fiel.'
                        : 'Maquete gerada deste registro. Não é fotogrametria, tour 360 nem o modelo de outro imóvel.'),
            ])->save();
        }

        return [
            'id' => $visualization->id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject' => $subject,
            'status' => $visualization->status,
            'level' => $visualization->level,
            'quality_score' => $visualization->quality_score,
            'origin_label' => $visualization->origin_label,
            'limitations' => $visualization->limitations,
            'faithful' => ! $illustrative,
            'illustrative' => $illustrative,
            'reconstruction_quality' => $geometryQuality,
            'model' => $model ? [
                'id' => $model->id,
                'kind' => $model->kind,
                'name' => $model->original_name,
                'url' => '/api/visualizations/'.$subjectType.'/'.$subjectId.'/assets/'.$model->id.'/file',
                'fidelity' => $isReconstructionPreview ? 'sparse_preview' : 'uploaded_model',
                'viewer_eligible' => $isReconstructionPreview ? $reconstructionViewerEligible : true,
                'viewer_diagnostic' => $isReconstructionPreview ? [
                    'code' => data_get($geometryQuality, 'status', 'eligibility_not_confirmed'),
                    'message' => implode(' ', data_get($geometryQuality, 'reasons', [])),
                ] : [
                    'code' => 'uploaded_model',
                    'message' => 'Modelo enviado pelo usuário; o visualizador ainda valida bounds e integridade em tempo de carregamento.',
                ],
            ] : null,
            'gallery' => $images->map(fn (PropertyVisualizationAsset $asset) => [
                'id' => $asset->id,
                'kind' => $asset->kind,
                'name' => $asset->original_name,
                'room_key' => $asset->room_key,
                'url' => '/api/visualizations/'.$subjectType.'/'.$subjectId.'/assets/'.$asset->id.'/file',
            ])->all(),
            'photo_analyses' => $photos,
            'photo_editing' => ['simulations' => $photoSimulations],
            'rooms' => $visualization->rooms ?? $this->inferredRooms($subject),
            'scene_status' => $visualization->scene_status,
            'scene_brief' => $visualization->scene_brief,
            'scene_graph' => $this->withPhotoUrls($visualization, $subjectType, $subjectId),
            'versions' => $visualization->versions()->latest('id')->limit(12)->get(['id', 'label', 'created_at'])->all(),
            'processing_jobs' => $visualization->processingJobs()->latest('id')->limit(10)->get([
                'id', 'worker_job_id', 'kind', 'status', 'result', 'error', 'created_at', 'completed_at',
            ])->all(),
            'product_features' => [
                'renovation_simulation' => $this->entitlement->allows($user, 'renovation_simulation'),
                'visualization_3d' => true,
            ],
        ];
    }

    public function storeAsset(User $user, string $subjectType, int $subjectId, UploadedFile $file, string $kind, ?string $roomKey = null): PropertyVisualizationAsset
    {
        $this->entitlement->assert($user, 'visualization_3d');
        $this->assertOwned($user, $subjectType, $subjectId);
        $this->assertFile($file, $kind);
        $this->scanner->assertClean($file);

        $visualization = $this->show($user, $subjectType, $subjectId);
        $extension = strtolower($file->getClientOriginalExtension());
        $path = 'visualizations/'.$user->tenant_id.'/'.$subjectType.'/'.$subjectId.'/'.Str::uuid().'.'.$extension;
        $stored = Storage::disk(config('visualizations.disk'))->putFileAs(dirname($path), $file, basename($path));
        if (! $stored) {
            throw new DocumentStorageException('Não foi possível armazenar o arquivo 3D.');
        }

        if (in_array($kind, ['glb', 'gltf'], true)) {
            $visualization->assets()->whereIn('kind', ['glb', 'gltf'])->update(['is_primary' => false]);
        }

        $asset = $visualization->assets()->create([
            'kind' => $kind,
            'room_key' => $roomKey,
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 160, ''),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => (int) $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath() ?: $file->getPathname()),
            'is_primary' => in_array($kind, ['glb', 'gltf'], true),
        ]);

        $visualization->fill([
            'status' => in_array($kind, ['glb', 'gltf'], true) ? 'available' : $visualization->status,
            'level' => in_array($kind, ['glb', 'gltf'], true) ? 'a_model' : $visualization->level,
            'origin_label' => in_array($kind, ['glb', 'gltf'], true) ? 'Modelo 3D enviado e validado' : $visualization->origin_label,
        ])->save();

        return $asset;
    }

    public function stream(User $user, string $subjectType, int $subjectId, int $assetId): StreamedResponse
    {
        $visualization = $this->show($user, $subjectType, $subjectId);
        $asset = $visualization->assets()->whereKey($assetId)->firstOrFail();
        $disk = Storage::disk(config('visualizations.disk'));
        if (! $disk->exists($asset->path)) {
            abort(404, 'Arquivo da visualização não encontrado.');
        }

        return $disk->response($asset->path, $asset->original_name, [
            'Content-Type' => $asset->mime_type,
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="'.$asset->original_name.'"',
        ]);
    }

    public function analyzeNeural(User $user, string $subjectType, int $subjectId, int $assetId, array $points = []): array
    {
        $visualization = $this->show($user, $subjectType, $subjectId);
        $asset = $visualization->assets()->whereIn('kind', ['photo', 'panorama'])->whereKey($assetId)->firstOrFail();
        $disk = Storage::disk(config('visualizations.disk'));
        abort_unless($disk->exists($asset->path), 404, 'Fotografia não encontrada.');
        $job = $this->startProcessingJob($visualization, $user, 'vision', [$asset->id]);

        try {
            $result = $this->worker->analyzeVision($disk->get($asset->path), $asset->original_name, $points);
            $job->fill(['worker_job_id' => $result['job_id'], 'result' => $result, 'status' => 'completed', 'completed_at' => now()])->save();
            $this->importWorkerArtifact($visualization, $result['job_id'], data_get($result, 'depth.artifact'), 'depth_map', 'depth.png', 'image/png');
            $this->importWorkerArtifact($visualization, $result['job_id'], data_get($result, 'segmentation.artifact'), 'segmentation_mask', 'mask.png', 'image/png');
            $this->importWorkerArtifact($visualization, $result['job_id'], data_get($result, 'segmentation.overlay_artifact'), 'vision_overlay', 'segmentation-overlay.png', 'image/png');
            $evidence = $this->visionEvidence->map($result);
            PropertyPhotoAnalysis::query()->updateOrCreate([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'source_name' => $asset->original_name,
            ], [
                'source_photo_id' => $asset->id,
                'room_guess' => $evidence['room_guess'],
                'elements' => $evidence['elements'],
                'confidence' => $evidence['room_confidence'],
                'detection_confidence' => $evidence['detection_confidence'],
                'room_confidence' => $evidence['room_confidence'],
                'room_provenance' => [
                    ...$evidence['room_provenance'],
                    'asset_room_tag' => $asset->room_key,
                    'asset_room_tag_used_as_classification' => false,
                ],
                'origin' => 'neural_object_detection',
                'quality' => 'model_output',
                'limitations' => array_values(array_unique([
                    ...data_get($result, 'structured_result.limitations', []),
                    ...$evidence['limitations'],
                ])),
                'pixel_stats' => ['depth' => $result['depth'], 'segmentation' => $result['segmentation'], 'detection' => $result['detection'] ?? null],
            ]);

            $this->persistSceneFromStoredAnalyses($visualization->fresh('assets'), $user, $subjectType, $subjectId);

            return $this->payload($visualization->fresh('assets', 'versions'), $user, $subjectType, $subjectId);
        } catch (\Throwable $exception) {
            $job->fill(['status' => 'failed', 'error' => Str::limit($exception->getMessage(), 1000, ''), 'completed_at' => now()])->save();
            throw $exception;
        }
    }

    public function reconstructPhotogrammetry(User $user, string $subjectType, int $subjectId, array $assetIds): array
    {
        $visualization = $this->show($user, $subjectType, $subjectId);
        $limit = (int) config('visualizations.max_capture_images', 120);
        abort_if(count($assetIds) < 8 || count($assetIds) > $limit, 422, 'Selecione entre 8 e '.$limit.' fotografias do mesmo ambiente.');
        $assets = $visualization->assets()->whereIn('kind', ['photo', 'panorama'])->whereIn('id', $assetIds)->get();
        abort_unless($assets->count() === count(array_unique($assetIds)), 422, 'Uma ou mais fotografias não pertencem a este imóvel.');
        $disk = Storage::disk(config('visualizations.disk'));
        $files = $assets->map(function (PropertyVisualizationAsset $asset) use ($disk): array {
            abort_unless($disk->exists($asset->path), 422, 'Uma fotografia selecionada não está disponível.');

            return ['name' => $asset->original_name, 'contents' => $disk->get($asset->path)];
        })->all();
        $job = $this->startProcessingJob($visualization, $user, 'photogrammetry', $assets->pluck('id')->all());

        try {
            $result = $this->worker->reconstruct($files);
            if (($result['status'] ?? null) === 'completed') {
                $job->fill(['worker_job_id' => $result['job_id']])->save();
                $this->completeReconstructionJob($visualization, $job, $result);
            } else {
                $status = ($result['status'] ?? null) === 'queued' ? 'queued' : 'processing';
                $job->fill(['worker_job_id' => $result['job_id'], 'result' => $result, 'status' => $status])->save();
            }

            return $this->payload($visualization->fresh('assets'), $user, $subjectType, $subjectId);
        } catch (\Throwable $exception) {
            $job->fill(['status' => 'failed', 'error' => Str::limit($exception->getMessage(), 1000, ''), 'completed_at' => now()])->save();
            throw $exception;
        }
    }

    public function refreshReconstructionJob(User $user, string $subjectType, int $subjectId, int $processingJobId): array
    {
        $visualization = $this->show($user, $subjectType, $subjectId);
        $job = $visualization->processingJobs()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('kind', 'photogrammetry')
            ->whereKey($processingJobId)
            ->firstOrFail();
        if (in_array($job->status, ['completed', 'failed'], true)) {
            return $this->payload($visualization->fresh('assets', 'versions'), $user, $subjectType, $subjectId);
        }
        abort_unless($job->worker_job_id, 409, 'O worker ainda não aceitou este processamento.');

        $result = $this->worker->job($job->worker_job_id);
        $status = (string) ($result['status'] ?? 'processing');
        if ($status === 'failed') {
            $job->fill([
                'status' => 'failed',
                'result' => $result,
                'error' => Str::limit((string) ($result['error'] ?? 'Falha no worker de reconstrução.'), 1000, ''),
                'completed_at' => now(),
            ])->save();
        } elseif ($status === 'completed') {
            $this->completeReconstructionJob($visualization, $job, $result);
        } else {
            $job->fill(['status' => $status === 'queued' ? 'queued' : 'processing', 'result' => $result])->save();
        }

        return $this->payload($visualization->fresh('assets', 'versions'), $user, $subjectType, $subjectId);
    }

    private function completeReconstructionJob(PropertyVisualization $visualization, PropertyProcessingJob $job, array $result): void
    {
        $job->fill(['result' => $result, 'status' => 'processing'])->save();
        $jobId = (string) $job->worker_job_id;
        abort_unless($jobId !== '' && hash_equals($jobId, (string) ($result['job_id'] ?? '')), 502, 'O worker retornou um identificador de job inconsistente.');
        $this->importWorkerArtifact($visualization, $jobId, data_get($result, 'artifacts.sparse_point_cloud'), 'point_cloud', 'sparse.ply', 'application/octet-stream');
        $this->importWorkerArtifact($visualization, $jobId, data_get($result, 'artifacts.mesh'), 'reconstruction_mesh', 'mesh.ply', 'application/octet-stream');
        $viewerEligible = data_get($result, 'geometry_quality.viewer_eligible') === true;
        $model = $viewerEligible
            ? $this->importWorkerArtifact($visualization, $jobId, data_get($result, 'artifacts.glb'), 'glb', 'reconstruction-preview.glb', 'model/gltf-binary', 'reconstruction_preview')
            : null;
        if ($model) {
            $visualization->assets()->whereIn('kind', ['glb', 'gltf'])->where('id', '!=', $model->id)->update(['is_primary' => false]);
            $model->forceFill(['is_primary' => true])->save();
            $visualization->fill([
                'status' => 'available', 'level' => 'photogrammetry_preview', 'quality_score' => 60,
                'origin_label' => 'Prévia fotogramétrica gerada das fotos',
                'limitations' => implode(' ', data_get($result, 'limitations', [])),
            ])->save();
        } else {
            $visualization->fill([
                'status' => is_array($visualization->scene_graph) ? 'photo_referenced' : 'insufficient',
                'level' => is_array($visualization->scene_graph) ? 'photo_referenced' : 'fallback_gallery',
                'quality_score' => null,
                'origin_label' => 'Reconstrução multivista insuficiente para visualização',
                'limitations' => implode(' ', data_get($result, 'geometry_quality.reasons', [
                    'A geometria produzida não atingiu os critérios mínimos de conectividade e densidade.',
                ])),
            ])->save();
        }
        $job->fill(['status' => 'completed', 'completed_at' => now()])->save();
    }

    public function simulatePhoto(
        User $user,
        string $subjectType,
        int $subjectId,
        int $assetId,
        string $color,
        array $points = [],
        float $opacity = 0.72,
        string $material = 'paint',
    ): array {
        $this->entitlement->assert($user, 'renovation_simulation');
        $visualization = $this->show($user, $subjectType, $subjectId);
        $asset = $visualization->assets()->whereIn('kind', ['photo', 'panorama'])->whereKey($assetId)->firstOrFail();
        $disk = Storage::disk(config('visualizations.disk'));
        abort_unless($disk->exists($asset->path), 404, 'Fotografia não encontrada.');
        $job = $this->startProcessingJob($visualization, $user, 'photo_simulation', [$asset->id]);

        try {
            $result = $this->worker->simulatePhoto(
                $disk->get($asset->path),
                $asset->original_name,
                $color,
                $points,
                $opacity,
                $material,
            );
            $job->fill([
                'worker_job_id' => $result['job_id'],
                'result' => $result,
                'status' => 'completed',
                'completed_at' => now(),
            ])->save();
            $sourceKey = 'source_'.$asset->id;
            $this->importWorkerArtifact($visualization, $result['job_id'], data_get($result, 'segmentation.artifact'), 'segmentation_mask', 'paint-mask.png', 'image/png', $sourceKey);
            $this->importWorkerArtifact($visualization, $result['job_id'], data_get($result, 'segmentation.overlay_artifact'), 'vision_overlay', 'paint-mask-overlay.png', 'image/png', $sourceKey);
            $this->importWorkerArtifact($visualization, $result['job_id'], data_get($result, 'simulation_artifact'), 'photo_simulation', 'simulacao-'.$asset->id.'.png', 'image/png', $sourceKey);
            $this->importWorkerArtifact($visualization, $result['job_id'], data_get($result, 'comparison_artifact'), 'photo_comparison', 'antes-depois-'.$asset->id.'.png', 'image/png', $sourceKey);

            return $this->payload($visualization->fresh('assets', 'versions'), $user, $subjectType, $subjectId);
        } catch (\Throwable $exception) {
            $job->fill(['status' => 'failed', 'error' => Str::limit($exception->getMessage(), 1000, ''), 'completed_at' => now()])->save();
            throw $exception;
        }
    }

    private function startProcessingJob(PropertyVisualization $visualization, User $user, string $kind, array $assetIds): PropertyProcessingJob
    {
        return $visualization->processingJobs()->create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'kind' => $kind,
            'status' => 'processing',
            'input_asset_ids' => array_values($assetIds),
            'started_at' => now(),
        ]);
    }

    private function importWorkerArtifact(
        PropertyVisualization $visualization,
        string $jobId,
        ?string $name,
        string $kind,
        string $originalName,
        string $mimeType,
        ?string $roomKey = null,
    ): ?PropertyVisualizationAsset {
        if (! $name) {
            return null;
        }
        $bytes = $this->worker->artifact($jobId, $name);
        $maxBytes = (int) config('visualizations.max_processing_storage_mb', 512) * 1024 * 1024;
        $used = (int) $visualization->assets()->sum('size_bytes');
        abort_if($used + strlen($bytes) > $maxBytes, 422, 'O limite de armazenamento de processamento deste imóvel foi atingido.');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $path = 'visualizations/'.$visualization->tenant_id.'/'.$visualization->subject_type.'/'.$visualization->subject_id.'/'.Str::uuid().'.'.$extension;
        abort_unless(Storage::disk(config('visualizations.disk'))->put($path, $bytes), 503, 'Não foi possível armazenar o artefato processado.');

        return $visualization->assets()->create([
            'kind' => $kind,
            'room_key' => $roomKey,
            'path' => $path,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($bytes),
            'checksum' => hash('sha256', $bytes),
            'is_primary' => false,
        ]);
    }

    public function rebuild(User $user, string $subjectType, int $subjectId): array
    {
        $visualization = $this->show($user, $subjectType, $subjectId);
        $visualization->fill(['scene_status' => 'analyzing'])->save();
        if ($subjectType === 'opportunity') {
            $this->photos->analyzeOpportunity($user, $subjectId);
            $this->importOpportunityPhotos($user, $visualization, $subjectId);
        } else {
            $this->photos->forget($subjectType, $subjectId, preserveNeural: true);
            $disk = Storage::disk(config('visualizations.disk'));
            foreach ($visualization->assets()->where('kind', 'photo')->get() as $asset) {
                if ($this->photos->hasNeuralEvidence($subjectType, $subjectId, $asset->original_name)) {
                    continue;
                }
                $this->photos->analyzeNamedFile(
                    $user,
                    $subjectType,
                    $subjectId,
                    $asset->original_name,
                    $disk->path($asset->path),
                );
            }
        }

        return $this->persistSceneFromStoredAnalyses($visualization->fresh('assets'), $user, $subjectType, $subjectId);
    }

    private function persistSceneFromStoredAnalyses(
        PropertyVisualization $visualization,
        User $user,
        string $subjectType,
        int $subjectId,
    ): array {
        $subject = $this->subject($user, $subjectType, $subjectId);
        $visualization->load('assets');
        $analyses = $this->photos->existing($subjectType, $subjectId);
        $photoAssets = $visualization->assets->where('kind', 'photo')->map(fn (PropertyVisualizationAsset $asset) => [
            'id' => $asset->id,
            'kind' => $asset->kind,
            'name' => $asset->original_name,
            'room_key' => $asset->room_key,
            'source_photo_id' => null,
            'url' => '/api/visualizations/'.$subjectType.'/'.$subjectId.'/assets/'.$asset->id.'/file',
        ])->values()->all();
        foreach ($analyses as &$row) {
            foreach ($photoAssets as $asset) {
                if ($asset['name'] === ($row['source_name'] ?? null)) {
                    $row['asset_id'] = $asset['id'];
                }
            }
        }
        unset($row);
        $brief = $this->briefs->build($analyses, $subject, $photoAssets);
        $graph = $this->graphs->fromBrief($brief, $photoAssets, $subject);
        $visualization->fill([
            'scene_status' => $photoAssets === [] ? 'insufficient' : 'ready',
            'scene_brief' => $brief,
            'scene_graph' => $graph,
            'rooms' => array_map(fn (array $room) => [
                'key' => $room['key'],
                'label' => $room['label'],
                'source' => 'photo_brief',
                'available' => true,
            ], $graph['rooms']),
        ])->save();
        if (! $visualization->versions()->where('label', 'original')->exists()) {
            $visualization->versions()->create([
                'user_id' => $user->id,
                'label' => 'original',
                'graph' => $graph,
            ]);
        }

        return $this->payload($visualization->fresh('assets', 'versions'), $user, $subjectType, $subjectId);
    }

    public function applyCommand(User $user, string $subjectType, int $subjectId, string $message, ?string $selectedId = null): array
    {
        $visualization = $this->show($user, $subjectType, $subjectId);
        $graph = $visualization->scene_graph ?? $this->rebuild($user, $subjectType, $subjectId)['scene_graph'];
        $parsed = $this->commands->interpret($message, $graph ?? [], $selectedId);
        if (! ($parsed['ok'] ?? false)) {
            return ['command' => $parsed, 'data' => $this->payload($visualization, $user, $subjectType, $subjectId)];
        }
        $actions = $parsed['actions'];
        if (collect($actions)->contains(fn (array $action) => ($action['op'] ?? '') === 'restore_original')) {
            $original = $visualization->versions()->where('label', 'original')->latest('id')->first();
            if ($original) {
                $visualization->fill(['scene_graph' => $original->graph])->save();
            }
        } else {
            $visualization->fill(['scene_graph' => $this->graphs->apply($graph ?? [], $actions)])->save();
        }

        return ['command' => $parsed, 'data' => $this->payload($visualization->fresh('assets', 'versions'), $user, $subjectType, $subjectId)];
    }

    public function applyActions(User $user, string $subjectType, int $subjectId, array $actions): array
    {
        $visualization = $this->show($user, $subjectType, $subjectId);
        $graph = $visualization->scene_graph;
        if (! is_array($graph)) {
            $graph = $this->rebuild($user, $subjectType, $subjectId)['scene_graph'];
        }
        $visualization->fill(['scene_graph' => $this->graphs->apply($graph ?? [], $actions)])->save();

        return $this->payload($visualization->fresh('assets', 'versions'), $user, $subjectType, $subjectId);
    }

    public function saveVersion(User $user, string $subjectType, int $subjectId, string $label): PropertySceneVersion
    {
        $visualization = $this->show($user, $subjectType, $subjectId);
        abort_unless(is_array($visualization->scene_graph), 422, 'Gere a cena antes de salvar uma versão.');

        return $visualization->versions()->create([
            'user_id' => $user->id,
            'label' => Str::limit($label, 80, ''),
            'graph' => $visualization->scene_graph,
        ]);
    }

    public function restoreVersion(User $user, string $subjectType, int $subjectId, int $versionId): array
    {
        $visualization = $this->show($user, $subjectType, $subjectId);
        $version = $visualization->versions()->whereKey($versionId)->firstOrFail();
        $visualization->fill(['scene_graph' => $version->graph])->save();

        return $this->payload($visualization->fresh('assets', 'versions'), $user, $subjectType, $subjectId);
    }

    private function importOpportunityPhotos(User $user, PropertyVisualization $visualization, int $opportunityId): void
    {
        $opportunity = Opportunity::query()->where('user_id', $user->id)->with('photos')->whereKey($opportunityId)->firstOrFail();
        $disk = Storage::disk(config('visualizations.disk'));
        foreach ($opportunity->photos as $photo) {
            $path = (string) $photo->path;
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || ! OpportunityPhotoPathPolicy::allows($path)) {
                continue;
            }
            if (! Storage::disk('public')->exists($path)) {
                continue;
            }
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg');
            $target = 'visualizations/'.$user->tenant_id.'/opportunity/'.$opportunityId.'/'.Str::uuid().'.'.$extension;
            $bytes = Storage::disk('public')->get($path);
            if ($bytes === null || $bytes === '') {
                continue;
            }
            $checksum = hash('sha256', $bytes);
            if ($visualization->assets()->where('kind', 'photo')->where('checksum', $checksum)->exists()) {
                continue;
            }
            $disk->put($target, $bytes);
            $visualization->assets()->create([
                'kind' => 'photo',
                'room_key' => null,
                'path' => $target,
                'original_name' => Str::limit((string) $photo->original_name, 160, ''),
                'mime_type' => 'image/jpeg',
                'size_bytes' => strlen($bytes),
                'checksum' => $checksum,
                'is_primary' => false,
            ]);
        }
    }

    private function withPhotoUrls(PropertyVisualization $visualization, string $subjectType, int $subjectId): ?array
    {
        $graph = $visualization->scene_graph;
        if (! is_array($graph)) {
            return null;
        }
        $graph['photo_assets'] = $visualization->assets->where('kind', 'photo')->map(fn (PropertyVisualizationAsset $asset) => [
            'id' => $asset->id,
            'url' => '/api/visualizations/'.$subjectType.'/'.$subjectId.'/assets/'.$asset->id.'/file',
            'name' => $asset->original_name,
            'room_key' => $asset->room_key,
        ])->values()->all();

        return $graph;
    }

    private function assertOwned(User $user, string $subjectType, int $subjectId): void
    {
        if ($subjectType === 'propriedade') {
            if (! Propriedade::query()->ativos()->whereKey($subjectId)->exists()) {
                abort(404, 'Imóvel não encontrado.');
            }

            return;
        }
        if ($subjectType === 'opportunity') {
            if (! Opportunity::query()->where('user_id', $user->id)->whereKey($subjectId)->exists()) {
                abort(404, 'Oportunidade não encontrada.');
            }

            return;
        }

        abort(404, 'Registro não encontrado.');
    }

    private function subject(User $user, string $subjectType, int $subjectId): array
    {
        if ($subjectType === 'propriedade') {
            $property = Propriedade::query()->ativos()->with('endereco')->whereKey($subjectId)->firstOrFail();

            return [
                'title' => $property->apelido,
                'city' => $property->endereco?->cidade,
                'neighborhood' => $property->endereco?->bairro,
                'state' => $property->endereco?->estado,
                'area_m2' => $property->area_m2,
                'bedrooms' => $property->quartos,
                'bathrooms' => $property->banheiros,
                'parking_spaces' => $property->vagas,
                'property_type' => $property->tipo,
                'back_path' => '/imoveis',
            ];
        }
        $opportunity = Opportunity::query()->where('user_id', $user->id)->whereKey($subjectId)->firstOrFail();

        return [
            'title' => $opportunity->title,
            'city' => $opportunity->city,
            'neighborhood' => $opportunity->neighborhood,
            'state' => $opportunity->state,
            'area_m2' => $opportunity->area_m2,
            'bedrooms' => $opportunity->bedrooms,
            'bathrooms' => $opportunity->bathrooms,
            'parking_spaces' => $opportunity->parking_spaces,
            'property_type' => $opportunity->property_type?->value,
            'cover_url' => null,
            'back_path' => '/leads',
        ];
    }

    private function inferredRooms(array $subject): array
    {
        $hasGeometry = (int) ($subject['bedrooms'] ?? 0) > 0 || (float) ($subject['area_m2'] ?? 0) > 0;
        $rooms = [];
        $bedrooms = (int) ($subject['bedrooms'] ?? 0);
        for ($index = 1; $index <= min(6, max(0, $bedrooms)); $index++) {
            $rooms[] = ['key' => 'bedroom-'.$index, 'label' => 'Quarto '.$index, 'source' => 'advertiser', 'available' => $hasGeometry];
        }
        if ((int) ($subject['bathrooms'] ?? 0) > 0) {
            $rooms[] = ['key' => 'bathroom', 'label' => 'Banheiro', 'source' => 'advertiser', 'available' => $hasGeometry];
        }
        $rooms[] = ['key' => 'living', 'label' => 'Sala', 'source' => 'inference', 'available' => $hasGeometry];
        $rooms[] = ['key' => 'kitchen', 'label' => 'Cozinha', 'source' => 'inference', 'available' => $hasGeometry];

        return $rooms;
    }

    private function assertFile(UploadedFile $file, string $kind): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = $kind === 'glb' || $kind === 'gltf'
            ? config('visualizations.model_extensions')
            : config('visualizations.image_extensions');
        if (! in_array($extension, $allowed, true)) {
            abort(422, 'Formato não suportado para este tipo de arquivo.');
        }
        $maximumKilobytes = in_array($kind, ['glb', 'gltf'], true)
            ? (int) config('visualizations.max_model_kb')
            : (int) config('visualizations.max_image_kb');
        if ($file->getSize() > $maximumKilobytes * 1024) {
            abort(422, 'Arquivo excede o tamanho máximo permitido.');
        }
        if (in_array($kind, ['glb', 'gltf'], true)) {
            $handle = fopen($file->getRealPath() ?: $file->getPathname(), 'rb');
            $header = $handle ? fread($handle, 4) : '';
            if ($handle) {
                fclose($handle);
            }
            if ($kind === 'glb' && $header !== 'glTF') {
                throw new DocumentRejectedException('O arquivo GLB é inválido.');
            }
        }
    }
}
