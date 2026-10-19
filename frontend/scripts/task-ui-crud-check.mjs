import { writeFile } from 'node:fs/promises';
import puppeteer from 'puppeteer-core';
import { browserRuntime } from './browser-runtime.mjs';

const runtime = browserRuntime();
const browser = await puppeteer.launch({ ...runtime, headless: true, args: [...runtime.args, '--disable-gpu'] });
const page = await browser.newPage();
await page.setViewport({ width: 375, height: 812, deviceScaleFactor: 1 });
await page.evaluateOnNewDocument(() => {
  localStorage.setItem('token', 'fictional-task-ui-token');
  localStorage.setItem('theme', 'dark');
});

const project = { id: 1, nome: 'Projeto descartável', etapas: [{ id: 11, nome: 'Acabamento' }] };
let tasks = [{
  id: 1, projeto_id: 1, projeto: { id: 1, nome: project.nome }, etapa_id: 11, etapa: { id: 11, nome: 'Acabamento' },
  responsavel_user_id: 7, responsavel: { id: 7, nome: 'Auditoria Visual' }, titulo: 'Tarefa inicial descartável',
  descricao: 'Fixture local', data_prevista: '2026-10-30', data_conclusao: null, prioridade: 'alta', status: 'pendente',
  custo_estimado: 100, custo_real: null,
}];
let failNextCreate = true;
const requests = [];
const consoleErrors = [];
const pageErrors = [];
page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
page.on('pageerror', error => pageErrors.push(error.message));
await page.setRequestInterception(true);
page.on('request', request => {
  const url = new URL(request.url());
  if (!url.pathname.startsWith('/api/')) return request.continue();
  const respond = (status, body) => request.respond({ status, contentType: 'application/json', body: JSON.stringify(body) });
  const paginated = data => ({ data, meta: { current_page: 1, last_page: 1, per_page: 100, total: data.length } });
  const method = request.method();
  requests.push({ method, path: url.pathname });
  if (method === 'GET' && url.pathname === '/api/tarefas') return respond(200, paginated(tasks));
  if (method === 'GET' && url.pathname === '/api/projetos') return respond(200, paginated([{ id: 1, nome: project.nome }]));
  if (method === 'GET' && url.pathname === '/api/auth/me') return respond(200, { id: 7, name: 'Auditoria Visual' });
  if (method === 'GET' && url.pathname === '/api/dashboard') return respond(200, { kpis: { total_invested: 0, realized_profit: 0, realized_roi: 0 }, activities: [] });
  if (method === 'GET' && url.pathname === '/api/projetos/1') return respond(200, { data: project });
  if (method === 'POST' && url.pathname === '/api/tarefas') {
    if (failNextCreate) { failNextCreate = false; return respond(503, { message: 'Falha offline simulada.' }); }
    const body = JSON.parse(request.postData() || '{}');
    tasks.push({ ...body, id: 2, projeto: { id: 1, nome: project.nome }, etapa: { id: 11, nome: 'Acabamento' }, responsavel: { id: 7, nome: 'Auditoria Visual' } });
    return respond(201, { data: tasks.at(-1) });
  }
  if (method === 'DELETE' && url.pathname === '/api/tarefas/2') {
    tasks = tasks.filter(task => task.id !== 2);
    return respond(200, { message: 'Tarefa excluída' });
  }
  return respond(404, { message: `Fixture ausente: ${method} ${url.pathname}` });
});

const clickText = async text => {
  const clicked = await page.evaluate(label => {
    const element = [...document.querySelectorAll('button')].find(button => button.textContent?.trim() === label);
    if (!element) return false;
    element.click();
    return true;
  }, text);
  if (!clicked) throw new Error(`Button not found: ${text}`);
};
const setLabelValue = async (labelText, value) => page.evaluate(({ labelText, value }) => {
  const label = [...document.querySelectorAll('label')].find(item => item.textContent?.trim().startsWith(labelText));
  const field = label?.querySelector('input, textarea, select');
  if (!field) throw new Error(`Field not found: ${labelText}`);
  const descriptor = Object.getOwnPropertyDescriptor(Object.getPrototypeOf(field), 'value');
  descriptor?.set?.call(field, value);
  field.dispatchEvent(new Event('input', { bubbles: true }));
  field.dispatchEvent(new Event('change', { bubbles: true }));
}, { labelText, value });

