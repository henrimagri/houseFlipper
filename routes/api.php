<?php

use App\Http\Controllers\Api\AiConversationController;
use App\Http\Controllers\Api\AIPropertySearchController;
use App\Http\Controllers\Api\AgencyDashboardController;
use App\Http\Controllers\Api\AgencyWorkspaceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuctionController;
use App\Http\Controllers\Api\AgencyContractController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\BrokerDashboardController;
use App\Http\Controllers\Api\BrokerSiteController;
use App\Http\Controllers\Api\CaptureController;
use App\Http\Controllers\Api\CategoriaDespesaController;
use App\Http\Controllers\Api\CommercialListingController;
use App\Http\Controllers\Api\CrmContactController;
use App\Http\Controllers\Api\MapListingsController;
use App\Http\Controllers\Api\PublicBrokerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FlipperDashboardController;
use App\Http\Controllers\Api\PlatformAdminController;
use App\Http\Controllers\Api\PublicAgencyController;
use App\Http\Controllers\Api\DataSourceStatusController;
use App\Http\Controllers\Api\DespesaController;
use App\Http\Controllers\Api\DocumentoController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\EventoCalendarioController;
use App\Http\Controllers\Api\FinancialLedgerController;
use App\Http\Controllers\Api\FornecedorController;
use App\Http\Controllers\Api\InvestmentAnalysisController;
use App\Http\Controllers\Api\ItbiImportController;
use App\Http\Controllers\Api\NotificationCenterController;
use App\Http\Controllers\Api\PersonalBoardTaskController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PublicCatalogController;
use App\Http\Controllers\Api\SaasLeadController;
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

Route::get('/public/brokers/{slug}', [PublicBrokerController::class, 'show'])->middleware('throttle:60,1');
Route::get('/public/listings/{listingSlug}', [PublicBrokerController::class, 'listing'])->middleware('throttle:60,1');
Route::get('/public/listings/{listingSlug}/photos/{photo}', [PublicBrokerController::class, 'photo'])->middleware('throttle:60,1')->whereNumber('photo');
Route::post('/public/listings/{listingSlug}/leads', [PublicBrokerController::class, 'lead'])->middleware('throttle:public-leads');
Route::get('/public/agencies/{slug}', [PublicAgencyController::class, 'show'])->middleware('throttle:60,1');
Route::get('/public/catalog', PublicCatalogController::class)->middleware('throttle:60,1');
Route::post('/public/saas-leads', [SaasLeadController::class, 'store'])->middleware('throttle:saas-leads');
Route::post('/billing/webhooks/{provider}', [BillingController::class, 'webhook'])->middleware('throttle:billing-webhook');

Route::middleware(['auth:sanctum', 'active-account'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
    Route::post('/auth/logout-others', [AuthController::class, 'logoutOthers']);
    Route::get('/auth/sessions', [AuthController::class, 'sessions']);
    Route::delete('/auth/sessions/{tokenId}', [AuthController::class, 'revokeSession'])->whereNumber('tokenId');
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/avatar', [AuthController::class, 'updateAvatar'])->middleware('throttle:10,1');
    Route::put('/auth/password', [AuthController::class, 'updatePassword'])->middleware('throttle:6,1');
    Route::post('/auth/product', [AuthController::class, 'switchProduct']);
    Route::post('/auth/email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:verification');
    Route::get('/onboarding/{product}', [OnboardingController::class, 'show']);
    Route::put('/onboarding/{product}', [OnboardingController::class, 'update']);
});

