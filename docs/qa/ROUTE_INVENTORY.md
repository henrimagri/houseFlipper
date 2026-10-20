# Inventário de rotas

Gerado em 2026-10-19 a partir de `frontend/src/App.tsx` e `php artisan route:list --json`.

## Frontend (112 rotas explícitas)

| Rota | Componente |
|---|---|
| /c/:slug | PublicBroker |
| /c/:slug/:listingSlug | PublicListing |
| /i/:slug | PublicAgency |
| / | Home |
| /solucoes/corretor | SolucoesCorretor |
| /solucoes/imobiliaria | SolucoesImobiliaria |
| /solucoes/house-flipper | SolucoesHouseFlipper |
| /funcionalidades | Funcionalidades |
| /comparar-produtos | CompararProdutos |
| /precos | Precos |
| /sobre | Sobre |
| /contato | Contato |
| /solicitar-demonstracao | SolicitarDemonstracao |
| /ajuda | AjudaPublica |
| /termos | Termos |
| /privacidade | Privacidade |
| /entrar | Login |
| /login | Login |
| /cadastro | Register |
| /recuperar-senha | ForgotPassword |
| /esqueci-senha | ForgotPassword |
| /redefinir-senha | ResetPassword |
| /confirmar-email | VerifyEmail |
| /app/inicio | Inicio |
| /app/produtos | Produtos |
| /app/assinaturas | Assinaturas |
| /app/configuracoes | Configuracoes |
| /app/ajuda | Ajuda |
| /app/onboarding/:product | Onboarding |
| /platform | PlatformHome |
| /platform/leads | SalesLeads |
| /app/corretor/dashboard | CorretorDashboard |
| /app/corretor/pesquisar | Oportunidades |
| /app/corretor/favoritos | Oportunidades |
| /app/corretor/captacao | Oportunidades |
| /app/corretor/carteira | Carteira |
| /app/corretor/carteira/:id | CarteiraFicha |
| /app/corretor/meu-site | SiteCorretor |
| /app/corretor/marketing | Carteira |
| /app/corretor/leads | CrmContatos |
| /app/corretor/leads/:id | CrmContatoFicha |
| /app/corretor/agenda | Calendario |
| /app/corretor/propostas | Propostas |
| /app/corretor/mapa | MapaInteligente |
| /app/corretor/ia | IAInteligente |
| /app/imobiliaria/dashboard | ImobiliariaDashboard |
| /app/imobiliaria/imoveis | Carteira |
| /app/imobiliaria/imoveis/:id | CarteiraFicha |
| /app/imobiliaria/clientes | CrmContatos |
| /app/imobiliaria/clientes/:id | CrmContatoFicha |
| /app/imobiliaria/proprietarios | CrmContatos |
| /app/imobiliaria/proprietarios/:id | CrmContatoFicha |
| /app/imobiliaria/atendimentos | Atendimentos |
| /app/imobiliaria/captacoes | Oportunidades |
| /app/imobiliaria/crm | CrmKanban |
| /app/imobiliaria/crm/:id | CrmDealDetail |
| /app/imobiliaria/roleta | Roleta |
| /app/imobiliaria/propostas | Propostas |
| /app/imobiliaria/equipes | Equipes |
| /app/imobiliaria/filiais | Filiais |
| /app/imobiliaria/comissoes | Comissoes |
| /app/imobiliaria/contratos | Contratos |
| /app/imobiliaria/site | AgencySite |
| /app/imobiliaria/mapa | MapaInteligente |
| /app/imobiliaria/relatorios | ImobiliariaDashboard |
| /app/house-flipper/dashboard | FlipperDashboard |
| /app/house-flipper/oportunidades | Oportunidades |
| /app/house-flipper/ia | IAInteligente |
| /app/house-flipper/ia/analise/:id | AnaliseMercadoOportunidade |
| /app/house-flipper/leiloes | Auctions |
| /app/house-flipper/mapa | MapaInteligente |
| /app/house-flipper/analises | Oportunidades |
| /app/house-flipper/simulador | Financeiro |
| /app/house-flipper/imoveis | AnaliseImovel |
| /app/house-flipper/imoveis/:id/3d | PropertyViewer |
| /app/house-flipper/projetos | Projetos |
| /app/house-flipper/projetos-3d | AnaliseImovel |
| /app/house-flipper/reformas | Obras |
| /app/house-flipper/financeiro | Financeiro |
| /app/house-flipper/despesas | Despesas |
| /app/house-flipper/obras | Obras |
| /app/house-flipper/tarefas | Tarefas |
| /app/house-flipper/fornecedores | Fornecedores |
| /app/house-flipper/documentos | Documentos |
| /app/house-flipper/relatorios | Relatorios |
| /app/house-flipper/calendario | Calendario |
| /app/house-flipper/importar-itbi | ImportarItbi |
| /app/house-flipper/analises-financeiras/:id | CentralFinanceira |
| /app/house-flipper/analises-financeiras/:id/wizard | WizardFinanceiro |
| /inicio | Navigate |
| /leads | Navigate |
| /carteira | Navigate |
| /carteira/:id | RedirectParam |
| /site-corretor | Navigate |
| /crm | Navigate |
| /mapa | Navigate |
| /projetos | Navigate |
| /imoveis | Navigate |
| /imoveis/:id/3d | RedirectParam |
| /obras | Navigate |
| /financeiro | Navigate |
| /despesas | Navigate |
| /tarefas | Navigate |
| /documentos | Navigate |
| /relatorios | Navigate |
| /calendario | Navigate |
| /ia-inteligente | Navigate |
| /analises-financeiras/:id | RedirectParam |
| /analises-financeiras/:id/wizard | RedirectParam |
| /configuracoes | Navigate |
| /app-ajuda | Navigate |
| * | ErrorPage |

