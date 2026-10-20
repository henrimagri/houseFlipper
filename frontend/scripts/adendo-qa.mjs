import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import puppeteer from 'puppeteer-core';

const base = 'http://127.0.0.1:5173';
const artifacts = path.resolve('../docs/qa/artifacts/adendo');
const viewports = [
  { width: 375, height: 812 },
  { width: 430, height: 932 },
  { width: 768, height: 1024 },
  { width: 1366, height: 768 },
  { width: 1920, height: 1080 },
];
await mkdir(artifacts, { recursive: true });
const browser = await puppeteer.launch({
  headless: true,
  executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
  args: ['--disable-dev-shm-usage', '--use-gl=angle', '--use-angle=swiftshader'],
});
const results = [];
const tourOnly = process.env.ADENDO_TOUR_ONLY === '1';
const pause = (milliseconds) => new Promise(resolve => setTimeout(resolve, milliseconds));

async function openPage(viewport, theme = 'light') {
  const page = await browser.newPage();
  await page.setViewport(viewport);
  await page.evaluateOnNewDocument(value => localStorage.setItem('theme', value), theme);
  const consoleErrors = [];
  const failedRequests = [];
  page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
  page.on('requestfailed', request => failedRequests.push({ url: request.url(), reason: request.failure()?.errorText }));
  return { page, consoleErrors, failedRequests };
}

