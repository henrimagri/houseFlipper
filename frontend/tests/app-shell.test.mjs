import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const read = (path) => readFile(new URL(`../${path}`, import.meta.url), 'utf8');

test('product switcher lives only inside the avatar profile menu', async () => {
  const [layout, sidebar] = await Promise.all([
    read('src/components/ProductLayout.tsx'),
    read('src/components/ProductSidebar.tsx'),
  ]);
  assert.match(layout, /role="menu" aria-label="Perfil e produtos"/);
  assert.match(layout, /<ProductSwitcher embedded activeProduct=\{product\}/);
  assert.doesNotMatch(layout, /<ProductSwitcher compact/);
  assert.doesNotMatch(sidebar, /ProductSwitcher/);
});

test('guided help blocks background interaction and remains explicitly dismissible', async () => {
  const tour = await read('src/components/ProductTour.tsx');
  assert.match(tour, /role="dialog"/);
  assert.match(tour, /aria-modal="true"/);
  assert.match(tour, /pointer-events-auto/);
  assert.match(tour, /Pular tutorial/);
  assert.match(tour, /Fechar tutorial/);
  assert.match(tour, /scrollIntoView/);
  assert.match(tour, /MutationObserver/);
  assert.match(tour, /ResizeObserver/);
});

test('browser CSP allows the configured map providers', async () => {
  const headers = await read('security-headers.ts');
  assert.match(headers, /https:\/\/tiles\.openfreemap\.org/);
  assert.match(headers, /https:\/\/nominatim\.openstreetmap\.org/);
});

test('preserved national visualization components use real Brazil geometry and identify all capitals', async () => {
  const [map, globe, globePrimitive, shadcn] = await Promise.all([
    read('src/ui/WorldMap.tsx'),
    read('src/components/3d-globe-demo.tsx'),
    read('src/components/ui/3d-globe.tsx'),
    read('components.json'),
  ]);
  assert.match(map, /@svg-maps\/brazil/);
  assert.match(map, /data-state=/);
  assert.match(map, /data-capital=/);
  assert.match(map, /data-route-flow="capital-communications"/);
  assert.match(map, /data-flow-segment/);
  assert.match(map, /27 capitais · visualização demonstrativa/);
  assert.match(globe, /Brasil em foco · arraste para girar/);
  assert.match(globe, /earth-blue-marble\.jpg/);
  assert.match(globe, /autoRotateSpeed: 0/);
  assert.match(globe, /enableRotate: true/);
  assert.match(globe, /enableZoom: false/);
  assert.match(globe, /initialRotation: \{ x: -\.24, y: -\.67 \}/);
  assert.match(globePrimitive, /size-8 cursor-pointer place-items-center/);
  assert.match(globePrimitive, /initialRotation\.x/);
  assert.match(globePrimitive, /enableRotate=\{config\.enableRotate\}/);
  assert.match(globePrimitive, /ProtectiveShield/);
  assert.doesNotMatch(globePrimitive, /distanceFactor=\{10\}/);
  assert.match(shadcn, /"ui": "@\/components\/ui"/);
});

test('authentication journey shares the premium shell and keeps password controls accessible', async () => {
  const [login, register, forgot, reset, verify] = await Promise.all([
    read('src/pages/Login.tsx'), read('src/pages/Register.tsx'), read('src/pages/ForgotPassword.tsx'),
    read('src/pages/ResetPassword.tsx'), read('src/pages/VerifyEmail.tsx'),
  ]);
  for (const screen of [login, register, forgot, reset, verify]) assert.match(screen, /<AuthShell/);
  assert.match(login, /Lembrar meu e-mail neste dispositivo/);
  assert.match(login, /aria-label=\{showPassword\?'Ocultar senha':'Mostrar senha'\}/);
  assert.match(register, /Força da senha:/);
  assert.match(reset, /Força da senha:/);
});

test('public features and pricing use the premium catalog with a seven-day trial', async () => {
  const [features, pricing, cards, catalog, productsConfig] = await Promise.all([
    read('src/pages/public/Funcionalidades.tsx'),
    read('src/pages/public/Precos.tsx'),
    read('src/components/public/PricingCards.tsx'),
    read('src/lib/publicCatalog.ts'),
    read('../config/products.php'),
  ]);
  assert.match(features, /featureIcon/);
  assert.match(features, /recursos listados/);
  assert.match(features, /Comparar produtos/);
  assert.match(pricing, /7 dias para experimentar/);
  assert.match(pricing, /role="tablist"/);
  assert.match(cards, /Criar conta e testar 7 dias/);
  assert.match(cards, /Continuar para pagamento/);
  assert.match(cards, /Trimestral/);
  assert.match(cards, /Anual/);
  assert.match(cards, /monthlyEquivalent/);
  assert.match(cards, /Economize \{selectedCycle\.discount_percent\}%/);
  assert.doesNotMatch(cards, /Solicitar proposta/);
  assert.match(catalog, /teste de 7 dias por produto/i);
  assert.equal([...productsConfig.matchAll(/'trial_days' => 7/g)].length, 3);
  assert.equal([...productsConfig.matchAll(/'name' => 'Teste 7 dias'/g)].length, 3);
  assert.match(productsConfig, /'quarterly' => \['label' => 'Trimestral', 'months' => 3, 'discount_percent' => 10\]/);
  assert.match(productsConfig, /'annual' => \['label' => 'Anual', 'months' => 12, 'discount_percent' => 20\]/);
});