## API (185 rotas)

| Método | URI | Ação | Middleware |
|---|---|---|---|
| GET|HEAD | /api/ai-property-search | App\Http\Controllers\Api\AIPropertySearchController | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, Illuminate\Routing\Middleware\ThrottleRequests:market-data |
| GET|HEAD | /api/ai/conversations | App\Http\Controllers\Api\AiConversationController@show | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| POST | /api/ai/conversations | App\Http\Controllers\Api\AiConversationController@store | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, Illuminate\Routing\Middleware\ThrottleRequests:market-data |
| GET|HEAD | /api/auctions | App\Http\Controllers\Api\AuctionController@index | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| POST | /api/auctions/import | App\Http\Controllers\Api\AuctionController@import | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| GET|HEAD | /api/auctions/imports | App\Http\Controllers\Api\AuctionController@imports | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| POST | /api/auth/avatar | App\Http\Controllers\Api\AuthController@updateAvatar | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| POST | /api/auth/email/resend | App\Http\Controllers\Api\EmailVerificationController@resend | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, Illuminate\Routing\Middleware\ThrottleRequests:verification |
| GET|HEAD | /api/auth/email/verify/{id}/{hash} | App\Http\Controllers\Api\EmailVerificationController@verify | api, Illuminate\Routing\Middleware\ValidateSignature:relative, Illuminate\Routing\Middleware\ThrottleRequests:6,1 |
| POST | /api/auth/login | App\Http\Controllers\Api\AuthController@login | api, Illuminate\Routing\Middleware\ThrottleRequests:login |
| POST | /api/auth/logout | App\Http\Controllers\Api\AuthController@logout | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount |
| POST | /api/auth/logout-all | App\Http\Controllers\Api\AuthController@logoutAll | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount |
| POST | /api/auth/logout-others | App\Http\Controllers\Api\AuthController@logoutOthers | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount |
| GET|HEAD | /api/auth/me | App\Http\Controllers\Api\AuthController@me | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount |
| PUT | /api/auth/password | App\Http\Controllers\Api\AuthController@updatePassword | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, Illuminate\Routing\Middleware\ThrottleRequests:6,1 |
| POST | /api/auth/password/forgot | App\Http\Controllers\Api\PasswordResetController@requestLink | api, Illuminate\Routing\Middleware\ThrottleRequests:password-reset-request |
| POST | /api/auth/password/reset | App\Http\Controllers\Api\PasswordResetController@reset | api, Illuminate\Routing\Middleware\ThrottleRequests:password-reset |
| GET|HEAD | /api/auth/postal-code/{postalCode} | App\Http\Controllers\Api\PostalCodeController | api, Illuminate\Routing\Middleware\ThrottleRequests:30,1 |
| POST | /api/auth/product | App\Http\Controllers\Api\AuthController@switchProduct | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount |
| PATCH | /api/auth/profile | App\Http\Controllers\Api\AuthController@updateProfile | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount |
| POST | /api/auth/register | App\Http\Controllers\Api\AuthController@register | api, Illuminate\Routing\Middleware\ThrottleRequests:register |
| GET|HEAD | /api/auth/sessions | App\Http\Controllers\Api\AuthController@sessions | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount |
| DELETE | /api/auth/sessions/{tokenId} | App\Http\Controllers\Api\AuthController@revokeSession | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount |
| POST | /api/billing/cancel | App\Http\Controllers\Api\BillingController@cancel | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| POST | /api/billing/checkout | App\Http\Controllers\Api\BillingController@checkout | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| GET|HEAD | /api/billing/subscription | App\Http\Controllers\Api\BillingController@show | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| POST | /api/billing/trial | App\Http\Controllers\Api\BillingController@trial | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| POST | /api/billing/webhooks/{provider} | App\Http\Controllers\Api\BillingController@webhook | api, Illuminate\Routing\Middleware\ThrottleRequests:billing-webhook |
| GET|HEAD | /api/broker-site | App\Http\Controllers\Api\BrokerSiteController@show | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor,imobiliaria |
| PUT | /api/broker-site | App\Http\Controllers\Api\BrokerSiteController@update | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor,imobiliaria |
| POST | /api/captures/{capture}/grant | App\Http\Controllers\Api\CaptureController@grant | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor,imobiliaria |
| POST | /api/captures/{capture}/revoke | App\Http\Controllers\Api\CaptureController@revoke | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor,imobiliaria |
| GET|HEAD | /api/categorias-despesa | App\Http\Controllers\Api\CategoriaDespesaController@index | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/commercial-listings | App\Http\Controllers\Api\CommercialListingController@index | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor,imobiliaria |
| POST | /api/commercial-listings | App\Http\Controllers\Api\CommercialListingController@store | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor,imobiliaria |
| GET|HEAD | /api/commercial-listings/{commercialListing} | App\Http\Controllers\Api\CommercialListingController@show | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor,imobiliaria |
| PATCH | /api/commercial-listings/{commercialListing} | App\Http\Controllers\Api\CommercialListingController@update | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor,imobiliaria |
| POST | /api/commercial-listings/{commercialListing}/copy | App\Http\Controllers\Api\CommercialListingController@copy | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor,imobiliaria |
| POST | /api/commercial-listings/{commercialListing}/photos | App\Http\Controllers\Api\CommercialListingController@storePhoto | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor,imobiliaria |
| GET|HEAD | /api/commercial-listings/{commercialListing}/photos/{photo}/file | App\Http\Controllers\Api\CommercialListingController@photoFile | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor,imobiliaria |
| POST | /api/commercial-listings/{commercialListing}/publish | App\Http\Controllers\Api\CommercialListingController@publish | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor,imobiliaria |
| POST | /api/commercial-listings/{commercialListing}/unpublish | App\Http\Controllers\Api\CommercialListingController@unpublish | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor,imobiliaria |
| GET|HEAD | /api/corretor/dashboard | App\Http\Controllers\Api\BrokerDashboardController | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor |
| GET|HEAD | /api/crm/contacts | App\Http\Controllers\Api\CrmContactController@index | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor,imobiliaria |
| POST | /api/crm/contacts | App\Http\Controllers\Api\CrmContactController@store | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor,imobiliaria |
| GET|HEAD | /api/crm/contacts/{crmContact} | App\Http\Controllers\Api\CrmContactController@show | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor,imobiliaria |
| PATCH | /api/crm/contacts/{crmContact} | App\Http\Controllers\Api\CrmContactController@update | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor,imobiliaria |
| GET|HEAD | /api/dashboard | App\Http\Controllers\Api\DashboardController | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/data-sources/status | App\Http\Controllers\Api\DataSourceStatusController | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| GET|HEAD | /api/despesas | App\Http\Controllers\Api\DespesaController@index | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| POST | /api/despesas | App\Http\Controllers\Api\DespesaController@store | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/despesas/{despesa} | App\Http\Controllers\Api\DespesaController@show | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| PUT|PATCH | /api/despesas/{despesa} | App\Http\Controllers\Api\DespesaController@update | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| DELETE | /api/despesas/{despesa} | App\Http\Controllers\Api\DespesaController@destroy | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/documentos | App\Http\Controllers\Api\DocumentoController@index | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| POST | /api/documentos | App\Http\Controllers\Api\DocumentoController@store | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper, Illuminate\Routing\Middleware\ThrottleRequests:document-upload |
| GET|HEAD | /api/documentos/{id} | App\Http\Controllers\Api\DocumentoController@show | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| PUT|PATCH | /api/documentos/{id} | App\Http\Controllers\Api\DocumentoController@update | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| DELETE | /api/documentos/{id} | App\Http\Controllers\Api\DocumentoController@destroy | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/documentos/{id}/download | App\Http\Controllers\Api\DocumentoController@download | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/eventos-calendario | App\Http\Controllers\Api\EventoCalendarioController@index | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| POST | /api/eventos-calendario | App\Http\Controllers\Api\EventoCalendarioController@store | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| GET|HEAD | /api/eventos-calendario/{id} | App\Http\Controllers\Api\EventoCalendarioController@show | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| PUT|PATCH | /api/eventos-calendario/{id} | App\Http\Controllers\Api\EventoCalendarioController@update | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| DELETE | /api/eventos-calendario/{id} | App\Http\Controllers\Api\EventoCalendarioController@destroy | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| DELETE | /api/financing-details/{financingDetail} | App\Http\Controllers\Api\InvestmentAnalysisController@destroyFinancingSimulation | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| PATCH | /api/financing-details/{financingDetail}/select | App\Http\Controllers\Api\InvestmentAnalysisController@selectFinancingSimulation | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/fornecedores | App\Http\Controllers\Api\FornecedorController@index | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| POST | /api/fornecedores | App\Http\Controllers\Api\FornecedorController@store | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/fornecedores/{id} | App\Http\Controllers\Api\FornecedorController@show | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| PUT|PATCH | /api/fornecedores/{id} | App\Http\Controllers\Api\FornecedorController@update | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| DELETE | /api/fornecedores/{id} | App\Http\Controllers\Api\FornecedorController@destroy | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/house-flipper/dashboard | App\Http\Controllers\Api\FlipperDashboardController | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/imobiliaria/assignments | App\Http\Controllers\Api\AgencyWorkspaceController@assignments | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| POST | /api/imobiliaria/branches | App\Http\Controllers\Api\AgencyWorkspaceController@storeBranch | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| POST | /api/imobiliaria/commission-rules | App\Http\Controllers\Api\AgencyWorkspaceController@storeCommissionRule | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| GET|HEAD | /api/imobiliaria/commissions | App\Http\Controllers\Api\AgencyWorkspaceController@commissionRules | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| PATCH | /api/imobiliaria/commissions/{commission} | App\Http\Controllers\Api\AgencyWorkspaceController@updateCommission | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| GET|HEAD | /api/imobiliaria/contracts | App\Http\Controllers\Api\AgencyContractController@index | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| POST | /api/imobiliaria/contracts | App\Http\Controllers\Api\AgencyContractController@store | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| PATCH | /api/imobiliaria/contracts/{contract} | App\Http\Controllers\Api\AgencyContractController@update | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| DELETE | /api/imobiliaria/contracts/{contract} | App\Http\Controllers\Api\AgencyContractController@destroy | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| GET|HEAD | /api/imobiliaria/dashboard | App\Http\Controllers\Api\AgencyDashboardController | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| GET|HEAD | /api/imobiliaria/deals | App\Http\Controllers\Api\AgencyWorkspaceController@deals | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| POST | /api/imobiliaria/deals | App\Http\Controllers\Api\AgencyWorkspaceController@storeDeal | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| GET|HEAD | /api/imobiliaria/deals/{deal} | App\Http\Controllers\Api\AgencyWorkspaceController@showDeal | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| PATCH | /api/imobiliaria/deals/{deal} | App\Http\Controllers\Api\AgencyWorkspaceController@updateDeal | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| DELETE | /api/imobiliaria/deals/{deal} | App\Http\Controllers\Api\AgencyWorkspaceController@archiveDeal | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| POST | /api/imobiliaria/deals/{deal}/commissions | App\Http\Controllers\Api\AgencyWorkspaceController@projectCommission | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| PATCH | /api/imobiliaria/deals/{deal}/stage | App\Http\Controllers\Api\AgencyWorkspaceController@moveDeal | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| POST | /api/imobiliaria/members | App\Http\Controllers\Api\AgencyWorkspaceController@storeMember | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| GET|HEAD | /api/imobiliaria/organization | App\Http\Controllers\Api\AgencyWorkspaceController@organization | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| POST | /api/imobiliaria/roulette | App\Http\Controllers\Api\AgencyWorkspaceController@roulette | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| GET|HEAD | /api/imobiliaria/roulette | App\Http\Controllers\Api\AgencyWorkspaceController@rouletteConfig | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| PUT | /api/imobiliaria/roulette | App\Http\Controllers\Api\AgencyWorkspaceController@updateRouletteConfig | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| GET|HEAD | /api/imobiliaria/site | App\Http\Controllers\Api\AgencyWorkspaceController@agencySite | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| PUT | /api/imobiliaria/site | App\Http\Controllers\Api\AgencyWorkspaceController@updateAgencySite | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| GET|HEAD | /api/imobiliaria/tickets | App\Http\Controllers\Api\AgencyWorkspaceController@tickets | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| POST | /api/imobiliaria/tickets | App\Http\Controllers\Api\AgencyWorkspaceController@storeTicket | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| GET|HEAD | /api/imobiliaria/tickets/{ticket} | App\Http\Controllers\Api\AgencyWorkspaceController@showTicket | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| PATCH | /api/imobiliaria/tickets/{ticket} | App\Http\Controllers\Api\AgencyWorkspaceController@updateTicket | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| POST | /api/imobiliaria/tickets/{ticket}/convert | App\Http\Controllers\Api\AgencyWorkspaceController@convertTicket | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| POST | /api/imobiliaria/tickets/{ticket}/messages | App\Http\Controllers\Api\AgencyWorkspaceController@messageTicket | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:imobiliaria |
| GET|HEAD | /api/investment-analyses/{investmentAnalysis} | App\Http\Controllers\Api\InvestmentAnalysisController@show | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| PUT | /api/investment-analyses/{investmentAnalysis} | App\Http\Controllers\Api\InvestmentAnalysisController@update | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| POST | /api/investment-analyses/{investmentAnalysis}/costs | App\Http\Controllers\Api\InvestmentAnalysisController@storeCost | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| PUT | /api/investment-analyses/{investmentAnalysis}/financing | App\Http\Controllers\Api\InvestmentAnalysisController@financing | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/investment-analyses/{investmentAnalysis}/financing-reference | App\Http\Controllers\Api\InvestmentAnalysisController@financingReference | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| POST | /api/investment-analyses/{investmentAnalysis}/financing-simulations | App\Http\Controllers\Api\InvestmentAnalysisController@storeFinancingSimulation | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| POST | /api/investment-analyses/{investmentAnalysis}/project | App\Http\Controllers\Api\InvestmentAnalysisController@convertToProject | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| POST | /api/investment-analyses/{investmentAnalysis}/result | App\Http\Controllers\Api\InvestmentAnalysisController@result | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| PUT | /api/investment-costs/{investmentCost} | App\Http\Controllers\Api\InvestmentAnalysisController@updateCost | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| DELETE | /api/investment-costs/{investmentCost} | App\Http\Controllers\Api\InvestmentAnalysisController@destroyCost | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| PUT | /api/investment-results/{investmentResult} | App\Http\Controllers\Api\InvestmentAnalysisController@updateResult | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/itbi-imports | App\Http\Controllers\Api\ItbiImportController@index | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| POST | /api/itbi-imports/upload | App\Http\Controllers\Api\ItbiImportController@upload | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper, Illuminate\Routing\Middleware\ThrottleRequests:itbi-upload, Illuminate\Auth\Middleware\Authorize:import-itbi |
| GET|HEAD | /api/maps/config | App\Http\Controllers\Api\MapListingsController@config | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| GET|HEAD | /api/maps/geocode | App\Http\Controllers\Api\MapListingsController@geocode | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, Illuminate\Routing\Middleware\ThrottleRequests:market-data |
| GET|HEAD | /api/maps/listings | App\Http\Controllers\Api\MapListingsController | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| POST | /api/maps/listings | App\Http\Controllers\Api\MapListingsController | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| POST | /api/maps/searches | App\Http\Controllers\Api\MapListingsController@saveSearch | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| POST | /api/neighborhood-context | Closure | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, Illuminate\Routing\Middleware\ThrottleRequests:market-data |
| GET|HEAD | /api/notifications | App\Http\Controllers\Api\NotificationCenterController | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| GET|HEAD | /api/onboarding/{product} | App\Http\Controllers\Api\OnboardingController@show | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount |
| PUT | /api/onboarding/{product} | App\Http\Controllers\Api\OnboardingController@update | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount |
| GET|HEAD | /api/opportunities | App\Http\Controllers\Api\OpportunityController@index | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| POST | /api/opportunities | App\Http\Controllers\Api\OpportunityController@store | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| POST | /api/opportunities/{id}/photo-analysis | App\Http\Controllers\Api\PropertyVisualizationController@analyzePhotos | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| GET|HEAD | /api/opportunities/{opportunity} | App\Http\Controllers\Api\OpportunityController@show | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| PUT|PATCH | /api/opportunities/{opportunity} | App\Http\Controllers\Api\OpportunityController@update | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| DELETE | /api/opportunities/{opportunity} | App\Http\Controllers\Api\OpportunityController@destroy | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| PATCH | /api/opportunities/{opportunity}/archive | App\Http\Controllers\Api\OpportunityController@archive | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| GET|HEAD | /api/opportunities/{opportunity}/capture | App\Http\Controllers\Api\CaptureController@showForOpportunity | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor,imobiliaria |
| POST | /api/opportunities/{opportunity}/captures | App\Http\Controllers\Api\CaptureController@start | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor,imobiliaria |
| PATCH | /api/opportunities/{opportunity}/discard | App\Http\Controllers\Api\OpportunityController@discard | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| PATCH | /api/opportunities/{opportunity}/favorite | App\Http\Controllers\Api\OpportunityController@favorite | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| POST | /api/opportunities/{opportunity}/investment-analysis | App\Http\Controllers\Api\InvestmentAnalysisController@store | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| PATCH | /api/opportunities/{opportunity}/status | App\Http\Controllers\Api\OpportunityController@changeStatus | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| GET|HEAD | /api/platform/overview | App\Http\Controllers\Api\PlatformAdminController@overview | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsurePlatformAdmin |
| GET|HEAD | /api/platform/saas-leads | App\Http\Controllers\Api\SaasLeadController@index | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsurePlatformAdmin |
| PATCH | /api/platform/saas-leads/{lead} | App\Http\Controllers\Api\SaasLeadController@update | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsurePlatformAdmin |
| GET|HEAD | /api/products | Closure | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| GET|HEAD | /api/projetos | App\Http\Controllers\Api\ProjetoController@index | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| POST | /api/projetos | App\Http\Controllers\Api\ProjetoController@store | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| PATCH | /api/projetos-etapas/{id} | App\Http\Controllers\Api\ProjetoController@updateEtapa | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| POST | /api/projetos/{id}/etapas | App\Http\Controllers\Api\ProjetoController@storeEtapa | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/projetos/{id}/resumo | App\Http\Controllers\Api\ProjetoController@resumo | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/projetos/{projeto} | App\Http\Controllers\Api\ProjetoController@show | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| PUT|PATCH | /api/projetos/{projeto} | App\Http\Controllers\Api\ProjetoController@update | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| DELETE | /api/projetos/{projeto} | App\Http\Controllers\Api\ProjetoController@destroy | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| POST | /api/property-market-analysis | App\Http\Controllers\Api\PropertyMarketAnalysisController | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, Illuminate\Routing\Middleware\ThrottleRequests:market-data |
| GET|HEAD | /api/proposals | App\Http\Controllers\Api\AgencyWorkspaceController@proposals | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor,imobiliaria |
| POST | /api/proposals | App\Http\Controllers\Api\AgencyWorkspaceController@storeProposal | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:corretor,imobiliaria |
| GET|HEAD | /api/propriedades | App\Http\Controllers\Api\PropriedadeController@index | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| POST | /api/propriedades | App\Http\Controllers\Api\PropriedadeController@store | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/propriedades/{id}/roi | App\Http\Controllers\Api\VendaController@roi | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/propriedades/{propriedade} | App\Http\Controllers\Api\PropriedadeController@show | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| PUT|PATCH | /api/propriedades/{propriedade} | App\Http\Controllers\Api\PropriedadeController@update | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| DELETE | /api/propriedades/{propriedade} | App\Http\Controllers\Api\PropriedadeController@destroy | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/public/agencies/{slug} | App\Http\Controllers\Api\PublicAgencyController@show | api, Illuminate\Routing\Middleware\ThrottleRequests:60,1 |
| GET|HEAD | /api/public/brokers/{slug} | App\Http\Controllers\Api\PublicBrokerController@show | api, Illuminate\Routing\Middleware\ThrottleRequests:60,1 |
| GET|HEAD | /api/public/catalog | App\Http\Controllers\Api\PublicCatalogController | api, Illuminate\Routing\Middleware\ThrottleRequests:60,1 |
| GET|HEAD | /api/public/listings/{listingSlug} | App\Http\Controllers\Api\PublicBrokerController@listing | api, Illuminate\Routing\Middleware\ThrottleRequests:60,1 |
| POST | /api/public/listings/{listingSlug}/leads | App\Http\Controllers\Api\PublicBrokerController@lead | api, Illuminate\Routing\Middleware\ThrottleRequests:public-leads |
| GET|HEAD | /api/public/listings/{listingSlug}/photos/{photo} | App\Http\Controllers\Api\PublicBrokerController@photo | api, Illuminate\Routing\Middleware\ThrottleRequests:60,1 |
| POST | /api/public/saas-leads | App\Http\Controllers\Api\SaasLeadController@store | api, Illuminate\Routing\Middleware\ThrottleRequests:saas-leads |
| GET|HEAD | /api/reports/ledger | App\Http\Controllers\Api\FinancialLedgerController | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/tarefas | App\Http\Controllers\Api\TarefaController@index | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| POST | /api/tarefas | App\Http\Controllers\Api\TarefaController@store | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/tarefas/{tarefa} | App\Http\Controllers\Api\TarefaController@show | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| PUT|PATCH | /api/tarefas/{tarefa} | App\Http\Controllers\Api\TarefaController@update | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| DELETE | /api/tarefas/{tarefa} | App\Http\Controllers\Api\TarefaController@destroy | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/vendas | App\Http\Controllers\Api\VendaController@index | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| POST | /api/vendas | App\Http\Controllers\Api\VendaController@store | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/vendas/{venda} | App\Http\Controllers\Api\VendaController@show | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| PUT|PATCH | /api/vendas/{venda} | App\Http\Controllers\Api\VendaController@update | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| DELETE | /api/vendas/{venda} | App\Http\Controllers\Api\VendaController@destroy | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, App\Http\Middleware\EnsureProductAccess:house_flipper |
| GET|HEAD | /api/visualizations/{subjectType}/{id} | App\Http\Controllers\Api\PropertyVisualizationController@show | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| POST | /api/visualizations/{subjectType}/{id}/assets | App\Http\Controllers\Api\PropertyVisualizationController@storeAsset | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, Illuminate\Routing\Middleware\ThrottleRequests:visualization-upload |
| GET|HEAD | /api/visualizations/{subjectType}/{id}/assets/{assetId}/file | App\Http\Controllers\Api\PropertyVisualizationController@file | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| POST | /api/visualizations/{subjectType}/{id}/commands | App\Http\Controllers\Api\PropertyVisualizationController@command | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, Illuminate\Routing\Middleware\ThrottleRequests:visualization-upload |
| POST | /api/visualizations/{subjectType}/{id}/rebuild | App\Http\Controllers\Api\PropertyVisualizationController@rebuild | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, Illuminate\Routing\Middleware\ThrottleRequests:visualization-upload |
| PATCH | /api/visualizations/{subjectType}/{id}/scene | App\Http\Controllers\Api\PropertyVisualizationController@mutate | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount, Illuminate\Routing\Middleware\ThrottleRequests:visualization-upload |
| POST | /api/visualizations/{subjectType}/{id}/versions | App\Http\Controllers\Api\PropertyVisualizationController@storeVersion | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
| POST | /api/visualizations/{subjectType}/{id}/versions/{versionId}/restore | App\Http\Controllers\Api\PropertyVisualizationController@restoreVersion | api, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\EnsureActiveAccount, App\Http\Middleware\EnsureVerifiedAccount |
