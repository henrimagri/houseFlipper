<?php

use App\Http\Controllers\Api\AiConversationController;
use App\Http\Controllers\Api\AIPropertySearchController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoriaDespesaController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DataSourceStatusController;
use App\Http\Controllers\Api\DespesaController;
use App\Http\Controllers\Api\DocumentoController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\EventoCalendarioController;
use App\Http\Controllers\Api\FinancialLedgerController;
use App\Http\Controllers\Api\FornecedorController;
use App\Http\Controllers\Api\InvestmentAnalysisController;
use App\Http\Controllers\Api\ItbiImportController;
use App\Http\Controllers\Api\OpportunityController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PostalCodeController;
use App\Http\Controllers\Api\ProjetoController;
use App\Http\Controllers\Api\PropertyMarketAnalysisController;
use App\Http\Controllers\Api\PropertyVisualizationController;
use App\Http\Controllers\Api\PropriedadeController;
use App\Http\Controllers\Api\TarefaController;
use App\Http\Controllers\Api\VendaController;
use App\Services\NeighborhoodContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/auth/password/forgot', [PasswordResetController::class, 'requestLink'])->middleware('throttle:password-reset-request');
Route::post('/auth/password/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:password-reset');
Route::get('/auth/postal-code/{postalCode}', PostalCodeController::class)->where('postalCode', '[0-9]{8}')->middleware('throttle:30,1');
Route::get('/auth/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed:relative', 'throttle:6,1'])->name('verification.verify');

Route::middleware(['auth:sanctum', 'active-account'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/auth/sessions', [AuthController::class, 'sessions']);
    Route::delete('/auth/sessions/{tokenId}', [AuthController::class, 'revokeSession'])->whereNumber('tokenId');
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:verification');
});

Route::middleware(['auth:sanctum', 'active-account', 'verified-account'])->group(function () {
    Route::get('/dashboard', DashboardController::class);
    Route::get('/reports/ledger', FinancialLedgerController::class);
    Route::get('/data-sources/status', DataSourceStatusController::class);
    Route::get('/ai-property-search', AIPropertySearchController::class)->middleware('throttle:market-data');
    Route::get('/ai/conversations', [AiConversationController::class, 'show']);
    Route::post('/ai/conversations', [AiConversationController::class, 'store'])->middleware('throttle:market-data');
    Route::get('/visualizations/{subjectType}/{id}', [PropertyVisualizationController::class, 'show'])->whereNumber('id');
    Route::post('/visualizations/{subjectType}/{id}/assets', [PropertyVisualizationController::class, 'storeAsset'])->middleware('throttle:visualization-upload')->whereNumber('id');
    Route::get('/visualizations/{subjectType}/{id}/assets/{assetId}/file', [PropertyVisualizationController::class, 'file'])->whereNumber('id')->whereNumber('assetId');
    Route::post('/visualizations/{subjectType}/{id}/rebuild', [PropertyVisualizationController::class, 'rebuild'])->middleware('throttle:visualization-upload')->whereNumber('id');
    Route::post('/visualizations/{subjectType}/{id}/commands', [PropertyVisualizationController::class, 'command'])->middleware('throttle:visualization-upload')->whereNumber('id');
    Route::patch('/visualizations/{subjectType}/{id}/scene', [PropertyVisualizationController::class, 'mutate'])->middleware('throttle:visualization-upload')->whereNumber('id');
    Route::post('/visualizations/{subjectType}/{id}/versions', [PropertyVisualizationController::class, 'storeVersion'])->whereNumber('id');
    Route::post('/visualizations/{subjectType}/{id}/versions/{versionId}/restore', [PropertyVisualizationController::class, 'restoreVersion'])->whereNumber('id')->whereNumber('versionId');
    Route::post('/opportunities/{id}/photo-analysis', [PropertyVisualizationController::class, 'analyzePhotos'])->whereNumber('id');
    Route::post('/property-market-analysis', PropertyMarketAnalysisController::class)->middleware('throttle:market-data');
    Route::post('/neighborhood-context', function (Request $request, NeighborhoodContextService $service) {
        $data = $request->validate([
            'selected' => ['required', 'array:title,address,neighborhood,city,state,area_m2'],
            'selected.title' => ['nullable', 'string', 'max:255'],
            'selected.address' => ['nullable', 'string', 'max:255'],
            'selected.neighborhood' => ['nullable', 'string', 'max:120'],
            'selected.city' => ['nullable', 'string', 'max:120'],
            'selected.state' => ['nullable', 'string', 'max:2'],
            'selected.area_m2' => ['nullable', 'numeric', 'between:0,99999999.99'],
        ]);

        return response()->json($service->analyze($data['selected']));
    })->middleware('throttle:market-data');
    Route::get('/itbi-imports', [ItbiImportController::class, 'index']);
    Route::post('/itbi-imports/upload', [ItbiImportController::class, 'upload'])->middleware(['can:import-itbi', 'throttle:itbi-upload']);
    Route::apiResource('propriedades', PropriedadeController::class);
    Route::get('/categorias-despesa', [CategoriaDespesaController::class, 'index']);
    Route::apiResource('projetos', ProjetoController::class);
    Route::post('/projetos/{id}/etapas', [ProjetoController::class, 'storeEtapa']);
    Route::patch('/projetos-etapas/{id}', [ProjetoController::class, 'updateEtapa']);
    Route::get('/projetos/{id}/resumo', [ProjetoController::class, 'resumo']);
    Route::apiResource('tarefas', TarefaController::class);
    Route::apiResource('despesas', DespesaController::class);
    Route::get('/fornecedores', [FornecedorController::class, 'index']);
    Route::post('/fornecedores', [FornecedorController::class, 'store']);
    Route::get('/fornecedores/{id}', [FornecedorController::class, 'show']);
    Route::match(['put', 'patch'], '/fornecedores/{id}', [FornecedorController::class, 'update']);
    Route::delete('/fornecedores/{id}', [FornecedorController::class, 'destroy']);
    Route::get('/documentos', [DocumentoController::class, 'index']);
    Route::post('/documentos', [DocumentoController::class, 'store'])->middleware('throttle:document-upload');
    Route::get('/documentos/{id}/download', [DocumentoController::class, 'download']);
    Route::get('/documentos/{id}', [DocumentoController::class, 'show']);
    Route::match(['put', 'patch'], '/documentos/{id}', [DocumentoController::class, 'update']);
    Route::delete('/documentos/{id}', [DocumentoController::class, 'destroy']);
    Route::get('/eventos-calendario', [EventoCalendarioController::class, 'index']);
    Route::post('/eventos-calendario', [EventoCalendarioController::class, 'store']);
    Route::get('/eventos-calendario/{id}', [EventoCalendarioController::class, 'show']);
    Route::match(['put', 'patch'], '/eventos-calendario/{id}', [EventoCalendarioController::class, 'update']);
    Route::delete('/eventos-calendario/{id}', [EventoCalendarioController::class, 'destroy']);
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
    Route::post('/investment-analyses/{investmentAnalysis}/project', [InvestmentAnalysisController::class, 'convertToProject']);
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
