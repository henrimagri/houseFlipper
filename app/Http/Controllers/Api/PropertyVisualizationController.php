<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DocumentRejectedException;
use App\Exceptions\DocumentStorageException;
use App\Http\Controllers\Controller;
use App\Services\Visualization\PhotoSceneAnalyzer;
use App\Services\Visualization\PropertyVisualizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        return response()->json(['data' => $this->visualizations->payload($visualization, $request->user(), $subjectType, $id)]);
    }

    public function storeAsset(Request $request, string $subjectType, int $id): JsonResponse
    {
        $subjectType = $this->subjectType($subjectType);
        $data = $request->validate([
            'arquivo' => ['required', 'file'],
            'kind' => ['required', 'in:glb,gltf,photo,floorplan,panorama'],
            'room_key' => ['nullable', 'string', 'max:80'],
        ]);
        try {
            $asset = $this->visualizations->storeAsset($request->user(), $subjectType, $id, $request->file('arquivo'), $data['kind'], $data['room_key'] ?? null);
        } catch (DocumentRejectedException) {
            return response()->json(['message' => 'O arquivo foi rejeitado pela validação de segurança.'], 422);
        } catch (DocumentStorageException $exception) {
            Log::warning('Falha ao armazenar visualização 3D', ['exception_type' => $exception::class]);

            return response()->json(['message' => 'Não foi possível armazenar o arquivo. Tente novamente.'], 503);
        }

        return response()->json(['data' => ['id' => $asset->id, 'kind' => $asset->kind]], 201);
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

    public function mutate(Request $request, string $subjectType, int $id): JsonResponse
    {
        $data = $request->validate([
            'actions' => ['required', 'array', 'min:1', 'max:20'],
            'actions.*.op' => ['required', 'string', 'in:paint_kind,add_object,remove_object,set_object_color,transform,hide,duplicate,set_active_room,upsert_object'],
            'actions.*.hidden' => ['nullable', 'boolean'],
            'actions.*.object' => ['nullable', 'array'],
            'actions.*.id' => ['nullable', 'string', 'max:80'],
            'actions.*.kind' => ['nullable', 'string', 'max:40'],
            'actions.*.room' => ['nullable', 'string', 'max:80'],
            'actions.*.color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'actions.*.material' => ['nullable', 'string', 'max:40'],
            'actions.*.position' => ['nullable', 'array'],
            'actions.*.rotation' => ['nullable', 'numeric'],
            'actions.*.scale' => ['nullable', 'numeric', 'between:0.4,2.5'],
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

    private function subjectType(string $value): string
    {
        return match ($value) {
            'propriedades', 'propriedade' => 'propriedade',
            'opportunities', 'opportunity', 'leads' => 'opportunity',
            default => abort(404, 'Tipo de registro inválido.'),
        };
    }
}