test('unverified accounts remain on email confirmation before account routes', async () => {
  const [app, session, layout, register, verify, products] = await Promise.all([
    read('src/App.tsx'), read('src/lib/session.tsx'), read('src/components/public/SiteSurface.tsx'),
    read('src/pages/Register.tsx'), read('src/pages/VerifyEmail.tsx'), read('src/pages/account/Produtos.tsx'),
  ]);
  assert.match(session, /requiresEmailVerification/);
  assert.match(app, /session\.requiresEmailVerification \? <Navigate to="\/confirmar-email"/);
  assert.match(layout, /Confirmar e-mail/);
  assert.match(register, /navigate\('\/confirmar-email'/);
  assert.match(verify, /href="\/app\/produtos"/);
  assert.match(products, /Ir para pagamento/);
  assert.doesNotMatch(products, /Solicitar proposta/);
});

test('application shell stays fixed, shows the registered location, and keeps uploads understandable', async () => {
  const [layout, sidebar, session, settings, itbi, finance, sources, neighborhood, services] = await Promise.all([
    read('src/components/ProductLayout.tsx'), read('src/components/ProductSidebar.tsx'), read('src/lib/session.tsx'),
    read('src/pages/Configuracoes.tsx'), read('src/pages/ImportarItbi.tsx'), read('src/pages/CentralFinanceira.tsx'),
    read('../app/Http/Controllers/Api/DataSourceStatusController.php'), read('../app/Services/NeighborhoodContextService.php'), read('../config/services.php'),
  ]);
  assert.match(layout, /fixed inset-y-0 left-0 z-50/);
  assert.match(layout, /locationLabel \|\| 'Localização não informada'/);
  assert.match(sidebar, /h-dvh/);
  assert.match(sidebar, /overscroll-contain/);
  assert.match(session, /registration_profile\?\.address/);
  assert.doesNotMatch(settings, /Google APIs/);
  assert.doesNotMatch(sources, /googleStatus|Google Maps/);
  assert.doesNotMatch(neighborhood, /fromGoogle|google_places/);
  assert.doesNotMatch(services, /google_maps/);
  assert.doesNotMatch(itbi, /disabled=\{!data\?\.can_import \|\| uploading\}/);
  assert.match(itbi, /Permissão de operador necessária/);
  assert.match(finance, /Editar análise completa/);
  assert.match(finance, /sticky top-20/);
  assert.match(finance, /\/app\/house-flipper\/oportunidades/);
});

test('broker onboarding and site editor stay readable and fully configurable', async () => {
  const [onboarding, tours, editor, publicSite, app, controller, sanitizer] = await Promise.all([
    read('src/pages/onboarding/Onboarding.tsx'), read('src/lib/tours.ts'), read('src/pages/SiteCorretor.tsx'),
    read('src/pages/PublicBroker.tsx'), read('src/App.tsx'), read('../app/Http/Controllers/Api/BrokerSiteController.php'),
    read('../app/Support/BrokerThemeSanitizer.php'),
  ]);
  assert.match(onboarding, /dark:bg-slate-900/);
  assert.match(onboarding, /dark:text-slate-100/);
  assert.doesNotMatch(tours, /\/c\/\{slug\}/);
  assert.match(editor, /Modelos prontos/);
  assert.match(editor, /cleanSlug/);
  assert.match(editor, /Espaços e acentos viram hífens automaticamente/);
  assert.match(editor, /Seções do site/);
  assert.match(editor, /background_color/);
  assert.doesNotMatch(editor, /Mercado Pago|Stripe|not_wired/);
  assert.match(publicSite, /visibleSections/);
  assert.match(publicSite, /Busque por bairro ou cidade/);
  assert.match(app, /path="\/corretor\/:slug"/);
  assert.match(controller, /'public_path' => '\/corretor\/'/);
  assert.match(sanitizer, /'button_style'/);
});

test('broker commercial area provides a personal persistent accessible organizer', async () => {
  const [board, app, products, controller] = await Promise.all([
    read('src/pages/corretor/PersonalKanban.tsx'), read('src/App.tsx'), read('../config/products.php'),
    read('../app/Http/Controllers/Api/PersonalBoardTaskController.php'),
  ]);
  assert.match(board, /Meu organizador/);
  assert.match(board, /espaço é pessoal e não altera clientes ou leads/);
  assert.match(board, /draggable/);
  assert.match(board, /onDrop/);
  assert.match(board, /api\.patch\(\`\/personal-board\/tasks/);
  assert.match(board, /Mover para a próxima coluna/);
  assert.match(app, /path="\/app\/corretor\/comercial"/);
  assert.match(products, /'label' => 'Organizador'/);
  assert.match(controller, /where\('user_id', \$request->user\(\)->id\)/);
});

test('auction catalog supports official automatic refresh and robust multi-file imports', async () => {
  const [screen, controller, importer, routes] = await Promise.all([
    read('src/pages/house-flipper/Auctions.tsx'), read('../app/Http/Controllers/Api/AuctionController.php'),
    read('../app/Services/Marketplace/AuctionImportService.php'), read('../routes/api.php'),
  ]);
  assert.match(screen, /Buscar e atualizar automaticamente/);
  assert.match(screen, /multiple type="file"/);
  assert.match(screen, /city: next\.city/);
  assert.match(screen, /max_price_cents/);
  assert.match(controller, /syncOfficialState/);
  assert.match(importer, /Lista_imoveis_\{\$state\}\.csv/);
  assert.match(importer, /removeProviderPreamble/);
  assert.match(routes, /\/auctions\/sync/);
});

test('property visualization separates photo editing, scene creation, and validated reconstruction', async () => {
  const [screen, editor, sceneEditor, catalog, viewer, contracts] = await Promise.all([
    read('src/pages/PropertyViewer.tsx'),
    read('src/components/PropertyPhotoEditor.tsx'),
    read('src/components/PropertyPhotoSceneEditor.tsx'),
    read('src/lib/furnitureCatalog.ts'),
    read('src/components/GlbViewer.tsx'),
    read('src/lib/propertyVisualization.ts'),
  ]);

  assert.match(screen, /C · Reconstrução multivista/);
  assert.match(screen, /B · Ambiente 3D editável/);
  assert.match(screen, /A · Simulação em fotografia/);
  assert.match(screen, /Projeto visual do imóvel/);
  assert.match(screen, /\['6', 'Apresentação'/);
  assert.match(screen, /regiões não observadas permanecem desconhecidas/i);
  assert.match(screen, /correção estéreo local experimental/);
  assert.match(screen, /fusão híbrida de estéreo verificado/);
  assert.match(screen, /viewer_eligible === true/);
  assert.match(screen, /diagnostic_asset_id/);
  assert.match(screen, /Candidato experimental aberto somente para diagnóstico/);
  assert.match(screen, /Voltar ao modelo ativo/);
  assert.match(screen, /Modelo ainda não elegível para o viewer/);
  assert.match(screen, /artefatos ajudam no diagnóstico, mas não são apresentados como modelo final/i);
  assert.match(editor, /Comparação antes e depois/);
  assert.match(editor, /mask_mode/);
  assert.match(editor, /Ponto incluir/);
  assert.match(editor, /Ponto excluir/);
  assert.match(editor, /Aplicar simulação/);
  assert.match(editor, /Histórico de simulações/);
  assert.match(editor, /SAM 2 interpreta esses pontos/i);
  assert.match(editor, /sem segmentação automática/);
  assert.match(screen, /photo-simulations/);
  assert.match(sceneEditor, /ACESFilmicToneMapping/);
  assert.match(sceneEditor, /Proporções aproximadas/);
  assert.match(sceneEditor, /loadCatalogModels\(graph\)/);
  assert.match(sceneEditor, /if \(!texture\) continue/);
  assert.match(catalog, /sheen-wood-leather-sofa\.glb/);
  assert.match(catalog, /license: 'CC BY 4\.0/);
  assert.match(viewer, /if \(!eligible\)/);
  assert.match(viewer, /invalid_bounds/);
  assert.match(viewer, /Falha ao abrir o modelo/);
  assert.match(viewer, /outputColorSpace = THREE\.SRGBColorSpace/);
  assert.match(viewer, /toneMapping = THREE\.ACESFilmicToneMapping/);
  assert.match(viewer, /viewPreset === 'top'/);
  assert.match(viewer, /Confiança geométrica estimada/);
  assert.match(viewer, /Desmarcar superfície/);
  assert.match(viewer, /Refazer/);
  assert.match(viewer, /side: THREE\.DoubleSide/);
  assert.match(viewer, /vertexColors: true/);
  assert.match(viewer, /Math\.tan\(fovRadians \/ 2\)\) \* 1\.25/);
  assert.match(contracts, /export type PhotoEditRequest/);
  assert.match(contracts, /viewer_eligible\?: boolean/);
  assert.match(contracts, /diagnostic_models\?: Array/);
});
