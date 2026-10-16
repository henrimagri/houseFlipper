<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AIPropertySearchController;
use App\Http\Controllers\Api\CategoriaDespesaController;
use App\Http\Controllers\Api\DespesaController;
use App\Http\Controllers\Api\InvestmentAnalysisController;
use App\Http\Controllers\Api\ItbiImportController;
use App\Http\Controllers\Api\OpportunityController;
use App\Http\Controllers\Api\PropertyMarketAnalysisController;
use App\Http\Controllers\Api\ProjetoController;
use App\Http\Controllers\Api\PropriedadeController;
use App\Http\Controllers\Api\VendaController;
use App\Services\NeighborhoodContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/ai-property-search', AIPropertySearchController::class);
    Route::post('/property-market-analysis', PropertyMarketAnalysisController::class);
    Route::post('/neighborhood-context', function (Request $request, NeighborhoodContextService $service) {
        $data = $request->validate([
            'selected' => ['required', 'array'],
            'selected.title' => ['nullable', 'string', 'max:255'],
            'selected.address' => ['nullable', 'string', 'max:255'],
            'selected.neighborhood' => ['nullable', 'string', 'max:120'],
            'selected.city' => ['nullable', 'string', 'max:120'],
            'selected.state' => ['nullable', 'string', 'max:2'],
            'selected.area_m2' => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json($service->analyze($data['selected']));
    });
    Route::get('/itbi-imports', [ItbiImportController::class, 'index']);
    Route::post('/itbi-imports/upload', [ItbiImportController::class, 'upload']);
    Route::get('/itbi-imports/upload-limits', fn () => [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'memory_limit' => ini_get('memory_limit'),
    ]);

    Route::apiResource('propriedades', PropriedadeController::class);
    Route::get('/categorias-despesa', [CategoriaDespesaController::class, 'index']);
    Route::apiResource('projetos', ProjetoController::class);
    Route::post('/projetos/{id}/etapas', [ProjetoController::class, 'storeEtapa']);
    Route::patch('/projetos-etapas/{id}', [ProjetoController::class, 'updateEtapa']);
    Route::get('/projetos/{id}/resumo', [ProjetoController::class, 'resumo']);
    Route::apiResource('despesas', DespesaController::class);
    Route::apiResource('vendas', VendaController::class);

    Route::get('/propriedades/{id}/roi', [VendaController::class, 'roi']);

    Route::patch('/opportunities/{opportunity}/favorite', [OpportunityController::class, 'favorite']);
    Route::patch('/opportunities/{opportunity}/archive', [OpportunityController::class, 'archive']);
    Route::patch('/opportunities/{opportunity}/discard', [OpportunityController::class, 'discard']);
    Route::patch('/opportunities/{opportunity}/status', [OpportunityController::class, 'changeStatus']);
    Route::apiResource('opportunities', OpportunityController::class)->names([
        'index' => 'api.opportunities.index',
        'store' => 'api.opportunities.store',
        'show' => 'api.opportunities.show',
        'update' => 'api.opportunities.update',
        'destroy' => 'api.opportunities.destroy',
    ]);
    Route::post('/opportunities/{opportunity}/investment-analysis', [InvestmentAnalysisController::class, 'store']);
    Route::get('/investment-analyses/{investmentAnalysis}', [InvestmentAnalysisController::class, 'show']);
    Route::put('/investment-analyses/{investmentAnalysis}', [InvestmentAnalysisController::class, 'update']);
    Route::put('/investment-analyses/{investmentAnalysis}/financing', [InvestmentAnalysisController::class, 'financing']);
    Route::post('/investment-analyses/{investmentAnalysis}/financing-simulations', [InvestmentAnalysisController::class, 'storeFinancingSimulation']);
    Route::patch('/financing-details/{financingDetail}/select', [InvestmentAnalysisController::class, 'selectFinancingSimulation']);
    Route::delete('/financing-details/{financingDetail}', [InvestmentAnalysisController::class, 'destroyFinancingSimulation']);
    Route::get('/investment-analyses/{investmentAnalysis}/financing-reference', [InvestmentAnalysisController::class, 'financingReference']);
    Route::post('/investment-analyses/{investmentAnalysis}/costs', [InvestmentAnalysisController::class, 'storeCost']);
    Route::put('/investment-costs/{investmentCost}', [InvestmentAnalysisController::class, 'updateCost']);
    Route::delete('/investment-costs/{investmentCost}', [InvestmentAnalysisController::class, 'destroyCost']);
    Route::post('/investment-analyses/{investmentAnalysis}/result', [InvestmentAnalysisController::class, 'result']);
    Route::put('/investment-results/{investmentResult}', [InvestmentAnalysisController::class, 'updateResult']);
});
