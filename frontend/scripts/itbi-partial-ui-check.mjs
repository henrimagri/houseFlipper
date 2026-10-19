import { resolve } from 'node:path';
import { rm, writeFile } from 'node:fs/promises';
import puppeteer from 'puppeteer-core';
import { browserRuntime } from './browser-runtime.mjs';

const fixture = resolve('../meta/itbi-partial-ui-fixture.csv');
await writeFile(fixture, 'logradouro,numero,valor\nRua QA,10,500000\nRua Inválida,20,abc\n');
const runtime = browserRuntime(); const browser = await puppeteer.launch({ ...runtime, headless: true, args: [...runtime.args, '--disable-gpu'] });
const page = await browser.newPage(); await page.setViewport({ width: 375, height: 812, deviceScaleFactor: 1 });
await page.evaluateOnNewDocument(() => { localStorage.setItem('token', 'fictional-itbi-token'); localStorage.setItem('theme', 'dark'); });
let imported = false; const requests = [], pageErrors = [], consoleErrors = [];
page.on('pageerror', e => pageErrors.push(e.message)); page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()); });
await page.setRequestInterception(true);
page.on('request', request => {
  const url = new URL(request.url()); if (!url.pathname.startsWith('/api/')) return request.continue(); const method = request.method(); requests.push({ method, path: url.pathname });
  const respond = (status, body) => request.respond({ status, contentType: 'application/json', body: JSON.stringify(body) });
  if (method === 'GET' && url.pathname === '/api/auth/me') return respond(200, { id: 7, name: 'Auditoria Visual' });
  if (method === 'GET' && url.pathname === '/api/dashboard') return respond(200, { kpis: { total_invested: 0, realized_profit: 0, realized_roi: 0 }, activities: [] });
  if (method === 'GET' && url.pathname === '/api/itbi-imports') return respond(200, { can_import: true, summary: { total_transactions: imported ? 1 : 0, latest_batch: imported ? { id: 9, status: 'completed_with_errors', rows_total: 2, rows_imported: 1, rows_failed: 1, error_message: 'Linha 2 inválida' } : null }, transactions: { data: imported ? [{ id: 1, address: 'Rua QA, 10', transaction_value: 500000, transaction_date: '2026-10-18' }] : [], meta: { current_page: 1, last_page: 1, total: imported ? 1 : 0 } } });
  if (method === 'POST' && url.pathname === '/api/itbi-imports/upload') { imported = true; return respond(201, { message: 'Importação concluída com 1 linha inválida.', batch: { id: 9, status: 'completed_with_errors', rows_total: 2, rows_imported: 1, rows_failed: 1 } }); }
  return respond(404, { message: `Fixture ausente: ${method} ${url.pathname}` });
});
try {
  await page.goto('http://127.0.0.1:5173/importar-itbi', { waitUntil: 'networkidle0' });
  await page.waitForSelector('input[type=file]'); await (await page.$('input[type=file]')).uploadFile(fixture);
  const before = await page.evaluate(() => ({ button: [...document.querySelectorAll('button')].find(b => b.textContent?.includes('Importar arquivo'))?.disabled, file: document.querySelector('input[type=file]')?.files?.[0]?.name }));
  await page.evaluate(() => [...document.querySelectorAll('button')].find(b => b.textContent?.includes('Importar arquivo'))?.click());
  await page.waitForFunction(() => document.querySelector('[role=status]')?.textContent?.includes('1 linha inválida'));
  const alert = await page.evaluate(() => ({ text: document.querySelector('[role=status]')?.textContent?.trim(), fileCleared: document.querySelector('input[type=file]')?.files?.length === 0, visibleTransaction: document.body.innerText.includes('Rua QA') || document.body.innerText.includes('1') }));
  await page.screenshot({ path: '../meta/itbi-partial-ui-mobile.png', fullPage: true });
  const final = await page.evaluate(() => ({ dark: document.documentElement.classList.contains('dark'), width: innerWidth, scrollWidth: document.documentElement.scrollWidth }));
  const result = { checked_at: '2026-10-19', before, alert, final, requests, page_errors: pageErrors, console_errors: consoleErrors };
  result.passed = before.button === false && before.file === 'itbi-partial-ui-fixture.csv' && alert.text?.includes('Importação concluída') && alert.fileCleared && alert.visibleTransaction && final.dark && final.scrollWidth <= final.width && pageErrors.length === 0 && consoleErrors.length === 0;
  await writeFile('../meta/itbi-partial-ui-checks.json', `${JSON.stringify(result, null, 2)}\n`); if (!result.passed) process.exitCode = 1;
} catch (error) { process.stderr.write(`${error.stack}\nBODY=${await page.evaluate(() => document.body.innerText.slice(-1800))}\nREQUESTS=${JSON.stringify(requests)}\n`); process.exitCode = 1; }
finally { await browser.close(); await rm(fixture, { force: true }); }