Route::middleware(['auth:sanctum', 'active-account', 'verified-account'])->group(function () {
    Route::get('/products', function (\Illuminate\Http\Request $request, \App\Services\Ai\ProductEntitlement $entitlement) {
        return response()->json(['data' => $entitlement->catalogPayload($request->user())]);
    });
    Route::middleware('product:house_flipper')->group(function () {
        Route::get('/dashboard', DashboardController::class);
        Route::get('/house-flipper/dashboard', FlipperDashboardController::class);
    });
    Route::middleware('product:corretor')->group(function () {
        Route::get('/corretor/dashboard', BrokerDashboardController::class);
        Route::get('/personal-board/tasks', [PersonalBoardTaskController::class, 'index']);
        Route::post('/personal-board/tasks', [PersonalBoardTaskController::class, 'store']);
        Route::patch('/personal-board/tasks/{personalBoardTask}', [PersonalBoardTaskController::class, 'update'])->whereNumber('personalBoardTask');
        Route::delete('/personal-board/tasks/{personalBoardTask}', [PersonalBoardTaskController::class, 'destroy'])->whereNumber('personalBoardTask');
    });
    Route::middleware('product:imobiliaria')->group(function () {
        Route::get('/imobiliaria/dashboard', AgencyDashboardController::class);
        Route::get('/imobiliaria/organization', [AgencyWorkspaceController::class, 'organization']);
        Route::post('/imobiliaria/members', [AgencyWorkspaceController::class, 'storeMember']);
        Route::post('/imobiliaria/branches', [AgencyWorkspaceController::class, 'storeBranch']);
        Route::get('/imobiliaria/deals', [AgencyWorkspaceController::class, 'deals']);
        Route::post('/imobiliaria/deals', [AgencyWorkspaceController::class, 'storeDeal']);
        Route::get('/imobiliaria/deals/{deal}', [AgencyWorkspaceController::class, 'showDeal'])->whereNumber('deal');
        Route::patch('/imobiliaria/deals/{deal}', [AgencyWorkspaceController::class, 'updateDeal'])->whereNumber('deal');
        Route::delete('/imobiliaria/deals/{deal}', [AgencyWorkspaceController::class, 'archiveDeal'])->whereNumber('deal');
        Route::patch('/imobiliaria/deals/{deal}/stage', [AgencyWorkspaceController::class, 'moveDeal'])->whereNumber('deal');
        Route::get('/imobiliaria/tickets', [AgencyWorkspaceController::class, 'tickets']);
        Route::post('/imobiliaria/tickets', [AgencyWorkspaceController::class, 'storeTicket']);
        Route::get('/imobiliaria/tickets/{ticket}', [AgencyWorkspaceController::class, 'showTicket'])->whereNumber('ticket');
        Route::patch('/imobiliaria/tickets/{ticket}', [AgencyWorkspaceController::class, 'updateTicket'])->whereNumber('ticket');
        Route::post('/imobiliaria/tickets/{ticket}/messages', [AgencyWorkspaceController::class, 'messageTicket'])->whereNumber('ticket');
        Route::post('/imobiliaria/tickets/{ticket}/convert', [AgencyWorkspaceController::class, 'convertTicket'])->whereNumber('ticket');
        Route::post('/imobiliaria/roulette', [AgencyWorkspaceController::class, 'roulette']);
        Route::get('/imobiliaria/roulette', [AgencyWorkspaceController::class, 'rouletteConfig']);
        Route::put('/imobiliaria/roulette', [AgencyWorkspaceController::class, 'updateRouletteConfig']);
        Route::get('/imobiliaria/assignments', [AgencyWorkspaceController::class, 'assignments']);
        Route::get('/imobiliaria/commissions', [AgencyWorkspaceController::class, 'commissionRules']);
        Route::patch('/imobiliaria/commissions/{commission}', [AgencyWorkspaceController::class, 'updateCommission'])->whereNumber('commission');
        Route::post('/imobiliaria/commission-rules', [AgencyWorkspaceController::class, 'storeCommissionRule']);
        Route::post('/imobiliaria/deals/{deal}/commissions', [AgencyWorkspaceController::class, 'projectCommission'])->whereNumber('deal');
        Route::get('/imobiliaria/site', [AgencyWorkspaceController::class, 'agencySite']);
        Route::put('/imobiliaria/site', [AgencyWorkspaceController::class, 'updateAgencySite']);
        Route::get('/imobiliaria/contracts', [AgencyContractController::class, 'index']);
        Route::post('/imobiliaria/contracts', [AgencyContractController::class, 'store']);
        Route::patch('/imobiliaria/contracts/{contract}', [AgencyContractController::class, 'update'])->whereNumber('contract');
        Route::delete('/imobiliaria/contracts/{contract}', [AgencyContractController::class, 'destroy'])->whereNumber('contract');
    });
    Route::middleware('product:corretor,imobiliaria')->group(function () {
        Route::get('/proposals', [AgencyWorkspaceController::class, 'proposals']);
        Route::post('/proposals', [AgencyWorkspaceController::class, 'storeProposal']);
    });
    Route::middleware('platform-admin')->prefix('platform')->group(function () {
        Route::get('/overview', [PlatformAdminController::class, 'overview']);
        Route::get('/saas-leads', [SaasLeadController::class, 'index']);
        Route::patch('/saas-leads/{lead}', [SaasLeadController::class, 'update'])->whereNumber('lead');
    });
    Route::get('/notifications', NotificationCenterController::class);
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
    Route::get('/eventos-calendario', [EventoCalendarioController::class, 'index']);
    Route::post('/eventos-calendario', [EventoCalendarioController::class, 'store']);
    Route::get('/eventos-calendario/{id}', [EventoCalendarioController::class, 'show']);
    Route::match(['put', 'patch'], '/eventos-calendario/{id}', [EventoCalendarioController::class, 'update']);
    Route::delete('/eventos-calendario/{id}', [EventoCalendarioController::class, 'destroy']);

    Route::middleware('product:house_flipper')->group(function () {
    Route::get('/auctions', [AuctionController::class, 'index']);
    Route::get('/auctions/imports', [AuctionController::class, 'imports']);
    Route::post('/auctions/import', [AuctionController::class, 'import'])->middleware('throttle:10,1');
    Route::post('/auctions/sync', [AuctionController::class, 'sync'])->middleware('throttle:6,1');
    Route::get('/reports/ledger', FinancialLedgerController::class);
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
    Route::apiResource('vendas', VendaController::class);

    Route::get('/propriedades/{id}/roi', [VendaController::class, 'roi']);
    });

    Route::middleware('product:corretor,imobiliaria')->group(function () {
    Route::get('/opportunities/{opportunity}/capture', [CaptureController::class, 'showForOpportunity']);
    Route::post('/opportunities/{opportunity}/captures', [CaptureController::class, 'start']);
    Route::post('/captures/{capture}/grant', [CaptureController::class, 'grant']);
    Route::post('/captures/{capture}/revoke', [CaptureController::class, 'revoke']);
    Route::get('/commercial-listings', [CommercialListingController::class, 'index']);
    Route::post('/commercial-listings', [CommercialListingController::class, 'store']);
    Route::get('/commercial-listings/{commercialListing}', [CommercialListingController::class, 'show'])->whereNumber('commercialListing');
    Route::patch('/commercial-listings/{commercialListing}', [CommercialListingController::class, 'update'])->whereNumber('commercialListing');
    Route::post('/commercial-listings/{commercialListing}/publish', [CommercialListingController::class, 'publish'])->whereNumber('commercialListing');
    Route::post('/commercial-listings/{commercialListing}/unpublish', [CommercialListingController::class, 'unpublish'])->whereNumber('commercialListing');
    Route::post('/commercial-listings/{commercialListing}/photos', [CommercialListingController::class, 'storePhoto'])->whereNumber('commercialListing');
    Route::get('/commercial-listings/{commercialListing}/photos/{photo}/file', [CommercialListingController::class, 'photoFile'])->whereNumber('commercialListing')->whereNumber('photo');
    Route::post('/commercial-listings/{commercialListing}/copy', [CommercialListingController::class, 'copy'])->whereNumber('commercialListing');
    Route::get('/broker-site', [BrokerSiteController::class, 'show']);
    Route::put('/broker-site', [BrokerSiteController::class, 'update']);
    Route::get('/crm/contacts', [CrmContactController::class, 'index']);
    Route::post('/crm/contacts', [CrmContactController::class, 'store']);
    Route::get('/crm/contacts/{crmContact}', [CrmContactController::class, 'show'])->whereNumber('crmContact');
    Route::patch('/crm/contacts/{crmContact}', [CrmContactController::class, 'update'])->whereNumber('crmContact');
    });
    Route::get('/billing/subscription', [BillingController::class, 'show']);
    Route::post('/billing/trial', [BillingController::class, 'trial']);
    Route::post('/billing/cancel', [BillingController::class, 'cancel']);
    Route::post('/billing/checkout', [BillingController::class, 'checkout']);
    Route::get('/maps/listings', MapListingsController::class);
    Route::post('/maps/listings', MapListingsController::class);
    Route::get('/maps/config', [MapListingsController::class, 'config']);
    Route::get('/maps/geocode', [MapListingsController::class, 'geocode'])->middleware('throttle:market-data');
    Route::post('/maps/searches', [MapListingsController::class, 'saveSearch']);

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
    Route::middleware('product:house_flipper')->group(function () {
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
});
