<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DocumentRejectedException;
use App\Exceptions\DocumentStorageException;
use App\Http\Controllers\Controller;
use App\Models\PlatformControl;
use App\Services\Visualization\PhotoSceneAnalyzer;
use App\Services\Visualization\PropertyVisualizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyVisualizationController extends Controller
{
    public function __construct(
        private readonly PropertyVisualizationService $visualizations,
        private readonly PhotoSceneAnalyzer $photos,
    ) {}

    public function show(Request $request, string $subjectType, int $id): JsonResponse
    {
        $subjectType = $this->subjectType($subjectType);
        $visualization = $this->visualizations->show($request->user(), $subjectType, $id);

        $query = $request->validate(['diagnostic_asset_id' => ['nullable', 'integer', 'min:1']]);
        $diagnosticAssetId = isset($query['diagnostic_asset_id']) ? (int) $query['diagnostic_asset_id'] : null;

        return response()->json(['data' => $this->visualizations->payload(
            $visualization, $request->user(), $subjectType, $id, $diagnosticAssetId,
        )]);
    }

    public function storeAsset(Request $request, string $subjectType, int $id): JsonResponse
    {
        $subjectType = $this->subjectType($subjectType);
        $data = $request->validate([
            'arquivo' => ['required_without:arquivos', 'nullable', 'file'],
            'arquivos' => ['required_without:arquivo', 'nullable', 'array', 'min:1', 'max:120'],
            'arquivos.*' => ['required', 'file'],
            'kind' => ['required', 'in:glb,gltf,photo,floorplan,panorama'],
            'room_key' => ['nullable', 'string', 'max:80'],
        ]);
        try {
            $files = $request->hasFile('arquivos') ? $request->file('arquivos') : [$request->file('arquivo')];
            if (count($files) > 1 && $data['kind'] !== 'photo') {
                return response()->json(['message' => 'O envio múltiplo é permitido somente para fotografias.'], 422);
            }
            $assets = [];
            foreach ($files as $file) {
                $asset = $this->visualizations->storeAsset($request->user(), $subjectType, $id, $file, $data['kind'], $data['room_key'] ?? null);
                $assets[] = ['id' => $asset->id, 'kind' => $asset->kind];
            }
        } catch (DocumentRejectedException) {
            return response()->json(['message' => 'O arquivo foi rejeitado pela validação de segurança.'], 422);
        } catch (DocumentStorageException $exception) {
            Log::warning('Falha ao armazenar visualização 3D', ['exception_type' => $exception::class]);

            return response()->json(['message' => 'Não foi possível armazenar o arquivo. Tente novamente.'], 503);
        }

        return response()->json(['data' => count($assets) === 1 ? $assets[0] : $assets], 201);
    }

    public function file(Request $request, string $subjectType, int $id, int $assetId): StreamedResponse|JsonResponse
    {
        return $this->visualizations->stream($request->user(), $this->subjectType($subjectType), $id, $assetId);
    }

    public function analyzePhotos(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->photos->analyzeOpportunity($request->user(), $id)]);
    }

    public function rebuild(Request $request, string $subjectType, int $id): JsonResponse
    {
        return response()->json(['data' => $this->visualizations->rebuild($request->user(), $this->subjectType($subjectType), $id)]);
    }

    public function analyzeNeural(Request $request, string $subjectType, int $id): JsonResponse
    {
        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'min:1'],
            'points' => ['nullable', 'array', 'max:8'],
            'points.*' => ['required', 'array', 'size:2'],
            'points.*.*' => ['required', 'numeric', 'min:0', 'max:20000'],
        ]);

        return response()->json(['data' => $this->visualizations->analyzeNeural(
            $request->user(),
            $this->subjectType($subjectType),
            $id,
            (int) $data['asset_id'],
            $data['points'] ?? [],
        )]);
    }

    public function selectPhoto(Request $request, string $subjectType, int $id): JsonResponse
    {
        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'min:1'],
            'points' => ['required', 'array', 'min:1', 'max:16'],
            'points.*' => ['required', 'array:x,y,label'],
            'points.*.x' => ['required', 'numeric', 'between:0,20000'],
            'points.*.y' => ['required', 'numeric', 'between:0,20000'],
            'points.*.label' => ['required', 'integer', 'in:0,1'],
        ]);
        if (! collect($data['points'])->contains('label', 1)) {
            throw ValidationException::withMessages(['points' => 'Inclua ao menos um ponto positivo para selecionar a superfície.']);
        }

        return response()->json(['data' => $this->visualizations->selectPhoto($request->user(), $this->subjectType($subjectType), $id, (int) $data['asset_id'], $data['points'])]);
    }

    public function confirmPhotoPreview(Request $request, string $subjectType, int $id, int $previewId): JsonResponse
    {
        return response()->json(['data' => $this->visualizations->confirmPhotoPreview($request->user(), $this->subjectType($subjectType), $id, $previewId)]);
    }

    public function discardPhotoPreview(Request $request, string $subjectType, int $id, int $previewId): JsonResponse
    {
        $this->visualizations->discardPhotoPreview($request->user(), $this->subjectType($subjectType), $id, $previewId);

        return response()->json(['data' => ['status' => 'cancelled']]);
    }

    public function simulatePhoto(Request $request, string $subjectType, int $id): JsonResponse
    {
        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'min:1'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'opacity' => ['nullable', 'numeric', 'between:0.1,1'],
            'material' => ['nullable', 'string', 'in:paint,wood'],
            'mask_mode' => ['nullable', 'string', 'in:sam,manual,refined'],
            'base_mask_asset_id' => ['required_if:mask_mode,refined', 'nullable', 'integer', 'min:1'],
            'finish' => ['nullable', 'in:matte,satin,semigloss'],
            'preview' => ['sometimes', 'boolean'],
            'mask_strokes' => ['required_if:mask_mode,manual', 'array', 'max:64'],
            'mask_strokes.*' => ['required', 'array:mode,radius,points'],
            'mask_strokes.*.mode' => ['required', 'string', 'in:paint,erase'],
            'mask_strokes.*.radius' => ['required', 'numeric', 'between:0.002,0.15'],
            'mask_strokes.*.points' => ['required', 'array', 'min:1', 'max:256'],
            'mask_strokes.*.points.*' => ['required', 'array:x,y'],
            'mask_strokes.*.points.*.x' => ['required', 'numeric', 'between:0,1'],
            'mask_strokes.*.points.*.y' => ['required', 'numeric', 'between:0,1'],
            'points' => ['nullable', 'array', 'max:16'],
            'points.*.x' => ['required_with:points', 'numeric', 'min:0', 'max:20000'],
            'points.*.y' => ['required_with:points', 'numeric', 'min:0', 'max:20000'],
            'points.*.label' => ['required_with:points', 'integer', 'in:0,1'],
        ]);

        if (($data['mask_mode'] ?? 'sam') === 'manual' && ! collect($data['mask_strokes'] ?? [])->contains('mode', 'paint')) {
            throw ValidationException::withMessages(['mask_strokes' => 'Pinte ao menos uma região na máscara manual.']);
        }
        if (($data['mask_mode'] ?? 'sam') === 'sam' && ! empty($data['mask_strokes'])) {
            throw ValidationException::withMessages(['mask_strokes' => 'Traços manuais exigem o modo manual.']);
        }
        if (($data['mask_mode'] ?? 'sam') === 'manual' && ! empty($data['points'])) {
            throw ValidationException::withMessages(['points' => 'Use somente traços no modo manual.']);
        }
        if (($data['mask_mode'] ?? 'sam') !== 'refined' && ! empty($data['base_mask_asset_id'])) {
            throw ValidationException::withMessages(['base_mask_asset_id' => 'A máscara base exige o modo refinado.']);
        }

        return response()->json(['data' => $this->visualizations->simulatePhoto(
            $request->user(),
            $this->subjectType($subjectType),
            $id,
            (int) $data['asset_id'],
            $data['color'],
            $data['points'] ?? [],
            (float) ($data['opacity'] ?? 0.72),
            $data['material'] ?? 'paint',
            $data['mask_mode'] ?? 'sam',
            $data['mask_strokes'] ?? [],
            isset($data['base_mask_asset_id']) ? (int) $data['base_mask_asset_id'] : null,
            $data['finish'] ?? 'matte',
            (bool) ($data['preview'] ?? false),
        )]);
    }

    public function reconstructPhotogrammetry(Request $request, string $subjectType, int $id): JsonResponse
    {
        $flag = PlatformControl::where('kind', 'flag')->where('key', 'reconstruction_enabled')->first();
        abort_if($flag && ! ($flag->value['enabled'] ?? true), 503, 'Reconstrução temporariamente pausada pela operação. As simulações editáveis continuam disponíveis.');
        $data = $request->validate([
            'asset_ids' => ['required', 'array', 'min:8', 'max:120'],
            'asset_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'capture_confirmed' => ['required', 'accepted'],
            'method' => ['required', 'string', 'in:auto,calibrated,tsdf,dense_colmap'],
        ]);

        return response()->json(['data' => $this->visualizations->reconstructPhotogrammetry(
            $request->user(),
            $this->subjectType($subjectType),
            $id,
            array_map('intval', $data['asset_ids']),
            $data['method'],
        )]);
    }

    public function reconstructionJob(Request $request, string $subjectType, int $id, int $job): JsonResponse
    {
        return response()->json(['data' => $this->visualizations->refreshReconstructionJob(
            $request->user(),
            $this->subjectType($subjectType),
            $id,
            $job,
        )]);
    }

    public function cancelReconstructionJob(Request $request, string $subjectType, int $id, int $job): JsonResponse
    {
        return response()->json(['data' => $this->visualizations->cancelReconstructionJob(
            $request->user(),
            $this->subjectType($subjectType),
            $id,
            $job,
        )]);
    }

    public function command(Request $request, string $subjectType, int $id): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:500'],
            'selected_id' => ['nullable', 'string', 'max:80'],
        ]);
        $result = $this->visualizations->applyCommand(
            $request->user(),
            $this->subjectType($subjectType),
            $id,
            $data['message'],
            $data['selected_id'] ?? null,
        );

        return response()->json(['data' => $result['data'], 'command' => $result['command']]);
    }

    public function createRoom(Request $request, string $subjectType, int $id): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:living,bedroom,kitchen,bathroom,office,outdoor'],
            'furnished' => ['sometimes', 'boolean'],
            'dimensions' => ['required', 'array:width,depth,height'],
            'dimensions.width' => ['required', 'numeric', 'between:1.8,20'],
            'dimensions.depth' => ['required', 'numeric', 'between:1.8,20'],
            'dimensions.height' => ['required', 'numeric', 'between:2,8'],
        ]);

        return response()->json(['data' => $this->visualizations->createRoom($request->user(), $this->subjectType($subjectType), $id, $data)], 201);
    }

    public function mutate(Request $request, string $subjectType, int $id): JsonResponse
    {
        $data = $request->validate([
            'actions' => ['required', 'array', 'min:1', 'max:20'],
            'actions.*.op' => ['required', 'string', 'in:paint_kind,paint_surface,set_reconstruction_material,undo_reconstruction,redo_reconstruction,undo_scene,redo_scene,add_object,remove_object,set_object_color,transform,hide,duplicate,set_active_room,upsert_object,set_lighting,set_room_dimensions,set_project_brief,add_opening,update_opening,remove_opening'],
            'actions.*.side' => ['nullable', 'in:north,south,west,east'],
            'actions.*.width' => ['nullable', 'numeric', 'between:0.3,20'],
            'actions.*.height' => ['nullable', 'numeric', 'between:0.3,8'],
            'actions.*.offset' => ['nullable', 'numeric', 'between:-20,20'],
            'actions.*.sill' => ['nullable', 'numeric', 'between:0,8'],
            'actions.*.hidden' => ['nullable', 'boolean'],
            'actions.*.object' => ['nullable', 'array'],
            'actions.*.id' => ['nullable', 'string', 'max:80'],
            'actions.*.kind' => ['nullable', 'string', 'max:40'],
            'actions.*.room' => ['nullable', 'string', 'max:80'],
            'actions.*.color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'actions.*.material' => ['nullable', 'string', 'max:40'],
            'actions.*.finish' => ['nullable', 'in:matte,satin,gloss'],
            'actions.*.expected_kind' => ['nullable', 'string', 'in:wall_candidate,floor_candidate,ceiling_candidate,horizontal_surface,other_surface'],
            'actions.*.preset' => ['nullable', 'string', 'in:daylight,warm,cool,soft,evening'],
            'actions.*.position' => ['nullable', 'array', 'size:3'],
            'actions.*.position.*' => ['required', 'numeric', 'between:-20,20'],
            'actions.*.rotation' => ['nullable', 'numeric', 'between:-360,360'],
            'actions.*.scale' => ['nullable', 'numeric', 'between:0.4,2.5'],
            'actions.*.dimensions' => ['nullable', 'array'],
            'actions.*.dimensions.width' => ['nullable', 'numeric', 'between:1.8,20'],
            'actions.*.dimensions.depth' => ['nullable', 'numeric', 'between:1.8,20'],
            'actions.*.dimensions.height' => ['nullable', 'numeric', 'between:2,8'],
            'actions.*.category' => ['nullable', 'string', 'in:cost_estimate,presentation,renovation_proposal'],
            'actions.*.brief' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json(['data' => $this->visualizations->applyActions($request->user(), $this->subjectType($subjectType), $id, $data['actions'])]);
    }

    public function storeVersion(Request $request, string $subjectType, int $id): JsonResponse
    {
        $data = $request->validate(['label' => ['required', 'string', 'min:2', 'max:80']]);
        $version = $this->visualizations->saveVersion($request->user(), $this->subjectType($subjectType), $id, $data['label']);

        return response()->json(['data' => ['id' => $version->id, 'label' => $version->label]], 201);
    }

    public function restoreVersion(Request $request, string $subjectType, int $id, int $versionId): JsonResponse
    {
        return response()->json(['data' => $this->visualizations->restoreVersion($request->user(), $this->subjectType($subjectType), $id, $versionId)]);
    }

    public function previewVersion(Request $request, string $subjectType, int $id, int $versionId): JsonResponse
    {
        return response()->json(['data' => $this->visualizations->previewVersion($request->user(), $this->subjectType($subjectType), $id, $versionId)]);
    }

    private function subjectType(string $value): string
    {
        return match ($value) {
            'propriedades', 'propriedade' => 'propriedade',
            'opportunities', 'opportunity', 'leads' => 'opportunity',
            default => abort(404, 'Tipo de registro inválido.'),
        };
    }
}
