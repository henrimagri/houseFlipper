import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import puppeteer from 'puppeteer-core';

const base = 'http://127.0.0.1:5173';
const appToken = process.env.QA_APP_TOKEN;
const clientToken = process.env.QA_CLIENT_TOKEN;
assert.ok(appToken && clientToken, 'Tokens temporários de QA ausentes.');
const artifacts = path.resolve('../docs/qa/artifacts/shell-itbi-finance');
await mkdir(artifacts, { recursive: true });
const fixture = path.join(artifacts, 'selecao-itbi.csv');
await writeFile(fixture, 'sql,data transacao,valor transacao\n0001,2026-01-01,100000\n');
const browser = await puppeteer.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', args: ['--disable-dev-shm-usage'] });
const results = [];

async function authenticatedPage(token, viewport = { width: 1366, height: 768 }) {
  const page = await browser.newPage();
  await page.setViewport(viewport);
  await page.evaluateOnNewDocument(value => localStorage.setItem('token', value), token);
  const errors = [];
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  return { page, errors };
}

try {
  {
    const { page, errors } = await authenticatedPage(clientToken);
    await page.goto(`${base}/app/produtos`, { waitUntil: 'networkidle0' });
    await page.waitForSelector('header');
    const location = await page.evaluate(() => document.querySelector('header')?.innerText.includes('São Paulo · SP'));
    assert.equal(location, true, 'localização do cadastro ausente no cabeçalho');
    assert.deepEqual(errors, []);
    results.push({ case: 'registered-location', passed: true });
    await page.close();
  }
  {
    const { page, errors } = await authenticatedPage(appToken);
    await page.goto(`${base}/app/configuracoes#aparencia`, { waitUntil: 'networkidle0' });
    await page.waitForSelector('aside');
    const before = await page.$eval('aside', node => ({ top: node.getBoundingClientRect().top, height: node.getBoundingClientRect().height }));
    await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
    await new Promise(resolve => setTimeout(resolve, 150));
    const after = await page.$eval('aside', node => ({ top: node.getBoundingClientRect().top, height: node.getBoundingClientRect().height }));
    assert.equal(before.top, 0); assert.equal(after.top, 0);
    assert.equal(Math.round(after.height), 768);
    assert.equal(await page.evaluate(() => document.body.innerText.includes('Google APIs') || document.body.innerText.includes('Google Maps')), false);
    assert.deepEqual(errors, []);
    await page.screenshot({ path: path.join(artifacts, 'configuracoes-scroll-desktop.png') });
    results.push({ case: 'fixed-sidebar-and-google-removal', before, after, passed: true });
    await page.close();
  }
  {
    const { page, errors } = await authenticatedPage(appToken);
    await page.goto(`${base}/app/house-flipper/importar-itbi`, { waitUntil: 'networkidle0' });
    await page.waitForSelector('input[type=file]');
    const input = await page.$('input[type=file]');
    await input.uploadFile(fixture);
    const state = await page.evaluate(() => ({
      filenameVisible: document.body.innerText.includes('selecao-itbi.csv'),
      button: [...document.querySelectorAll('button')].find(node => node.textContent?.includes('Importar arquivo') || node.textContent?.includes('Permissão de operador necessária'))?.textContent?.trim(),
    }));
    assert.equal(state.filenameVisible, true);
    assert.ok(state.button);
    assert.deepEqual(errors, []);
    await page.screenshot({ path: path.join(artifacts, 'itbi-file-selected.png') });
    results.push({ case: 'itbi-file-selection', ...state, passed: true });
    await page.close();
  }
  for (const viewport of [{ width: 1366, height: 768 }, { width: 390, height: 844 }]) {
    const { page, errors } = await authenticatedPage(appToken, viewport);
    await page.goto(`${base}/app/house-flipper/analises-financeiras/2`, { waitUntil: 'networkidle0' });
    await page.waitForSelector('h1');
    await page.$$eval('button', buttons => buttons.find(node => node.textContent?.includes('Editar análise completa'))?.click());
    await page.waitForFunction(() => [...document.querySelectorAll('button')].some(node => node.textContent?.includes('Custos da Operação')));
    const state = await page.evaluate(() => ({
      title: document.querySelector('h1')?.textContent?.trim(),
      completeAction: document.body.innerText.includes('Editar análise completa') || document.body.innerText.includes('Ver resumo executivo'),
      overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
      tabs: [...document.querySelectorAll('button')].filter(node => ['Viabilidade', 'Financiamento', 'Custos da Operação', 'Resultado'].some(label => node.textContent?.includes(label))).length,
    }));
    assert.equal(state.title, 'Central financeira');
    assert.equal(state.completeAction, true);
    assert.equal(state.overflow, false);
    assert.ok(state.tabs >= 4);
    assert.deepEqual(errors, []);
    await page.screenshot({ path: path.join(artifacts, `analise-${viewport.width}.png`), fullPage: true });
    results.push({ case: `financial-analysis-${viewport.width}`, ...state, passed: true });
    await page.close();
  }
  await writeFile(path.join(artifacts, 'results.json'), JSON.stringify({ generatedAt: new Date().toISOString(), results }, null, 2));
  console.log(`SHELL_ITBI_FINANCE_QA_PASS ${results.length}/${results.length}`);
} finally {
  await browser.close();
}
