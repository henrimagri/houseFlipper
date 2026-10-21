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
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    public function payload(PropertyVisualization $visualization, User $user, string $subjectType, int $subjectId, ?int $diagnosticAssetId = null): array
    {
        $subject = $this->subject($user, $subjectType, $subjectId);
        $photos = $this->photos->existing($subjectType, $subjectId);
        $models = $visualization->assets->whereIn('kind', ['glb', 'gltf']);
        $primaryModel = $models->firstWhere('is_primary', true);
        $diagnosticModel = $diagnosticAssetId
            ? $models->first(fn (PropertyVisualizationAsset $asset): bool => $asset->id === $diagnosticAssetId
                && ! $asset->is_primary && $asset->room_key === 'reconstruction_preview')
            : null;
        abort_if($diagnosticAssetId && ! $diagnosticModel, 404, 'Candidato de diagnóstico não encontrado neste imóvel.');
        $candidateModel = $diagnosticModel ?: ($primaryModel ?: $models->first());
        $photogrammetryJobs = $visualization->processingJobs()->where('kind', 'photogrammetry')->latest('id')->get();
        $photogrammetryJob = $diagnosticModel
            ? $photogrammetryJobs->first(fn (PropertyProcessingJob $job): bool => (int) data_get($job->result, 'promotion.candidate_asset_id') === $diagnosticModel->id)
            : $photogrammetryJobs->first();
        $geometryQuality = data_get($photogrammetryJob?->result, 'geometry_quality');
        $modelJob = $candidateModel
            ? $photogrammetryJobs->first(fn (PropertyProcessingJob $job): bool => (int) data_get($job->result, 'promotion.candidate_asset_id') === $candidateModel->id)
            : null;
        $modelGeometryQuality = data_get($modelJob?->result, 'geometry_quality');
        $isSparseCandidate = $candidateModel?->room_key === 'reconstruction_preview';
        $reconstructionViewerEligible = $isSparseCandidate
            ? ($candidateModel?->is_primary || data_get($geometryQuality, 'viewer_eligible') === true)
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
            $sourceJobs = $visualization->processingJobs->where('kind', 'photo_simulation')->where('status', 'completed')
                ->filter(fn (PropertyProcessingJob $item) => in_array($sourceId, $item->input_asset_ids ?? [], true))
                ->sortByDesc('id');
            $job = $sourceJobs->first(fn (PropertyProcessingJob $item) => (int) data_get($item->result, 'assets.simulation_id') === $simulation->id);
            // Legacy records have no asset/job link. Only a single unambiguous pair can be recovered.
            if (! $job && $sourceJobs->count() === 1 && ! data_get($sourceJobs->first()->result, 'assets.simulation_id')
                && $visualization->assets->where('kind', 'photo_simulation')->where('room_key', $simulation->room_key)->count() === 1) {
                $job = $sourceJobs->first();
            }
            $result = $job?->result ?? [];

            return $this->photoSimulationPayload($visualization, $simulation, $result, $subjectType, $subjectId, $job !== null);
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
            'diagnostic_models' => $models->filter(fn (PropertyVisualizationAsset $asset): bool => ! $asset->is_primary
                && $asset->room_key === 'reconstruction_preview'
                && $photogrammetryJobs->contains(fn (PropertyProcessingJob $job): bool => (int) data_get($job->result, 'promotion.candidate_asset_id') === $asset->id
                    && data_get($job->result, 'geometry_quality.viewer_eligible') === true))
                ->map(fn (PropertyVisualizationAsset $asset): array => [
                    'id' => $asset->id,
                    'name' => $asset->original_name,
                    'active' => $diagnosticModel?->id === $asset->id,
                ])->values()->all(),
            'model' => $model ? [
                'id' => $model->id,
                'kind' => $model->kind,
                'name' => $model->original_name,
                'url' => '/api/visualizations/'.$subjectType.'/'.$subjectId.'/assets/'.$model->id.'/file',
                'fidelity' => $isReconstructionPreview ? 'sparse_preview' : 'uploaded_model',
                'viewer_eligible' => $isReconstructionPreview ? $reconstructionViewerEligible : true,
                'diagnostic_preview' => (bool) $diagnosticModel,
                'viewer_diagnostic' => $isReconstructionPreview ? [
                    'code' => data_get($modelGeometryQuality, 'status', $candidateModel?->is_primary ? 'active_model_preserved' : 'eligibility_not_confirmed'),
                    'message' => implode(' ', data_get($modelGeometryQuality, 'reasons', $candidateModel?->is_primary
                        ? ['Modelo ativo preservado pelo gate de qualidade.']
                        : [])),
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

    public function reconstructPhotogrammetry(User $user, string $subjectType, int $subjectId, array $assetIds, string $method = 'auto'): array
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
        $runningForTenant = PropertyProcessingJob::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('kind', 'photogrammetry')
            ->whereIn('status', ['queued', 'processing'])
            ->count();
        abort_if($runningForTenant >= (int) config('visualizations.max_concurrent_reconstructions_per_tenant', 1), 429, 'Já existe uma reconstrução em andamento nesta conta.');
        $job = $this->startProcessingJob($visualization, $user, 'photogrammetry', $assets->pluck('id')->all());

        try {
            $scope = $this->workerScope($visualization);
            $result = $this->worker->reconstruct($files, $method, $scope, $this->workerIdempotencyKey($job));
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
        if (in_array($job->status, ['completed', 'failed', 'cancelled'], true)) {
            return $this->payload($visualization->fresh('assets', 'versions'), $user, $subjectType, $subjectId);
        }
        abort_unless($job->worker_job_id, 409, 'O worker ainda não aceitou este processamento.');

        $result = $this->worker->job($job->worker_job_id, $this->workerScope($visualization));
        $status = (string) ($result['status'] ?? 'processing');
        if ($status === 'failed') {
            $job->fill([
                'status' => 'failed',
                'result' => $result,
                'error' => Str::limit((string) ($result['error'] ?? 'Falha no worker de reconstrução.'), 1000, ''),
                'completed_at' => now(),
            ])->save();
        } elseif ($status === 'cancelled') {
            $job->fill(['status' => 'cancelled', 'result' => $result, 'completed_at' => now()])->save();
        } elseif ($status === 'completed') {
            $this->completeReconstructionJob($visualization, $job, $result);
        } else {
            $job->fill(['status' => $status === 'queued' ? 'queued' : 'processing', 'result' => $result])->save();
        }

        return $this->payload($visualization->fresh('assets', 'versions'), $user, $subjectType, $subjectId);
    }

    public function cancelReconstructionJob(User $user, string $subjectType, int $subjectId, int $processingJobId): array
    {
        $visualization = $this->show($user, $subjectType, $subjectId);
        $job = $visualization->processingJobs()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('kind', 'photogrammetry')
            ->whereKey($processingJobId)
            ->firstOrFail();
        abort_unless($job->worker_job_id, 409, 'O worker ainda não aceitou este processamento.');
        if (! in_array($job->status, ['completed', 'failed', 'cancelled'], true)) {
            $result = $this->worker->cancelJob($job->worker_job_id, $this->workerScope($visualization));
            $job->fill(['status' => 'processing', 'result' => $result])->save();
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
        $previousPrimary = $visualization->assets()
            ->whereIn('kind', ['glb', 'gltf'])
            ->where('is_primary', true)
            ->first();
        $viewerEligible = data_get($result, 'geometry_quality.viewer_eligible') === true;
        $model = $viewerEligible
            ? $this->importWorkerArtifact($visualization, $jobId, data_get($result, 'artifacts.glb'), 'glb', 'reconstruction-preview.glb', 'model/gltf-binary', 'reconstruction_preview')
            : null;
        $promotionEligible = data_get($result, 'geometry_quality.final_model_eligible') === true;
        $promote = $model && (! $previousPrimary || $promotionEligible);
        if ($promote) {
            $visualization->assets()->whereIn('kind', ['glb', 'gltf'])->where('id', '!=', $model->id)->update(['is_primary' => false]);
            $model->forceFill(['is_primary' => true])->save();
            $visualization->fill([
                'status' => 'available', 'level' => 'photogrammetry_preview', 'quality_score' => 60,
                'origin_label' => 'Prévia fotogramétrica gerada das fotos',
                'limitations' => implode(' ', data_get($result, 'limitations', [])),
            ])->save();
        } elseif (! $previousPrimary) {
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
        $storedResult = $result;
        $storedResult['promotion'] = [
            'promoted' => (bool) $promote,
            'candidate_asset_id' => $model?->id,
            'previous_primary_preserved' => (bool) ($previousPrimary && ! $promote),
            'reason' => $promote
                ? ($previousPrimary ? 'candidate_passed_final_gate' : 'first_viewer_eligible_preview')
                : ($model ? 'candidate_requires_geometric_and_visual_approval' : 'candidate_not_viewer_eligible'),
        ];
        $job->fill(['result' => $storedResult, 'status' => 'completed', 'completed_at' => now()])->save();
    }

    public function selectPhoto(User $user, string $subjectType, int $subjectId, int $assetId, array $points): array
    {
        $this->entitlement->assert($user, 'renovation_simulation');
        $visualization = $this->show($user, $subjectType, $subjectId);
        $asset = $visualization->assets()->whereIn('kind', ['photo', 'panorama'])->whereKey($assetId)->firstOrFail();
        $disk = Storage::disk(config('visualizations.disk'));
        abort_unless($disk->exists($asset->path), 404, 'Fotografia não encontrada.');
        $job = $this->startPhotoProcessingJob($visualization, $user, 'photo_selection', [$assetId]);
        try {
            $result = $this->worker->segmentPhoto($disk->get($asset->path), $asset->original_name, $points);
            abort_unless(($result['status'] ?? null) === 'completed', 502, 'O worker não concluiu a seleção.');
            $mask = $this->importWorkerArtifact($visualization, $result['job_id'], data_get($result, 'segmentation.artifact'), 'segmentation_mask', 'neural-mask.png', 'image/png', 'source_'.$assetId);
            abort_unless($mask, 502, 'O worker não devolveu a máscara da seleção.');
            $this->validateMaskBytes($disk->get($mask->path));
            $result['assets'] = ['mask_id' => $mask->id];
            $result['editor_input'] = ['points' => $points];
            $job->fill(['worker_job_id' => $result['job_id'], 'result' => $result, 'status' => 'completed', 'completed_at' => now()])->save();

            return ['mask_asset_id' => $mask->id, 'mask_url' => $this->photoAssetUrl($subjectType, $subjectId, $mask->id), 'source_asset_id' => $assetId,
                'points' => $points, 'dimensions' => data_get($result, 'segmentation.dimensions')];
        } catch (\Throwable $exception) {
            $job->fill(['status' => 'failed', 'error' => 'Falha ao preparar a seleção da fotografia.', 'completed_at' => now()])->save();
            if ($exception instanceof RequestException || $exception instanceof ConnectionException) {
                abort(502, 'O serviço de seleção está indisponível. Tente novamente.');
            }
            throw $exception;
        }
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
        string $maskMode = 'sam',
        array $maskStrokes = [],
        ?int $baseMaskAssetId = null,
        string $finish = 'matte',
        bool $preview = false,
    ): array {
        $this->entitlement->assert($user, 'renovation_simulation');
        $visualization = $this->show($user, $subjectType, $subjectId);
        $asset = $visualization->assets()->whereIn('kind', ['photo', 'panorama'])->whereKey($assetId)->firstOrFail();
        $disk = Storage::disk(config('visualizations.disk'));
        abort_unless($disk->exists($asset->path), 404, 'Fotografia não encontrada.');
        $baseMask = null;
        $selectionPoints = null;
        $selectionDimensions = null;
        if ($maskMode === 'refined') {
            $mask = $visualization->assets()->whereKey($baseMaskAssetId)->where('kind', 'segmentation_mask')->where('room_key', 'source_'.$assetId)->firstOrFail();
            $selection = $visualization->processingJobs()->where('kind', 'photo_selection')->where('status', 'completed')
                ->where('result->assets->mask_id', $mask->id)->get()
                ->first(fn (PropertyProcessingJob $selection) => in_array($assetId, $selection->input_asset_ids ?? [], true));
            abort_unless($selection, 422, 'Utilize uma máscara neural preparada para esta fotografia.');
            $selectionPoints = $this->photoPromptPoints($selection->result);
            $selectionDimensions = data_get($selection->result, 'segmentation.dimensions');
            abort_unless($disk->exists($mask->path), 404, 'Máscara base não encontrada.');
            $baseMask = $disk->get($mask->path);
            abort_unless(hash_equals($mask->checksum, hash('sha256', $baseMask)), 422, 'A máscara base foi alterada; prepare outra seleção.');
            $this->validateMaskBytes($baseMask);
        }
        $job = $this->startPhotoProcessingJob($visualization, $user, $preview ? 'photo_preview' : 'photo_simulation', [$asset->id]);

        try {
            $result = $this->worker->simulatePhoto(
                $disk->get($asset->path),
                $asset->original_name,
                $color,
                $maskMode === 'refined' ? [] : $points,
                $opacity,
                $material,
                $maskMode,
                $maskStrokes,
                $baseMask,
                $finish,
            );
            abort_unless(($result['status'] ?? null) === 'completed', 502, 'O worker não concluiu a simulação.');
            $result['editor_input'] = ['mask_mode' => $maskMode, 'mask_strokes' => $maskStrokes, 'base_mask_asset_id' => $baseMaskAssetId, 'finish' => $finish, 'points' => $points];
            if ($maskMode === 'refined') {
                $result['editor_input']['selection_points'] = $selectionPoints;
                $result['editor_input']['selection_dimensions'] = $selectionDimensions;
            }
            $sourceKey = 'source_'.$asset->id;
            $mask = $this->importWorkerArtifact($visualization, $result['job_id'], data_get($result, 'segmentation.artifact'), 'segmentation_mask', 'paint-mask.png', 'image/png', $sourceKey);
            abort_if($preview && ! $mask, 502, 'A prévia não contém a máscara aplicada.');
            $this->importWorkerArtifact($visualization, $result['job_id'], data_get($result, 'segmentation.overlay_artifact'), 'vision_overlay', 'paint-mask-overlay.png', 'image/png', $sourceKey);
            $comparison = $this->importWorkerArtifact($visualization, $result['job_id'], data_get($result, 'comparison_artifact'), 'photo_comparison', 'antes-depois-'.$asset->id.'.png', 'image/png', $sourceKey);
            $simulation = $this->importWorkerArtifact($visualization, $result['job_id'], data_get($result, 'simulation_artifact'), $preview ? 'photo_preview' : 'photo_simulation', 'simulacao-'.$asset->id.'.png', 'image/png', $sourceKey);
            abort_unless($simulation, 502, 'O worker não devolveu a imagem da simulação.');
            $result['assets'] = ['simulation_id' => $simulation->id, 'comparison_id' => $comparison?->id, 'mask_id' => $mask?->id];
            $job->fill(['worker_job_id' => $result['job_id'], 'result' => $result, 'status' => 'completed', 'completed_at' => now()])->save();

            if ($preview) {
                return $this->photoSimulationPayload($visualization->fresh('assets'), $simulation, $result, $subjectType, $subjectId);
            }

            return $this->payload($visualization->fresh('assets', 'versions'), $user, $subjectType, $subjectId);
        } catch (\Throwable $exception) {
            $job->fill(['status' => 'failed', 'error' => 'Falha ao compor a simulação fotográfica.', 'completed_at' => now()])->save();
            if ($exception instanceof RequestException || $exception instanceof ConnectionException) {
                abort(502, 'O serviço de pintura está indisponível. A versão não foi salva. Tente novamente.');
            }
            throw $exception;
        }
    }

    private function photoAssetUrl(string $subjectType, int $subjectId, ?int $assetId): ?string
    {
        return $assetId ? '/api/visualizations/'.$subjectType.'/'.$subjectId.'/assets/'.$assetId.'/file' : null;
    }

    private function photoSimulationPayload(PropertyVisualization $visualization, PropertyVisualizationAsset $simulation, array $result, string $subjectType, int $subjectId, bool $metadataAvailable = true): array
    {
        $sourceId = (int) substr((string) $simulation->room_key, 7);
        $points = data_get($result, 'editor_input.selection_points');
        $dimensions = data_get($result, 'editor_input.selection_dimensions', data_get($result, 'segmentation.dimensions'));
        // Recover earlier refined versions from their authorized immutable neural selection.
        if ($points === null && data_get($result, 'editor_input.mask_mode') === 'refined' && ($baseId = data_get($result, 'editor_input.base_mask_asset_id'))) {
            $selection = $visualization->processingJobs()->where('kind', 'photo_selection')->where('status', 'completed')
                ->where('result->assets->mask_id', $baseId)->get()
                ->first(fn (PropertyProcessingJob $selection) => in_array($sourceId, $selection->input_asset_ids ?? [], true));
            if ($selection) {
                $points = $this->photoPromptPoints($selection->result);
                $dimensions = data_get($selection->result, 'segmentation.dimensions');
            }
        }
        if ($points === null) {
            $points = $this->photoPromptPoints($result);
        }
        $assetUrl = fn ($id) => $id && $visualization->assets->contains('id', (int) $id) ? $this->photoAssetUrl($subjectType, $subjectId, (int) $id) : null;

        return [
            'id' => 'asset-'.$simulation->id,
            'status' => 'completed',
            'preview_id' => $simulation->kind === 'photo_preview' ? $simulation->id : null,
            'source_asset_id' => $sourceId,
            'source_url' => $assetUrl($sourceId),
            'result_url' => $assetUrl($simulation->id),
            'comparison_url' => $assetUrl(data_get($result, 'assets.comparison_id')),
            'mask_url' => $assetUrl(data_get($result, 'assets.mask_id')),
            'base_mask_url' => $assetUrl(data_get($result, 'editor_input.base_mask_asset_id')),
            'base_mask_asset_id' => data_get($result, 'editor_input.base_mask_asset_id'),
            'mask_mode' => data_get($result, 'editor_input.mask_mode', 'sam'),
            'mask_strokes' => data_get($result, 'editor_input.mask_strokes', []),
            'finish' => data_get($result, 'editor_input.finish', data_get($result, 'composition.finish', 'matte')),
            'metadata_available' => $metadataAvailable,
            'color' => data_get($result, 'composition.color', '#d8c6ae'),
            'opacity' => data_get($result, 'composition.opacity', 0.72),
            'material' => data_get($result, 'composition.material', 'paint'),
            'points' => $points,
            'dimensions' => $dimensions,
            'created_at' => optional($simulation->created_at)->toISOString(),
            'label' => ($simulation->kind === 'photo_preview' ? 'Prévia ' : 'Simulação ').$simulation->id,
        ];
    }

    private function photoPromptPoints(array $result): array
    {
        $points = data_get($result, 'editor_input.points');
        if (is_array($points)) {
            return $points;
        }
        $labels = data_get($result, 'segmentation.prompt_labels', []);

        return collect(data_get($result, 'segmentation.prompt_points', []))->map(fn ($point, $index) => ['x' => (float) ($point[0] ?? 0), 'y' => (float) ($point[1] ?? 0), 'label' => (int) ($labels[$index] ?? 1)])->all();
    }

    public function confirmPhotoPreview(User $user, string $subjectType, int $subjectId, int $previewId): array
    {
        $this->entitlement->assert($user, 'renovation_simulation');
        $visualization = $this->show($user, $subjectType, $subjectId);

        return DB::transaction(function () use ($visualization, $subjectType, $subjectId, $previewId): array {
            $asset = $visualization->assets()->whereKey($previewId)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($asset->kind, ['photo_preview', 'photo_simulation'], true), 409, 'Esta prévia não pode ser confirmada.');
            $job = $visualization->processingJobs()->whereIn('kind', ['photo_preview', 'photo_simulation'])->where('status', 'completed')
                ->where('result->assets->simulation_id', $previewId)->lockForUpdate()->firstOrFail();
            abort_unless(in_array((int) substr((string) $asset->room_key, 7), $job->input_asset_ids ?? [], true), 409);
            $asset->update(['kind' => 'photo_simulation']);
            $job->update(['kind' => 'photo_simulation']);

            return $this->photoSimulationPayload($visualization->fresh('assets'), $asset, $job->result, $subjectType, $subjectId);
        });
    }

    public function discardPhotoPreview(User $user, string $subjectType, int $subjectId, int $previewId): void
    {
        $this->entitlement->assert($user, 'renovation_simulation');
        $visualization = $this->show($user, $subjectType, $subjectId);
        DB::transaction(function () use ($visualization, $previewId): void {
            $asset = $visualization->assets()->whereKey($previewId)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($asset->kind, ['photo_preview', 'photo_discarded'], true), 409, 'Uma versão salva não pode ser descartada como prévia.');
            $job = $visualization->processingJobs()->where('kind', 'photo_preview')->where('result->assets->simulation_id', $previewId)->lockForUpdate()->firstOrFail();
            $asset->update(['kind' => 'photo_discarded']);
            $job->update(['status' => 'cancelled']);
        });
    }

    private function validateMaskBytes(string $bytes): void
    {
        $size = @getimagesizefromstring($bytes);
        abort_unless($size && ($size[2] ?? null) === IMAGETYPE_PNG && strlen($bytes) >= 26
            && ord($bytes[25]) === 0 && $size[0] > 0 && $size[1] > 0 && $size[0] <= 20000 && $size[1] <= 20000, 422, 'A máscara base deve ser PNG em escala de cinza válido.');
    }

    private function startPhotoProcessingJob(PropertyVisualization $visualization, User $user, string $kind, array $assetIds): PropertyProcessingJob
    {
        return DB::transaction(function () use ($visualization, $user, $kind, $assetIds): PropertyProcessingJob {
            PropertyVisualization::query()->whereKey($visualization->id)->lockForUpdate()->firstOrFail();
            abort_if($visualization->processingJobs()->whereIn('kind', ['photo_selection', 'photo_preview', 'photo_simulation'])->where('status', 'processing')
                ->where('started_at', '>=', now()->subSeconds((int) config('ai.worker.processing_timeout', 1800) + 60))->exists(), 409, 'Aguarde o processamento fotográfico em andamento.');

            return $this->startProcessingJob($visualization, $user, $kind, $assetIds);
        });
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
        $bytes = $this->worker->artifact($jobId, $name, $this->workerScope($visualization));
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

    private function workerScope(PropertyVisualization $visualization): string
    {
        $token = (string) config('ai.worker.token');
        abort_if($token === '', 503, 'Credencial do worker de IA não configurada.');

        return hash_hmac('sha256', $visualization->tenant_id.':'.$visualization->id, $token);
    }

    private function workerIdempotencyKey(PropertyProcessingJob $job): string
    {
        $hex = hash('sha256', $job->tenant_id.':'.$job->id.':photogrammetry');

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-4'.substr($hex, 13, 3).'-a'.substr($hex, 17, 3).'-'.substr($hex, 20, 12);
    }

    public function createRoom(User $user, string $subjectType, int $subjectId, array $input): array
    {
        $visualization = $this->show($user, $subjectType, $subjectId);
        DB::transaction(function () use ($visualization, $user, $input): void {
            $locked = PropertyVisualization::query()->lockForUpdate()->findOrFail($visualization->id);
            $graph = $locked->scene_graph ?? [];
            abort_if(count($graph['rooms'] ?? []) >= 12, 422, 'O projeto já possui 12 ambientes.');
            $next = $this->graphs->addRoom($graph, $input['type'], $input['dimensions'], $input['furnished'] ?? false);
            if ($graph !== []) {
                $locked->versions()->create(['user_id' => $user->id, 'label' => 'Antes de adicionar ambiente', 'graph' => $graph]);
            }
            $locked->fill(['scene_graph' => $next, 'scene_status' => 'ready'])->save();
            if ($graph === []) {
                $locked->versions()->create(['user_id' => $user->id, 'label' => 'original', 'graph' => $next]);
            }
        });

        return $this->payload($visualization->fresh('assets', 'versions'), $user, $subjectType, $subjectId);
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
        if (! is_array($visualization->scene_graph)) {
            $this->rebuild($user, $subjectType, $subjectId);
        }

        return DB::transaction(function () use ($visualization, $user, $subjectType, $subjectId, $message, $selectedId): array {
            $visualization = PropertyVisualization::query()->lockForUpdate()->findOrFail($visualization->id);
            $graph = $visualization->scene_graph;
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
        });
    }

    public function applyActions(User $user, string $subjectType, int $subjectId, array $actions): array
    {
        $visualization = $this->show($user, $subjectType, $subjectId);
        if (! is_array($visualization->scene_graph)) {
            $this->rebuild($user, $subjectType, $subjectId);
        }
        DB::transaction(function () use ($visualization, $actions): void {
            $locked = PropertyVisualization::query()->lockForUpdate()->findOrFail($visualization->id);
            try {
                $next = $this->graphs->apply($locked->scene_graph ?? [], $actions);
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['actions' => $exception->getMessage()]);
            }
            $locked->fill(['scene_graph' => $next])->save();
        });

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

    public function previewVersion(User $user, string $subjectType, int $subjectId, int $versionId): array
    {
        $visualization = $this->show($user, $subjectType, $subjectId);
        $version = $visualization->versions()->whereKey($versionId)->firstOrFail();
        // In-memory presentation only; comparison never overwrites the saved scene.
        $visualization->scene_graph = $version->graph;

        return ['label' => $version->label, 'graph' => $this->withPhotoUrls($visualization, $subjectType, $subjectId)];
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
