<?php

namespace App\Services\Visualization;

use App\Exceptions\DocumentRejectedException;
use App\Exceptions\DocumentStorageException;
use App\Models\Opportunity;
use App\Models\PropertySceneVersion;
use App\Models\PropertyVisualization;
use App\Models\PropertyVisualizationAsset;
use App\Models\Propriedade;
use App\Models\User;
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
        $model = $visualization->assets->first(fn (PropertyVisualizationAsset $asset) => in_array($asset->kind, ['glb', 'gltf'], true));
        $images = $visualization->assets->whereIn('kind', ['photo', 'floorplan', 'panorama'])->values();
        $hasFloorplan = $visualization->assets->contains(fn (PropertyVisualizationAsset $asset) => $asset->kind === 'floorplan');
        $hasMetadata = (int) ($subject['bedrooms'] ?? 0) > 0 || (float) ($subject['area_m2'] ?? 0) > 0;
        $hasPhotoScene = ($visualization->scene_graph['fidelity'] ?? null) === 'photo_referenced';
        $level = $model
            ? 'a_model'
            : ($hasPhotoScene
                ? 'photo_referenced'
                : ($hasFloorplan ? 'reconstructed_floorplan' : ($hasMetadata ? 'parametric_spec' : 'fallback_gallery')));
        $status = $model ? 'available' : ($hasPhotoScene ? 'photo_referenced' : ($hasMetadata ? 'parametric' : 'insufficient'));
        $illustrative = ! $model;

        if ($visualization->level !== $level || $visualization->status !== $status) {
            $visualization->fill([
                'level' => $level,
                'status' => $status,
                'quality_score' => $model ? 80 : ($hasMetadata ? 45 : 20),
                'origin_label' => $model
                    ? 'Modelo 3D enviado e validado'
                    : ($hasPhotoScene
                        ? 'Cena referenciada nas fotografias deste registro'
                        : ($hasFloorplan
                            ? 'Planta 2D no registro; volume 3D ainda paramétrico'
                            : ($hasMetadata ? 'Maquete paramétrica gerada da ficha' : 'Fallback de galeria'))),
                'limitations' => $model
                    ? 'A navegação usa o arquivo enviado. Não é uma reconstrução fotogramétrica automática. A maquete ao lado lê só metadados deste imóvel.'
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
            'model' => $model ? [
                'id' => $model->id,
                'kind' => $model->kind,
                'name' => $model->original_name,
                'url' => '/api/visualizations/'.$subjectType.'/'.$subjectId.'/assets/'.$model->id.'/file',
            ] : null,
            'gallery' => $images->map(fn (PropertyVisualizationAsset $asset) => [
                'id' => $asset->id,
                'kind' => $asset->kind,
                'name' => $asset->original_name,
                'room_key' => $asset->room_key,
            ])->all(),
            'photo_analyses' => $photos,
            'rooms' => $visualization->rooms ?? $this->inferredRooms($subject),
            'scene_status' => $visualization->scene_status,
            'scene_brief' => $visualization->scene_brief,
            'scene_graph' => $this->withPhotoUrls($visualization, $subjectType, $subjectId),
            'versions' => $visualization->versions()->latest('id')->limit(12)->get(['id', 'label', 'created_at'])->all(),
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

    public function rebuild(User $user, string $subjectType, int $subjectId): array
    {
        $visualization = $this->show($user, $subjectType, $subjectId);
        $visualization->fill(['scene_status' => 'analyzing'])->save();
        $subject = $this->subject($user, $subjectType, $subjectId);
        if ($subjectType === 'opportunity') {
            $this->photos->analyzeOpportunity($user, $subjectId);
            $this->importOpportunityPhotos($user, $visualization, $subjectId);
        } else {
            $this->photos->forget($subjectType, $subjectId);
            $disk = Storage::disk(config('visualizations.disk'));
            foreach ($visualization->assets()->where('kind', 'photo')->get() as $asset) {
                $this->photos->analyzeNamedFile(
                    $user,
                    $subjectType,
                    $subjectId,
                    $asset->original_name,
                    $disk->path($asset->path),
                );
            }
        }
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
        if ($file->getSize() > ((int) config('visualizations.max_kb')) * 1024) {
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
