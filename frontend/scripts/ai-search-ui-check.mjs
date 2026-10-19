import { writeFile } from 'node:fs/promises';
import puppeteer from 'puppeteer-core';
import { browserRuntime } from './browser-runtime.mjs';

const runtime = browserRuntime();
const browser = await puppeteer.launch({ ...runtime, headless: true, args: [...runtime.args, '--disable-gpu'] });
const page = await browser.newPage();
await page.setViewport({ width: 375, height: 812, deviceScaleFactor: 1 });
await page.evaluateOnNewDocument(() => {
  localStorage.setItem('token', 'visual-audit-token');
  localStorage.setItem('theme', 'dark');
  sessionStorage.clear();
});

let attempts = 0;
let inFlight = 0;
let maximumConcurrent = 0;
const consoleErrors = [];
const pageErrors = [];
const requestFailures = [];
page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
page.on('pageerror', error => pageErrors.push(error.message));
page.on('requestfailed', request => requestFailures.push({ url: request.url(), error: request.failure()?.errorText }));
await page.setRequestInterception(true);
page.on('request', async request => {
  const url = new URL(request.url());
  const json = (status, body) => request.respond({ status, contentType: 'application/json', headers: { 'Access-Control-Allow-Origin': '*' }, body: JSON.stringify(body) });
  if (url.hostname === 'servicodados.ibge.gov.br') {
    await json(200, [{ nome: 'São Paulo' }, { nome: 'Campinas' }]);
  } else if (url.pathname === '/api/auth/me') {
    await json(200, { name: 'Auditoria Visual' });
  } else if (url.pathname === '/api/dashboard') {
    await json(200, { kpis: { total_invested: 0, realized_profit: 0, realized_roi: 0 }, activities: [] });
  } else if (url.pathname === '/api/ai-property-search') {
    attempts++;
    inFlight++;
    maximumConcurrent = Math.max(maximumConcurrent, inFlight);
    await new Promise(resolve => setTimeout(resolve, 250));
    if (attempts === 1) {
      await json(502, { message: 'Nenhuma fonte de anúncios respondeu agora.', diagnostics: [{ source: 'Portais', message: 'Fontes temporariamente indisponíveis' }] });
    } else {
      await json(200, {
        data: [{ id: 'fixture-1', title: 'Apartamento de teste em Perdizes', address: 'Rua de teste', city: 'São Paulo', neighborhood: 'Perdizes', state: 'SP', source: 'Fixture', source_key: 'other', source_url: 'https://example.test/imovel', asking_price: 500000, estimated_market_price: 600000, discount_percentage: 16.7, area_m2: 80, bedrooms: 2, bathrooms: 2, parking_spaces: 1, price_per_m2: 6250, score: 82, signal: 'Boa oportunidade', market_estimate_method: 'comparables_neighborhood_median_m2', market_reference_count: 3, market_reference_sources: ['Fixture'] }],
        source: { diagnostics: [{ source: 'Itens coletados', found: 1 }] },
      });
    }
    inFlight--;
  } else if (url.pathname.startsWith('/api/')) {
    await json(404, { message: 'Fixture ausente.' });
  } else {
    await request.continue();
  }
});

try {
  await page.goto('http://127.0.0.1:5173/ia-inteligente', { waitUntil: 'domcontentloaded', timeout: 120000 });
  try {
    await page.waitForFunction(() => document.body.innerText.includes('Radar por filtros'), { timeout: 30000 });
  } catch (error) {
    const diagnostic = { url: page.url(), body: await page.evaluate(() => document.body.innerText), consoleErrors, pageErrors, requestFailures };
    process.stderr.write(`${JSON.stringify(diagnostic)}\n`);
    throw error;
  }
  const tabButtons = await page.$$('button');
  const radarTab = (await Promise.all(tabButtons.map(async item => ({ item, text: await item.evaluate(node => node.textContent?.trim()) })))).find(item => item.text === 'Radar por filtros')?.item;
  if (!radarTab) throw new Error('Aba Radar por filtros não encontrada.');
  await radarTab.click();
  await page.waitForFunction(() => document.body.innerText.includes('Buscar'), { timeout: 15000 });
  const buttons = await page.$$('button');
  const button = (await Promise.all(buttons.map(async item => ({ item, text: await item.evaluate(node => node.textContent?.trim()) })))).find(item => item.text === 'Buscar')?.item;
  if (!button) throw new Error('Botão Buscar não encontrado.');

  await button.click();
  const disabledDuringFailure = await button.evaluate(node => node.disabled);
  await page.waitForFunction(() => document.body.innerText.includes('Nenhuma fonte de anúncios respondeu agora.'));
  const failed = await page.evaluate(() => ({
    messageVisible: document.body.innerText.includes('Nenhuma fonte de anúncios respondeu agora.'),
    diagnosticsVisible: document.body.innerText.includes('Fontes temporariamente indisponíveis'),
    resultVisible: document.body.innerText.includes('Apartamento de teste em Perdizes'),
    buttonEnabled: [...document.querySelectorAll('button')].some(item => item.textContent?.trim() === 'Buscar' && !item.disabled),
    width: innerWidth,
    scrollWidth: document.documentElement.scrollWidth,
    dark: document.documentElement.classList.contains('dark'),
  }));
  await page.screenshot({ path: '../meta/ai-search-502-mobile.png', fullPage: true });

  await button.click();
  const disabledDuringRecovery = await button.evaluate(node => node.disabled);
  await page.waitForFunction(() => document.body.innerText.includes('Apartamento de teste em Perdizes'));
  const recovered = await page.evaluate(() => ({
    errorVisible: document.body.innerText.includes('Nenhuma fonte de anúncios respondeu agora.'),
    resultVisible: document.body.innerText.includes('Apartamento de teste em Perdizes'),
    width: innerWidth,
    scrollWidth: document.documentElement.scrollWidth,
  }));
  await page.screenshot({ path: '../meta/ai-search-recovered-mobile.png', fullPage: true });

  const result = {
    checked_at: '2026-10-18',
    route: '/ia-inteligente',
    viewport: { width: 375, height: 812 },
    attempts,
    maximum_concurrent_requests: maximumConcurrent,
    disabled_during_failure: disabledDuringFailure,
    disabled_during_recovery: disabledDuringRecovery,
    failed_state: failed,
    recovered_state: recovered,
    console_errors: consoleErrors,
    page_errors: pageErrors,
    passed: attempts === 2
      && maximumConcurrent === 1
      && disabledDuringFailure
      && disabledDuringRecovery
      && failed.messageVisible
      && !failed.resultVisible
      && failed.buttonEnabled
      && failed.dark
      && failed.scrollWidth <= failed.width
      && !recovered.errorVisible
      && recovered.resultVisible
      && recovered.scrollWidth <= recovered.width
      && pageErrors.length === 0
      && consoleErrors.every(message => message.includes('502 (Bad Gateway)')),
  };
  await writeFile('../meta/ai-search-network-checks.json', `${JSON.stringify(result, null, 2)}\n`);
  process.stdout.write(`${JSON.stringify(result)}\n`);
  if (!result.passed) process.exitCode = 1;
} finally {
  await browser.close();
}