try {
  if (!tourOnly) {
  for (const viewport of viewports) {
    const { page, consoleErrors, failedRequests } = await openPage(viewport);
    await page.goto(base, { waitUntil: 'networkidle0', timeout: 30000 });
    await page.waitForSelector('canvas', { timeout: 15000 });
    await page.waitForSelector('svg[aria-label="Mapa do Brasil com todos os estados e capitais conectadas"]', { timeout: 15000 });
    await pause(1400);
    const state = await page.evaluate(() => ({
      overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
      canvases: document.querySelectorAll('canvas').length,
      mapImages: document.querySelectorAll('svg[aria-label="Mapa do Brasil com todos os estados e capitais conectadas"]').length,
      mapPaths: document.querySelectorAll('[data-state]').length,
      mapCapitals: document.querySelectorAll('[data-capital]').length,
      routeFlows: document.querySelectorAll('[data-route-flow="capital-communications"]').length,
      movingSignals: document.querySelectorAll('[data-flow-segment]').length,
      oldMapPolygons: document.querySelectorAll('.world-fill, polygon.world-fill').length,
      demoDisclosure: document.body.innerText.includes('27 capitais · visualização demonstrativa'),
    }));
    assert.equal(state.overflow, false, `overflow horizontal em ${viewport.width}x${viewport.height}`);
    assert.ok(state.canvases >= 1, 'globo sem canvas WebGL');
    assert.ok(state.mapImages >= 1 && state.mapPaths === 27 && state.mapCapitals === 27, 'mapa do Brasil incompleto');
    assert.equal(state.routeFlows, 1, 'fluxo animado entre capitais ausente');
    assert.equal(state.movingSignals, 27, 'segmentos animados entre capitais incompletos');
    assert.equal(state.oldMapPolygons, 0, 'mapa antigo ainda presente');
    assert.equal(state.demoDisclosure, true, 'aviso de conexões demonstrativas ausente');
    assert.deepEqual(consoleErrors, [], `erros de console em ${viewport.width}x${viewport.height}`);
    assert.deepEqual(failedRequests, [], `requisições falharam em ${viewport.width}x${viewport.height}`);
    await page.screenshot({ path: path.join(artifacts, `landing-after-${viewport.width}x${viewport.height}.png`), fullPage: true });
    results.push({ kind: 'landing', viewport, ...state, consoleErrors, failedRequests, passed: true });
    await page.close();
  }

  const { page: detailPage, consoleErrors, failedRequests } = await openPage({ width: 1366, height: 768 }, 'dark');
  await detailPage.goto(base, { waitUntil: 'networkidle0', timeout: 30000 });
  await detailPage.waitForSelector('canvas', { timeout: 15000 });
  await detailPage.waitForSelector('svg[aria-label="Mapa do Brasil com todos os estados e capitais conectadas"]', { timeout: 15000 });
  await pause(1600);
  const globeCanvas = await detailPage.$('canvas');
  assert.ok(globeCanvas, 'canvas do globo não encontrado');
  const globeBox = await globeCanvas.boundingBox();
  assert.ok(globeBox && globeBox.width > 300 && globeBox.height > 300, 'globo sem área visível adequada');
  await detailPage.evaluate(() => document.querySelector('canvas')?.scrollIntoView({ block: 'center' }));
  await pause(400);
  const globeContainer = await detailPage.evaluateHandle(() => document.querySelector('canvas')?.parentElement?.parentElement);
  const globeElement = globeContainer.asElement();
  assert.ok(globeElement, 'contêiner do globo ausente');
  await globeElement.screenshot({ path: path.join(artifacts, 'globe-after.png') });
  const marker = await detailPage.$('button[aria-label^="Selecionar "]');
  if (marker) {
    await marker.hover();
    await marker.click();
  }
  const globeText = await detailPage.evaluate(() => document.body.innerText);
  assert.match(globeText, /Capital em destaque/i);
  const mapFigure = await detailPage.evaluateHandle(() => document.querySelector('svg[aria-label="Mapa do Brasil com todos os estados e capitais conectadas"]')?.closest('figure'));
  const mapElement = mapFigure.asElement();
  assert.ok(mapElement, 'figura do mapa ausente');
  await mapElement.screenshot({ path: path.join(artifacts, 'world-map-after.png') });
  assert.deepEqual(consoleErrors, [], 'erros de console no modo escuro');
  assert.deepEqual(failedRequests, [], 'requisições falharam no modo escuro');
  results.push({ kind: 'components-dark', globeBox, passed: true });
  await detailPage.close();
  }

  const { page: tourPage, consoleErrors: tourErrors, failedRequests: tourFailed } = await openPage({ width: 1366, height: 768 });
  await tourPage.goto(`${base}/entrar`, { waitUntil: 'networkidle0', timeout: 30000 });
  await tourPage.type('#login-email', 'imobiliaria@houseflipper.local');
  await tourPage.type('#login-password', 'password');
  await Promise.all([tourPage.click('button[type="submit"]'), tourPage.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 })]);
  await tourPage.goto(`${base}/app/imobiliaria/dashboard`, { waitUntil: 'networkidle0', timeout: 30000 });
  await tourPage.evaluate(async () => {
    const token = localStorage.getItem('token');
    await fetch('/api/onboarding/imobiliaria', {
      method: 'PUT',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}`, 'X-Product-Context': 'imobiliaria' },
      body: JSON.stringify({ tour_version: 2, current_step: 0, completed_steps: [], completed: false, dismissed: false }),
    });
  });
  await tourPage.reload({ waitUntil: 'networkidle0' });
  await pause(800);
  const automatic = await tourPage.$('[role="dialog"][aria-modal="true"]');
  if (automatic) {
    const [closeResponse] = await Promise.all([
      tourPage.waitForResponse(response => response.request().method() === 'PUT' && response.url().includes('/api/onboarding/imobiliaria'), { timeout: 5000 }),
      tourPage.$eval('[aria-label="Fechar tutorial"]', button => button.click()),
    ]);
    assert.ok(closeResponse.ok(), `persistência ao fechar retornou ${closeResponse.status()}`);
    await tourPage.waitForFunction(() => !document.querySelector('[role="dialog"]'), { timeout: 5000 });
  }
  await tourPage.waitForSelector('button[title="Rever tutorial"]');
  await tourPage.$eval('button[title="Rever tutorial"]', button => button.click());
  await tourPage.waitForSelector('[role="dialog"][aria-modal="true"]');
  await tourPage.waitForFunction(() => location.pathname === '/app/imobiliaria/dashboard', { timeout: 10000 });
  await pause(500);
  const initialTour = await tourPage.evaluate(() => ({
    path: location.pathname,
    overflow: document.body.style.overflow,
    title: document.querySelector('#tour-title')?.textContent,
    focusInside: Boolean(document.querySelector('[role="dialog"]')?.contains(document.activeElement)),
  }));
  assert.equal(initialTour.path, '/app/imobiliaria/dashboard');
  assert.equal(initialTour.overflow, 'hidden');
  assert.equal(initialTour.focusInside, true);
  await tourPage.screenshot({ path: path.join(artifacts, 'tutorial-after-desktop.png') });
  const pathBeforeBlockedClick = await tourPage.evaluate(() => location.pathname);
  await tourPage.mouse.click(20, 300);
  await pause(200);
  assert.equal(await tourPage.evaluate(() => location.pathname), pathBeforeBlockedClick, 'overlay não bloqueou clique externo');
  await tourPage.$$eval('button', buttons => buttons.find(button => button.textContent?.includes('Próximo'))?.click());
  await tourPage.waitForFunction(() => location.pathname === '/app/imobiliaria/imoveis', { timeout: 10000 });
  await tourPage.waitForSelector('[data-tour="page"]');
  await pause(400);
  const routedTour = await tourPage.evaluate(() => {
    const dialog = document.querySelector('[role="dialog"]')?.getBoundingClientRect();
    const target = document.querySelector('[data-tour="page"]')?.getBoundingClientRect();
    return { path: location.pathname, dialog: dialog && { top: dialog.top, left: dialog.left, right: dialog.right, bottom: dialog.bottom }, target: target && { width: target.width, height: target.height } };
  });
  assert.equal(routedTour.path, '/app/imobiliaria/imoveis');
  assert.ok(routedTour.dialog && routedTour.dialog.top >= 0 && routedTour.dialog.right <= 1366 && routedTour.dialog.bottom <= 768, 'painel saiu da viewport');
  assert.ok(routedTour.target && routedTour.target.width > 0 && routedTour.target.height > 0, 'alvo da etapa ausente');
  await tourPage.setViewport({ width: 375, height: 812 });
  await pause(500);
  const mobileDialog = await tourPage.evaluate(() => { const box = document.querySelector('[role="dialog"]')?.getBoundingClientRect(); return box && { top: box.top, left: box.left, right: box.right, bottom: box.bottom }; });
  assert.ok(mobileDialog && mobileDialog.left >= 0 && mobileDialog.right <= 375 && mobileDialog.bottom <= 812, 'painel móvel saiu da viewport');
  await tourPage.screenshot({ path: path.join(artifacts, 'tutorial-after-mobile.png') });
  await tourPage.focus('[role="dialog"] [tabindex="-1"]');
  await tourPage.evaluate(() => document.activeElement?.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true })));
  await tourPage.waitForFunction(() => !document.querySelector('[role="dialog"]'), { timeout: 5000 });
  assert.equal(await tourPage.evaluate(() => document.body.style.overflow), '', 'rolagem não foi restaurada');
  await tourPage.reload({ waitUntil: 'networkidle0' });
  await pause(500);
  const storedOnboarding = await tourPage.evaluate(async () => {
    const token = localStorage.getItem('token');
    const response = await fetch('/api/onboarding/imobiliaria', { headers: { Accept: 'application/json', Authorization: `Bearer ${token}`, 'X-Product-Context': 'imobiliaria' } });
    return (await response.json()).data;
  });
  assert.equal(Boolean(await tourPage.$('[role="dialog"][aria-modal="true"]')), false, 'estado dispensado não persistiu');
  assert.equal(storedOnboarding.dismissed, true, 'API não persistiu a dispensa do tutorial');
  assert.equal(storedOnboarding.tour_version, 2, 'API não persistiu a versão do tutorial');
  assert.deepEqual(tourErrors, [], 'erros de console no tutorial');
  assert.deepEqual(tourFailed, [], 'requisições falharam no tutorial');
  results.push({ kind: 'tutorial', initialTour, routedTour, mobileDialog, dismissedPersisted: storedOnboarding.dismissed, tourVersion: storedOnboarding.tour_version, passed: true });
  await tourPage.close();

  await writeFile(path.join(artifacts, 'adendo-results.json'), JSON.stringify({ generatedAt: new Date().toISOString(), results }, null, 2));
  console.log(`ADENDO_QA_PASS ${results.length}/${results.length}`);
} finally {
  await browser.close();
}
