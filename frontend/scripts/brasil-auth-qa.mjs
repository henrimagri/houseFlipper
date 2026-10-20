import assert from 'node:assert/strict';
import { cp, mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import puppeteer from 'puppeteer-core';

const base = 'http://127.0.0.1:5173';
const artifacts = path.resolve('../docs/qa/artifacts/brasil-auth');
const viewports = [
  { width: 375, height: 812 },
  { width: 430, height: 932 },
  { width: 768, height: 1024 },
  { width: 1366, height: 768 },
  { width: 1920, height: 1080 },
];
await mkdir(artifacts, { recursive: true });
try { await cp(path.resolve('../docs/qa/artifacts/adendo/landing-after-1366x768.png'), path.join(artifacts, 'before-landing-1366x768.png')); } catch {}
const browser = await puppeteer.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', args: ['--disable-dev-shm-usage', '--use-gl=angle', '--use-angle=swiftshader'] });
const results = [];
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));

async function pageAt(viewport) {
  const page = await browser.newPage();
  await page.setViewport(viewport);
  const errors = [];
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  return { page, errors };
}
async function assertLayout(page, label) {
  const metrics = await page.evaluate(() => ({
    overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
    minTap: [...document.querySelectorAll('button')].filter(node => {
      const box = node.getBoundingClientRect();
      return box.width > 0 && box.height > 0 && (box.width < 32 || box.height < 32);
    }).length,
    title: document.querySelector('h1,h2')?.textContent?.trim(),
  }));
  assert.equal(metrics.overflow, false, `overflow horizontal em ${label}`);
  return metrics;
}

