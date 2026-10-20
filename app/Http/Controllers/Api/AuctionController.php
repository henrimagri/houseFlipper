<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuctionImport;
use App\Models\AuctionListing;
use App\Services\Marketplace\AuctionImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuctionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable','string','max:120'], 'state' => ['nullable','string','size:2'], 'city' => ['nullable','string','max:120'], 'neighborhood' => ['nullable','string','max:120'], 'property_type' => ['nullable','string','max:60'], 'max_price_cents' => ['nullable','integer','min:0'], 'provider' => ['nullable','in:caixa,vendasgov'], 'sale_method' => ['nullable','string','max:120'], 'page' => ['nullable','integer','min:1','max:10000']]);
        $query = AuctionListing::query()->where('status', '!=', 'withdrawn');
        if ($data['q'] ?? null) $query->where(fn ($q) => $q->where('title', 'like', '%'.$data['q'].'%')->orWhere('city', 'like', '%'.$data['q'].'%')->orWhere('neighborhood', 'like', '%'.$data['q'].'%'));
        foreach (['provider', 'sale_method'] as $field) if ($data[$field] ?? null) $query->where($field, $data[$field]);
        if ($data['state'] ?? null) $query->where('state', strtoupper($data['state']));
        foreach (['city', 'neighborhood', 'property_type'] as $field) if ($data[$field] ?? null) $query->where($field, 'like', '%'.$data[$field].'%');
        if ($data['max_price_cents'] ?? null) $query->where('price_cents', '<=', $data['max_price_cents']);
        $page = $query->orderByRaw('auction_at IS NULL, auction_at')->latest('source_updated_at')->paginate(24);
        return response()->json(['data' => collect($page->items())->map(fn ($item) => $this->payload($item)), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()], 'sources' => $this->sources()]);
    }

    public function import(Request $request, AuctionImportService $service): JsonResponse
    {
        $data = $request->validate(['provider' => ['required','in:caixa,vendasgov'], 'file' => ['required','file','max:10240','mimes:csv,txt,xlsx,ods']]);
        $batch = $service->import($request->user(), $data['file'], $data['provider']);
        return response()->json(['data' => $batch], 201);
    }

    public function sync(Request $request, AuctionImportService $service): JsonResponse
    {
        $data = $request->validate(['state' => ['required', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/']]);
        $batch = $service->syncOfficialState($request->user(), strtoupper($data['state']));

        return response()->json(['data' => $batch, 'message' => 'Lista oficial da CAIXA atualizada.'], 201);
    }

    public function imports(Request $request): JsonResponse
    {
        return response()->json(['data' => AuctionImport::query()->where('user_id', $request->user()->id)->latest()->limit(20)->get()]);
    }

    private function sources(): array { return [
        ['id' => 'caixa', 'name' => 'CAIXA — Imóveis à venda', 'url' => 'https://venda-imoveis.caixa.gov.br/sistema/download-lista.asp', 'mode' => 'official_file'],
        ['id' => 'vendasgov', 'name' => 'VendasGov — Imóveis da União', 'url' => 'https://imoveis.vendasgov.serpro.gov.br/leilao', 'notices_url' => 'https://imoveis.vendasgov.serpro.gov.br/editais', 'mode' => 'official_file'],
    ]; }

    private function payload(AuctionListing $item): array { return [
        'id'=>$item->id,'provider'=>$item->provider,'source_id'=>$item->source_id,'title'=>$item->title,'property_type'=>$item->property_type,
        'city'=>$item->city,'state'=>$item->state,'neighborhood'=>$item->neighborhood,'price_cents'=>$item->price_cents,'appraisal_cents'=>$item->appraisal_cents,
        'sale_method'=>$item->sale_method,'auction_at'=>$item->auction_at?->toIso8601String(),'status'=>$item->status,'official_url'=>$item->official_url,'notice_url'=>$item->notice_url,'collected_at'=>$item->collected_at?->toIso8601String(),
    ]; }
}
