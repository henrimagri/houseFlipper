<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DocumentRejectedException;
use App\Exceptions\DocumentStorageException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentoRequest;
use App\Http\Resources\DocumentoResource;
use App\Services\DocumentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoController extends Controller
{
    public function __construct(private readonly DocumentoService $service) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->listar($request->only(['busca', 'projeto_id', 'categoria', 'status', 'per_page']));

        return response()->json(DocumentoResource::collection($paginator)->response()->getData(true));
    }

    public function store(DocumentoRequest $request): JsonResponse
    {
        try {
            $documento = $this->service->criar($request->validated(), $request->file('arquivo'), $request->user());
        } catch (DocumentRejectedException) {
            return response()->json(['message' => 'O documento foi bloqueado pela verificação de segurança.'], 422);
        } catch (DocumentStorageException $exception) {
            Log::warning('Falha ao armazenar documento', ['exception_type' => $exception::class]);

            return response()->json(['message' => 'Não foi possível armazenar o documento. Tente novamente.'], 503);
        }

        return (new DocumentoResource($documento))->response()->setStatusCode(201);
    }

    public function show(int $id): JsonResponse
    {
        $documento = $this->service->obter($id);
        if (! $documento) {
            return response()->json(['message' => 'Documento não encontrado'], 404);
        }

        return (new DocumentoResource($documento))->response();
    }

    public function update(DocumentoRequest $request, int $id): JsonResponse
    {
        $documento = $this->service->obter($id);
        if (! $documento) {
            return response()->json(['message' => 'Documento não encontrado'], 404);
        }

        return (new DocumentoResource($this->service->atualizar($documento, $request->validated())))->response();
    }

    public function download(int $id): JsonResponse|StreamedResponse
    {
        $documento = $this->service->obter($id);
        if (! $documento) {
            return response()->json(['message' => 'Documento não encontrado'], 404);
        }
        $disk = Storage::disk(config('documents.disk'));
        if (! $disk->exists($documento->caminho)) {
            return response()->json(['message' => 'Arquivo do documento não encontrado'], 404);
        }

        return $disk->download($documento->caminho, $documento->nome_original, [
            'Content-Type' => $documento->mime_type,
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $documento = $this->service->obter($id);
        if (! $documento) {
            return response()->json(['message' => 'Documento não encontrado'], 404);
        }
        try {
            $this->service->excluir($documento);
        } catch (DocumentStorageException $exception) {
            Log::warning('Falha ao excluir documento privado', [
                'document_id' => $documento->id,
                'exception_type' => $exception::class,
            ]);

            return response()->json(['message' => 'Não foi possível excluir o documento. Tente novamente.'], 503);
        }

        return response()->json(['message' => 'Documento excluído']);
    }
}