try {
  for (const viewport of viewports) {
    const { page, errors } = await pageAt(viewport);
    const response = await page.goto(base, { waitUntil: 'networkidle0', timeout: 45000 });
    await page.waitForSelector('svg[aria-label="Mapa do Brasil com todos os estados e capitais conectadas"]', { timeout: 20000 });
    await page.waitForSelector('canvas', { timeout: 20000 });
    await pause(800);
    const layout = await assertLayout(page, `home ${viewport.width}x${viewport.height}`);
    const state = await page.evaluate(() => ({
      states: document.querySelectorAll('[data-state]').length,
      capitals: document.querySelectorAll('[data-capital]').length,
      previews: document.querySelectorAll('a[aria-label^="Explorar "]').length,
      previewDisclaimer: (document.body.innerText.match(/Nenhum número representa dado comercial real/g) || []).length,
      nationalCopy: document.body.innerText.includes('Uma visão nacional. Três produtos com inteligência própria.'),
      globeDisclosure: document.body.innerText.includes('Brasil em foco · arraste para girar'),
      routeFlows: document.querySelectorAll('[data-route-flow="capital-communications"]').length,
      movingSignals: document.querySelectorAll('[data-flow-segment]').length,
      canvases: document.querySelectorAll('canvas').length,
    }));
    assert.ok((response?.status() ?? 500) < 400);
    assert.equal(state.states, 27, 'mapa sem os 27 estados');
    assert.equal(state.capitals, 27, 'mapa sem as 27 capitais');
    assert.equal(state.previews, 3, 'prévias de produto incompletas');
    assert.equal(state.previewDisclaimer, 3, 'aviso ilustrativo incompleto');
    assert.ok(state.nationalCopy && state.globeDisclosure && state.canvases > 0);
    assert.equal(state.routeFlows, 1, 'fluxo animado entre capitais ausente');
    assert.equal(state.movingSignals, 27, 'segmentos animados entre capitais incompletos');
    assert.deepEqual(errors, [], `erros de console na home ${viewport.width}`);
    await page.screenshot({ path: path.join(artifacts, `home-${viewport.width}x${viewport.height}.png`), fullPage: true });
    results.push({ route: '/', viewport, ...layout, ...state, errors, passed: true });
    await page.close();
  }

  for (const viewport of viewports) {
    const { page, errors } = await pageAt(viewport);
    await page.goto(`${base}/login`, { waitUntil: 'networkidle0', timeout: 30000 });
    const layout = await assertLayout(page, `login ${viewport.width}x${viewport.height}`);
    await page.type('#login-password', 'SenhaTemporaria123!');
    await page.click('button[aria-label="Mostrar senha"]');
    const state = await page.evaluate(() => ({
      passwordType: document.querySelector('#login-password')?.getAttribute('type'),
      remember: Boolean(document.querySelector('input[type="checkbox"]')),
      forgot: Boolean([...document.querySelectorAll('a')].find(node => node.textContent?.includes('Esqueci minha senha'))),
      splitLayout: innerWidth < 1024 || getComputedStyle(document.querySelector('main section')).display !== 'none',
    }));
    assert.equal(state.passwordType, 'text');
    assert.ok(state.remember && state.forgot && state.splitLayout);
    assert.deepEqual(errors, [], `erros de console no login ${viewport.width}`);
    await page.screenshot({ path: path.join(artifacts, `login-${viewport.width}x${viewport.height}.png`), fullPage: true });
    results.push({ route: '/login', viewport, ...layout, ...state, errors, passed: true });
    await page.close();
  }

  for (const viewport of viewports) {
    const { page, errors } = await pageAt(viewport);
    await page.goto(`${base}/cadastro`, { waitUntil: 'networkidle0', timeout: 30000 });
    let layout = await assertLayout(page, `cadastro ${viewport.width}x${viewport.height}`);
    const firstProduct = await page.$('button');
    assert.ok(firstProduct);
    const productButtons = await page.$$('section button');
    const choice = productButtons.find(async button => (await button.evaluate(node => node.textContent)).includes('Corretor'));
    const candidates = await page.$$('section button');
    let selected = false;
    for (const button of candidates) {
      if ((await button.evaluate(node => node.textContent || '')).includes('Corretor')) { await button.click(); selected = true; break; }
    }
    assert.ok(selected, 'produto Corretor não selecionável');
    await page.waitForSelector('input[type="password"]');
    const passwordInputs = await page.$$('input[type="password"]');
    await passwordInputs[0].type('SenhaForte123!');
    await passwordInputs[1].type('SenhaForte123!');
    await page.click('button[aria-label="Mostrar senha"]');
    const state = await page.evaluate(() => ({
      step: document.querySelector('[role="progressbar"]')?.getAttribute('aria-valuenow'),
      strength: document.querySelector('[aria-label^="Força da senha"]')?.getAttribute('aria-label'),
      textPasswords: document.querySelectorAll('input[type="text"]').length,
      hasFiveSteps: document.body.innerText.includes('Etapa 2 de 5'),
    }));
    assert.equal(state.step, '2');
    assert.equal(state.strength, 'Força da senha: 5 de 5');
    assert.ok(state.textPasswords >= 2 && state.hasFiveSteps);
    layout = await assertLayout(page, `cadastro etapa 2 ${viewport.width}x${viewport.height}`);
    assert.deepEqual(errors, [], `erros de console no cadastro ${viewport.width}`);
    await page.screenshot({ path: path.join(artifacts, `register-${viewport.width}x${viewport.height}.png`), fullPage: true });
    results.push({ route: '/cadastro', viewport, ...layout, ...state, errors, passed: true });
    await page.close();
  }

  for (const route of ['/esqueci-senha', '/redefinir-senha', '/confirmar-email']) {
    for (const viewport of [viewports[0], viewports[3]]) {
      const { page, errors } = await pageAt(viewport);
      await page.goto(`${base}${route}`, { waitUntil: 'networkidle0', timeout: 30000 });
      const layout = await assertLayout(page, `${route} ${viewport.width}`);
      assert.deepEqual(errors, [], `erros de console em ${route}`);
      await page.screenshot({ path: path.join(artifacts, `${route.slice(1)}-${viewport.width}x${viewport.height}.png`), fullPage: true });
      results.push({ route, viewport, ...layout, errors, passed: true });
      await page.close();
    }
  }

  await writeFile(path.resolve('../docs/qa/brasil-auth-results.json'), JSON.stringify({ generatedAt: new Date().toISOString(), results }, null, 2));
  console.log(`BRASIL_AUTH_QA_PASS ${results.length}/${results.length}`);
} finally {
  await browser.close();
}
