import { createHash } from 'node:crypto';
import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import puppeteer from 'puppeteer-core';
import { browserRuntime } from './browser-runtime.mjs';

const bytes = Buffer.from('%PDF-1.4\n% fixture privado QA\n%%EOF\n');
const fixturePath = resolve('../meta/document-ui-fixture.pdf');
const downloadDir = resolve('../meta/document-ui-download');
await writeFile(fixturePath, bytes); await rm(downloadDir, { recursive: true, force: true }); await mkdir(downloadDir, { recursive: true });
const runtime = browserRuntime(); const browser = await puppeteer.launch({ ...runtime, headless: true, args: [...runtime.args, '--disable-gpu'] });
const page = await browser.newPage(); await page.setViewport({ width: 375, height: 812, deviceScaleFactor: 1 });
await page.evaluateOnNewDocument(() => { localStorage.setItem('token', 'fictional-document-token'); localStorage.setItem('theme', 'dark'); });
await page.createCDPSession().then(session => session.send('Page.setDownloadBehavior', { behavior: 'allow', downloadPath: downloadDir }));
const project = { id: 4, nome: 'Projeto documental QA' }; let documents = []; let failCreate = true;
const requests = [], pageErrors = [], consoleErrors = [];
page.on('pageerror', e => pageErrors.push(e.message)); page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()); });
await page.setRequestInterception(true);
page.on('request', request => {
  const url = new URL(request.url()); if (!url.pathname.startsWith('/api/')) return request.continue();
  const method = request.method(); requests.push({ method, path: url.pathname });
  const respond = (status, body, contentType = 'application/json') => request.respond({ status, contentType, body: contentType === 'application/json' ? JSON.stringify(body) : body });
  const list = data => ({ data, meta: { current_page: 1, last_page: 1, per_page: 100, total: data.length } });
  if (method === 'GET' && url.pathname === '/api/documentos') return respond(200, list(documents));
  if (method === 'GET' && url.pathname === '/api/projetos') return respond(200, list([project]));
  if (method === 'GET' && url.pathname === '/api/auth/me') return respond(200, { id: 7, name: 'Auditoria Visual' });
  if (method === 'GET' && url.pathname === '/api/dashboard') return respond(200, { kpis: { total_invested: 0, realized_profit: 0, realized_roi: 0 }, activities: [] });
  if (method === 'POST' && url.pathname === '/api/documentos') {
    if (failCreate) { failCreate = false; return respond(503, { message: 'Upload offline simulado.' }); }
    documents = [{ id: 41, projeto_id: 4, projeto: project, nome: 'document-ui-fixture.pdf', mime_type: 'application/pdf', tamanho_bytes: bytes.length, categoria: 'Contrato QA', status: 'em_revisao', descricao: 'Arquivo descartável', data_vencimento: '2026-11-20', enviado_por: 'Auditoria Visual', data_inclusao: '2026-10-19T12:00:00Z' }];
    return respond(201, { data: documents[0] });
  }
  if (method === 'PUT' && url.pathname === '/api/documentos/41') { const body = JSON.parse(request.postData() || '{}'); documents = [{ ...documents[0], ...body }]; return respond(200, { data: documents[0] }); }
  if (method === 'GET' && url.pathname === '/api/documentos/41/download') return respond(200, bytes, 'application/pdf');
  if (method === 'DELETE' && url.pathname === '/api/documentos/41') { documents = []; return respond(200, { message: 'Documento excluído.' }); }
  return respond(404, { message: `Fixture ausente: ${method} ${url.pathname}` });
});
const click = async text => { const ok = await page.evaluate(label => { const el = [...document.querySelectorAll('button')].find(node => node.textContent?.trim() === label); el?.click(); return Boolean(el); }, text); if (!ok) throw new Error(`Button not found: ${text}`); };
const setLabel = async (label, value) => page.evaluate(({ label, value }) => { const wrap = [...document.querySelectorAll('dialog label')].find(node => node.textContent?.trim().startsWith(label)); const field = wrap?.querySelector('input:not([type=file]),select,textarea'); if (!field) throw new Error(`Field not found: ${label}`); Object.getOwnPropertyDescriptor(Object.getPrototypeOf(field), 'value')?.set?.call(field, value); field.dispatchEvent(new Event('input', { bubbles: true })); field.dispatchEvent(new Event('change', { bubbles: true })); }, { label, value });
try {
  await page.goto('http://127.0.0.1:5173/documentos', { waitUntil: 'networkidle0' }); await page.waitForFunction(() => document.body.innerText.includes('Nenhum documento cadastrado'));
  await click('Novo documento'); await page.waitForSelector('dialog[open]');
  const focused = await page.evaluate(() => document.activeElement?.type === 'file');
  await (await page.$('dialog input[type=file]')).uploadFile(fixturePath);
  await setLabel('Categoria', 'Contrato QA'); await setLabel('Projeto', '4'); await setLabel('Status', 'em_revisao'); await setLabel('Data de vencimento', '2026-11-20'); await setLabel('Descrição', 'Arquivo descartável');
  await page.click('dialog button[type=submit]'); await page.waitForFunction(() => document.body.innerText.includes('Upload offline simulado.'));
  const failed = await page.evaluate(() => ({ open: Boolean(document.querySelector('dialog[open]')), category: document.querySelector('dialog input[placeholder="Ex: Contratos"]')?.value }));
  await page.click('dialog button[type=submit]'); await page.waitForFunction(() => document.body.innerText.includes('document-ui-fixture.pdf') && !document.querySelector('dialog[open]'));
  await page.reload({ waitUntil: 'networkidle0' }); await page.waitForFunction(() => document.body.innerText.includes('document-ui-fixture.pdf'));
  await page.type('input[placeholder="Nome ou descrição..."]', 'fixture');
  const selects = await page.$$('section[aria-label="Filtros de documentos"] select'); await selects[0].select('4'); await selects[1].select('Contrato QA'); await selects[2].select('em_revisao');
  await page.click('button[aria-label="Editar document-ui-fixture.pdf"]'); await setLabel('Categoria', 'Contrato editado QA'); await setLabel('Status', 'aprovado'); await page.click('dialog button[type=submit]');
  await page.waitForFunction(() => document.body.innerText.includes('Contrato editado QA'));
  await page.evaluate(() => {
    const search = document.querySelector('input[placeholder="Nome ou descrição..."]');
    const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set; setter?.call(search, ''); search?.dispatchEvent(new Event('input', { bubbles: true }));
    document.querySelectorAll('section[aria-label="Filtros de documentos"] select').forEach(select => { const set = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value')?.set; set?.call(select, ''); select.dispatchEvent(new Event('change', { bubbles: true })); });
  });
  await page.waitForSelector('button[aria-label="Baixar document-ui-fixture.pdf"]');
  await page.click('button[aria-label="Baixar document-ui-fixture.pdf"]');
  const downloaded = resolve(downloadDir, 'document-ui-fixture.pdf');
  await new Promise((resolveWait, reject) => { let count = 0; const timer = setInterval(async () => { try { await readFile(downloaded); clearInterval(timer); resolveWait(); } catch { if (++count > 50) { clearInterval(timer); reject(new Error('Download timeout')); } } }, 100); });
  const downloadedHash = createHash('sha256').update(await readFile(downloaded)).digest('hex'); const expectedHash = createHash('sha256').update(bytes).digest('hex');
  await page.click('button[aria-label="Excluir document-ui-fixture.pdf"]'); await page.waitForFunction(() => document.body.innerText.includes('Excluir documento?')); await click('Excluir documento'); await page.waitForFunction(() => document.body.innerText.includes('Nenhum documento cadastrado'));
  await page.screenshot({ path: '../meta/document-ui-crud-mobile.png', fullPage: true });
  const final = await page.evaluate(() => ({ dark: document.documentElement.classList.contains('dark'), width: innerWidth, scrollWidth: document.documentElement.scrollWidth, empty: document.body.innerText.includes('Nenhum documento cadastrado') }));
  const result = { checked_at: '2026-10-19', focused, failed, downloaded_hash: downloadedHash, expected_hash: expectedHash, final, requests, page_errors: pageErrors, console_errors: consoleErrors };
  result.passed = focused && failed.open && failed.category === 'Contrato QA' && downloadedHash === expectedHash && final.dark && final.empty && final.scrollWidth <= final.width && pageErrors.length === 0 && consoleErrors.every(value => value.includes('503'));
  await writeFile('../meta/document-ui-crud-checks.json', `${JSON.stringify(result, null, 2)}\n`); if (!result.passed) process.exitCode = 1;
} catch (error) {
  process.stderr.write(`${error.stack}\nBODY=${await page.evaluate(() => document.body.innerText.slice(-2000))}\nREQUESTS=${JSON.stringify(requests)}\n`); process.exitCode = 1;
} finally { await browser.close(); await rm(fixturePath, { force: true }); }
