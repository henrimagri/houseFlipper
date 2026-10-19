import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import puppeteer from 'puppeteer-core';
import { browserRuntime } from './browser-runtime.mjs';

const output = '../meta/expense-ui-download';
const outputAbsolute = resolve(output);
await rm(output, { recursive: true, force: true }); await mkdir(output, { recursive: true });
const runtime = browserRuntime(); const browser = await puppeteer.launch({ ...runtime, headless: true, args: [...runtime.args, '--disable-gpu'] });
const page = await browser.newPage(); await page.setViewport({ width: 375, height: 812, deviceScaleFactor: 1 });
await page._client().send('Page.setDownloadBehavior', { behavior: 'allow', downloadPath: outputAbsolute });
await page.evaluateOnNewDocument(() => { localStorage.setItem('token', 'fictional-expense-token'); localStorage.setItem('theme', 'dark'); });
const category = { id: 5, nome: 'Materiais', tipo: 'reforma' };
const project = { id: 2, nome: 'Projeto despesas QA', propriedade: { apelido: 'Imóvel QA' } };
let expense = { id: 21, categoria_despesa_id: 5, categoria: category, propriedade_id: 8, projeto_id: 2, descricao: '=RISCO CSV QA', valor: 12345, data_despesa: '2026-10-18', data_pagamento: null, forma_pagamento: 'pix', status_pagamento: 'pendente', documento_numero: 'NF-21', observacoes: 'Observação QA' };
let failUpdate = true; const requests = [], errors = [], consoleErrors = [];
await page.setRequestInterception(true); page.on('pageerror', e => errors.push(e.message));
page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
page.on('request', request => { const url = new URL(request.url()); if (!url.pathname.startsWith('/api/')) return request.continue(); const method = request.method(); requests.push(`${method} ${url.pathname}`); const reply = (status, body) => request.respond({ status, contentType: 'application/json', body: JSON.stringify(body) }); const list = data => ({ data, meta: { current_page: 1, last_page: 1, per_page: 100, total: data.length } });
  if (method === 'GET' && url.pathname === '/api/auth/me') return reply(200, { id: 7, name: 'Auditoria' });
  if (method === 'GET' && url.pathname === '/api/dashboard') return reply(200, { kpis: { total_invested: 12345, realized_profit: 0, realized_roi: 0 }, activities: [] });
  if (method === 'GET' && url.pathname === '/api/despesas') return reply(200, list([expense]));
  if (method === 'GET' && url.pathname === '/api/projetos') return reply(200, list([project]));
  if (method === 'GET' && url.pathname === '/api/categorias-despesa') return reply(200, { data: [category] });
  if (method === 'PUT' && url.pathname === '/api/despesas/21') { if (failUpdate) { failUpdate = false; return reply(503, { message: 'Pagamento offline simulado.' }); } expense = { ...expense, ...JSON.parse(request.postData() || '{}') }; return reply(200, { data: expense }); }
  return reply(404, { message: `Fixture ausente: ${method} ${url.pathname}` });
});
const click = text => page.evaluate(label => { const button = [...document.querySelectorAll('button')].find(el => el.textContent?.trim() === label); if (!button) throw new Error(`Button not found: ${label}`); button.click(); }, text);
try {
  await page.goto('http://127.0.0.1:5173/despesas', { waitUntil: 'networkidle0' }); await page.waitForFunction(() => document.body.innerText.includes('=RISCO CSV QA'));
  await click('Detalhes'); await page.waitForFunction(() => document.body.innerText.includes('Observação QA'));
  await click('Marcar como paga'); await page.waitForFunction(() => document.body.innerText.includes('Pagamento offline simulado.'));
  const failed = await page.evaluate(() => ({ detail: document.body.innerText.includes('Detalhes da despesa'), pending: document.body.innerText.includes('A pagar') }));
  await click('Marcar como paga'); await page.waitForFunction(() => document.body.innerText.includes('Despesa marcada como paga.'));
  const paid = await page.evaluate(() => document.body.innerText.includes('Pago') && !document.body.innerText.includes('Marcar como paga'));
  await click('Exportar CSV'); await new Promise(resolve => setTimeout(resolve, 400));
  const files = await (await import('node:fs/promises')).readdir(output); const csv = files[0] ? await readFile(resolve(output, files[0]), 'utf8') : '';
  await page.screenshot({ path: '../meta/expense-ui-mobile.png', fullPage: true });
  const final = await page.evaluate(() => ({ dark: document.documentElement.classList.contains('dark'), width: innerWidth, scrollWidth: document.documentElement.scrollWidth, overflowX: getComputedStyle(document.documentElement).overflowX, rootWidth: document.querySelector('#root')?.getBoundingClientRect().width }));
  const result = { checked_at: '2026-10-19', failed, paid, csv: { file: files[0], bom: csv.charCodeAt(0) === 0xfeff, neutralized: csv.includes("'=RISCO CSV QA"), has_value: csv.includes('12345') }, final, requests, errors };
  result.passed = failed.detail && failed.pending && paid && result.csv.bom && result.csv.neutralized && result.csv.has_value && final.dark && final.overflowX === 'clip' && final.rootWidth === final.width && !errors.length;
  await writeFile('../meta/expense-ui-checks.json', `${JSON.stringify(result, null, 2)}\n`); if (!result.passed) process.exitCode = 1;
} catch (error) { process.stderr.write(`${error.stack}\n${await page.evaluate(() => document.body.innerText.slice(-1800))}\nERRORS=${JSON.stringify(errors)}\nCONSOLE=${JSON.stringify(consoleErrors)}\nREQUESTS=${JSON.stringify(requests)}\n`); process.exitCode = 1; }
finally { await browser.close(); }