try {
  await page.goto('http://127.0.0.1:5173/tarefas', { waitUntil: 'networkidle0' });
  await page.waitForFunction(() => document.body.innerText.includes('Tarefa inicial descartável'));
  await clickText('Nova tarefa');
  await page.waitForSelector('dialog[open]');
  const focusedInitially = await page.evaluate(() => document.activeElement?.closest('label')?.textContent?.trim().startsWith('Título'));
  await setLabelValue('Título', 'Tarefa criada pela automação');
  await setLabelValue('Etapa', '11');
  await setLabelValue('Prazo', '2026-11-15');
  await setLabelValue('Descrição', 'Rascunho preservado durante a falha');
  const dateInputAccepted = await page.$eval('dialog input[type="date"]', input => input.value === '2026-11-15');

  await clickText('Criar tarefa');
  await page.waitForFunction(() => document.body.innerText.includes('Falha offline simulada.'));
  const failureState = await page.evaluate(() => ({
    dialogOpen: Boolean(document.querySelector('dialog[open]')),
    title: document.querySelector('dialog label input')?.value,
    date: document.querySelector('dialog input[type="date"]')?.value,
    alert: document.querySelector('dialog [role="alert"]')?.textContent?.trim(),
  }));

  await clickText('Criar tarefa');
  await page.waitForFunction(() => document.body.innerText.includes('Tarefa criada.') && document.body.innerText.includes('Tarefa criada pela automação'));
  await page.reload({ waitUntil: 'networkidle0' });
  await page.waitForFunction(() => document.body.innerText.includes('Tarefa criada pela automação'));
  const reloadPersisted = await page.evaluate(() => document.body.innerText.includes('15/11/2026') && document.body.innerText.includes('Rascunho preservado durante a falha'));

  await page.type('input[placeholder="Buscar título, projeto ou etapa"]', 'automação');
  await page.waitForFunction(() => !document.body.innerText.includes('Tarefa inicial descartável'));
  const filtered = await page.evaluate(() => ({ cards: document.querySelectorAll('[aria-label="Lista de tarefas"] article').length, visible: document.body.innerText.includes('Tarefa criada pela automação') }));
  await page.$eval('input[placeholder="Buscar título, projeto ou etapa"]', input => {
    const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set;
    setter?.call(input, '');
    input.dispatchEvent(new Event('input', { bubbles: true }));
  });
  await page.waitForFunction(() => document.body.innerText.includes('Tarefa inicial descartável'));

  await page.click('button[aria-label="Excluir Tarefa criada pela automação"]');
  await page.waitForFunction(() => document.body.innerText.includes('Excluir tarefa?'));
  await clickText('Excluir tarefa');
  await page.waitForFunction(() => document.body.innerText.includes('Tarefa excluída.') && !document.body.innerText.includes('Tarefa criada pela automação'));
  await page.screenshot({ path: '../meta/task-ui-crud-mobile.png', fullPage: true });
  const finalState = await page.evaluate(() => ({
    path: location.pathname,
    dark: document.documentElement.classList.contains('dark'),
    width: innerWidth,
    scrollWidth: document.documentElement.scrollWidth,
    initialVisible: document.body.innerText.includes('Tarefa inicial descartável'),
    deletedVisible: document.body.innerText.includes('Tarefa criada pela automação'),
  }));
  const result = {
    checked_at: '2026-10-19', viewport: { width: 375, height: 812 }, focused_initially: focusedInitially,
    date_input_accepted: dateInputAccepted, failure_state: failureState, reload_persisted: reloadPersisted,
    filtered_state: filtered, final_state: finalState, requests, console_errors: consoleErrors, page_errors: pageErrors,
  };
  result.passed = focusedInitially && dateInputAccepted && failureState.dialogOpen
    && failureState.title === 'Tarefa criada pela automação' && failureState.date === '2026-11-15'
    && failureState.alert === 'Falha offline simulada.' && reloadPersisted && filtered.cards === 1 && filtered.visible
    && finalState.path === '/tarefas' && finalState.dark && finalState.scrollWidth <= finalState.width
    && finalState.initialVisible && !finalState.deletedVisible && pageErrors.length === 0
    && consoleErrors.every(message => message.includes('503 (Service Unavailable)'));
  await writeFile('../meta/task-ui-crud-checks.json', `${JSON.stringify(result, null, 2)}\n`);
  process.stdout.write(`${JSON.stringify(result)}\n`);
  if (!result.passed) process.exitCode = 1;
} finally {
  await browser.close();
}
