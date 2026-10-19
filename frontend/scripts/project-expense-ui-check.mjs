import { writeFile } from 'node:fs/promises';
import puppeteer from 'puppeteer-core';
import { browserRuntime } from './browser-runtime.mjs';

const runtime = browserRuntime(); const browser = await puppeteer.launch({ ...runtime, headless: true, args: [...runtime.args, '--disable-gpu'] });
const page = await browser.newPage(); await page.setViewport({ width: 375, height: 812, deviceScaleFactor: 1 });
await page.evaluateOnNewDocument(() => { localStorage.setItem('token', 'fictional-project-expense-token'); localStorage.setItem('theme', 'dark'); });
const property = { id: 8, apelido: 'Imóvel QA', endereco: { cidade: 'São Paulo', estado: 'SP' } };
let projects = [{ id: 1, nome: 'Projeto base QA', descricao: 'Fixture', status: 'em_andamento', orcamento_total: 100000, propriedade: property, data_inicio: '2026-09-01', data_termino_prevista: '2026-12-31' }];
let expenses = []; let failProjectCreate = true; let failExpenseCreate = true; let failProjectPatch = true; let failProjectDelete = true;
const category = { id: 5, nome: 'Materiais', tipo: 'reforma' }; const requests = [], pageErrors = [], consoleErrors = [];
const summary = project => ({ orcamento_total: Number(project.orcamento_total), custo_real: expenses.filter(e => e.projeto_id === project.id && e.status_pagamento !== 'cancelado').reduce((s, e) => s + Number(e.valor), 0), economia: Number(project.orcamento_total), consumido_pct: 0, progresso_pct: 25, etapas: [{ id: 11, nome: 'Acabamento', orcamento: 50000, custo_real: 0, economia: 50000, status: 'em_andamento', progresso_pct: 25 }], roi: { lucro: 0, roi_pct: 0, valor_aquisicao: 0, total_despesas: 0, custo_total: 0, valor_liquido_venda: 0, tem_venda: false } });
page.on('pageerror', e => pageErrors.push(e.message)); page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()); });
await page.setRequestInterception(true);
page.on('request', request => {
  const url = new URL(request.url()); if (!url.pathname.startsWith('/api/')) return request.continue(); const method = request.method(); requests.push({ method, path: url.pathname });
  const respond = (status, body) => request.respond({ status, contentType: 'application/json', body: JSON.stringify(body) }); const list = data => ({ data, meta: { current_page: 1, last_page: 1, per_page: 100, total: data.length } });
  if (method === 'GET' && url.pathname === '/api/auth/me') return respond(200, { id: 7, name: 'Auditoria Visual' });
  if (method === 'GET' && url.pathname === '/api/dashboard') return respond(200, { kpis: { total_invested: 0, realized_profit: 0, realized_roi: 0 }, activities: [] });
  if (method === 'GET' && url.pathname === '/api/projetos') return respond(200, list(projects));
  if (method === 'GET' && url.pathname === '/api/propriedades') return respond(200, list([property]));
  if (method === 'GET' && url.pathname === '/api/categorias-despesa') return respond(200, { data: [category] });
  if (method === 'GET' && /^\/api\/projetos\/\d+\/resumo$/.test(url.pathname)) { const id = Number(url.pathname.split('/')[3]); return respond(200, summary(projects.find(p => p.id === id) ?? projects[0])); }
  if (method === 'GET' && url.pathname === '/api/despesas') { const id = Number(url.searchParams.get('projeto_id')); return respond(200, list(expenses.filter(e => !id || e.projeto_id === id))); }
  if (method === 'POST' && url.pathname === '/api/projetos') { if (failProjectCreate) { failProjectCreate = false; return respond(503, { message: 'Criação de projeto offline.' }); } const body = JSON.parse(request.postData() || '{}'); const item = { ...body, id: 2, propriedade: property }; projects.push(item); return respond(201, { data: item }); }
  if (method === 'PATCH' && url.pathname === '/api/projetos/2') { if (failProjectPatch) { failProjectPatch = false; return respond(503, { message: 'Edição de projeto offline.' }); } const body = JSON.parse(request.postData() || '{}'); projects = projects.map(p => p.id === 2 ? { ...p, ...body, propriedade: property } : p); return respond(200, { data: projects.find(p => p.id === 2) }); }
  if (method === 'DELETE' && url.pathname === '/api/projetos/2') { if (failProjectDelete) { failProjectDelete = false; return respond(503, { message: 'Exclusão de projeto offline.' }); } projects = projects.filter(p => p.id !== 2); return respond(200, { message: 'Projeto excluído.' }); }
  if (method === 'POST' && url.pathname === '/api/despesas') { if (failExpenseCreate) { failExpenseCreate = false; return respond(503, { message: 'Despesa offline simulada.' }); } const body = JSON.parse(request.postData() || '{}'); const item = { ...body, id: 21, valor: Number(body.valor), categoria: category }; expenses.push(item); return respond(201, { data: item }); }
  return respond(404, { message: `Fixture ausente: ${method} ${url.pathname}` });
});
const click = async text => { const ok = await page.evaluate(label => { const el = [...document.querySelectorAll('button')].find(node => node.textContent?.trim() === label); el?.click(); return Boolean(el); }, text); if (!ok) throw new Error(`Button not found: ${text}`); };
const setDialogLabel = async (label, value) => page.evaluate(({ label, value }) => { const wrap = [...document.querySelectorAll('dialog label')].find(node => node.textContent?.trim().startsWith(label)); const field = wrap?.querySelector('input,select,textarea'); if (!field) throw new Error(`Field not found: ${label}`); Object.getOwnPropertyDescriptor(Object.getPrototypeOf(field), 'value')?.set?.call(field, value); field.dispatchEvent(new Event('input', { bubbles: true })); field.dispatchEvent(new Event('change', { bubbles: true })); }, { label, value });
const selectCard = async name => page.evaluate(label => [...document.querySelectorAll('button')].find(node => node.textContent?.includes(label))?.click(), name);
try {
  await page.goto('http://127.0.0.1:5173/projetos', { waitUntil: 'networkidle0' }); await page.waitForFunction(() => document.body.innerText.includes('Projeto base QA'));
  await click('Novo projeto manual'); await page.waitForSelector('dialog[open]'); await setDialogLabel('Nome do projeto', 'Projeto novo QA'); await setDialogLabel('Orçamento', '75000');
  await page.click('dialog button[type=submit]'); await page.waitForFunction(() => document.body.innerText.includes('Criação de projeto offline.'));
  const createFailure = await page.evaluate(() => ({ open: Boolean(document.querySelector('dialog[open]')), draft: [...document.querySelectorAll('dialog input')].some(input => input.value === 'Projeto novo QA') }));
  await page.click('dialog button[type=submit]'); await page.waitForFunction(() => document.body.innerText.includes('Projeto novo QA') && !document.querySelector('dialog[open]'));
  await click('Editar'); await page.waitForSelector('dialog[open]'); await setDialogLabel('Nome do projeto', 'Projeto editado QA'); await page.click('dialog button[type=submit]'); await page.waitForFunction(() => document.body.innerText.includes('Edição de projeto offline.'));
  const editFailure = await page.evaluate(() => ({ open: Boolean(document.querySelector('dialog[open]')), draft: [...document.querySelectorAll('dialog input')].some(input => input.value === 'Projeto editado QA') }));
  await page.click('dialog button[type=submit]'); await page.waitForFunction(() => document.body.innerText.includes('Projeto editado QA') && !document.querySelector('dialog[open]'));
  await click('Lançar/ver gastos'); await click('Nova despesa'); await page.waitForSelector('dialog[open]'); await setDialogLabel('Descrição', 'Material QA'); await setDialogLabel('Valor', '12345'); await setDialogLabel('Categoria/meta', '11'); await setDialogLabel('Observações', 'Observação persistida QA');
  await click('Adicionar despesa'); await page.waitForFunction(() => document.body.innerText.includes('Despesa offline simulada.'));
  const expenseFailure = await page.evaluate(() => ({ open: Boolean(document.querySelector('dialog[open]')), draft: [...document.querySelectorAll('dialog input')].some(input => input.value === 'Material QA'), notes: document.querySelector('dialog textarea')?.value }));
  await click('Adicionar despesa'); await page.waitForFunction(() => document.body.innerText.includes('Material QA') && !document.querySelector('dialog[open]'));
  const expensePersisted = await page.evaluate(() => document.body.innerText.includes('Observação persistida QA'));
  await click('Excluir'); await page.waitForFunction(() => document.body.innerText.includes('Excluir projeto?')); await click('Excluir projeto'); await page.waitForFunction(() => document.body.innerText.includes('Exclusão de projeto offline.'));
  const deleteFailure = await page.evaluate(() => ({ dialogClosed: !document.querySelector('dialog[open]'), projectVisible: document.body.innerText.includes('Projeto editado QA') }));
  await click('Excluir'); await click('Excluir projeto'); await page.waitForFunction(() => !document.body.innerText.includes('Projeto editado QA'));
  await page.screenshot({ path: '../meta/project-expense-ui-mobile.png', fullPage: true });
  const final = await page.evaluate(() => ({ dark: document.documentElement.classList.contains('dark'), width: innerWidth, scrollWidth: document.documentElement.scrollWidth, baseVisible: document.body.innerText.includes('Projeto base QA') }));
  const result = { checked_at: '2026-10-19', create_failure: createFailure, edit_failure: editFailure, expense_failure: expenseFailure, expense_persisted: expensePersisted, delete_failure: deleteFailure, final, requests, page_errors: pageErrors, console_errors: consoleErrors };
  result.passed = createFailure.open && createFailure.draft && editFailure.open && editFailure.draft && expenseFailure.open && expenseFailure.draft && expenseFailure.notes === 'Observação persistida QA' && expensePersisted && deleteFailure.dialogClosed && deleteFailure.projectVisible && final.dark && final.baseVisible && final.scrollWidth <= final.width && pageErrors.length === 0 && consoleErrors.every(value => value.includes('503'));
  await writeFile('../meta/project-expense-ui-checks.json', `${JSON.stringify(result, null, 2)}\n`); if (!result.passed) process.exitCode = 1;
} catch (error) { process.stderr.write(`${error.stack}\nBODY=${await page.evaluate(() => document.body.innerText.slice(-2500))}\nREQUESTS=${JSON.stringify(requests)}\n`); process.exitCode = 1; }
finally { await browser.close(); }
