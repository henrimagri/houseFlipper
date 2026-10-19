import { writeFile } from 'node:fs/promises';
import puppeteer from 'puppeteer-core';
import { browserRuntime } from './browser-runtime.mjs';

const browser = await puppeteer.launch({ ...browserRuntime(), headless: true, args: [...browserRuntime().args, '--disable-gpu'] });
const page = await browser.newPage();
await page.setViewport({ width: 375, height: 812, deviceScaleFactor: 1 });
await page.evaluateOnNewDocument(() => { localStorage.setItem('token', 'fictional-calendar-token'); localStorage.setItem('theme', 'dark'); });
let events = [];
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
  const method = request.method();
  requests.push({ method, path: url.pathname });
  const send = (status, body) => request.respond({ status, contentType: 'application/json', body: JSON.stringify(body) });
  const paginated = data => ({ data, meta: { current_page: 1, last_page: 1, per_page: 100, total: data.length } });
  if (method === 'GET' && url.pathname === '/api/auth/me') return send(200, { id: 7, name: 'Auditoria Visual' });
  if (method === 'GET' && url.pathname === '/api/dashboard') return send(200, { kpis: { total_invested: 0, realized_profit: 0, realized_roi: 0 }, activities: [] });
  if (method === 'GET' && url.pathname === '/api/projetos') return send(200, paginated([{ id: 1, nome: 'Projeto calendário' }]));
  if (method === 'GET' && url.pathname === '/api/eventos-calendario') return send(200, paginated(events));
  if (method === 'POST' && url.pathname === '/api/eventos-calendario') {
    if (failNextCreate) { failNextCreate = false; return send(503, { message: 'Falha offline simulada no calendário.' }); }
    const body = JSON.parse(request.postData() || '{}');
    events = [{ ...body, id: 1, projeto: { id: 1, nome: 'Projeto calendário' }, criado_por: 'Auditoria Visual' }];
    return send(201, { data: events[0] });
  }
  if (method === 'PUT' && url.pathname === '/api/eventos-calendario/1') {
    const body = JSON.parse(request.postData() || '{}');
    events = [{ ...events[0], ...body }];
    return send(200, { data: events[0] });
  }
  if (method === 'DELETE' && url.pathname === '/api/eventos-calendario/1') { events = []; return send(200, { message: 'Evento excluído' }); }
  return send(404, { message: `Fixture ausente: ${method} ${url.pathname}` });
});

const clickText = async text => {
  const ok = await page.evaluate(value => {
    const button = [...document.querySelectorAll('button')].find(item => item.textContent?.trim() === value);
    if (!button) return false;
    button.click(); return true;
  }, text);
  if (!ok) throw new Error(`Button not found: ${text}`);
};
const setField = async (labelText, value) => {
  const handle = await page.evaluateHandle(labelText => {
    const label = [...document.querySelectorAll('dialog label')].find(item => item.textContent?.trim().startsWith(labelText));
    return label?.querySelector('input,textarea,select') ?? null;
  }, labelText);
  const field = handle.asElement();
  if (!field) throw new Error(`Field not found: ${labelText}`);
  const tag = await field.evaluate(element => element.tagName);
  const type = await field.evaluate(element => element.type);
  if (tag === 'SELECT') await field.select(value);
  else if (type === 'datetime-local') {
    await field.evaluate((element, next) => {
      const previous = element.value;
      Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set?.call(element, next);
      element._valueTracker?.setValue(previous);
      element.dispatchEvent(new Event('input', { bubbles: true }));
      element.dispatchEvent(new Event('change', { bubbles: true }));
    }, value);
  } else {
    await field.click();
    await page.keyboard.down('Control');
    await page.keyboard.press('KeyA');
    await page.keyboard.up('Control');
    await field.press('Backspace');
    await field.type(value);
  }
  await handle.dispose();
  await new Promise(resolve => setTimeout(resolve, 100));
};

try {
  await page.goto('http://127.0.0.1:5173/calendario', { waitUntil: 'networkidle0' });
  await page.waitForFunction(() => document.body.innerText.includes('Calendário'), { timeout: 5000 }).catch(async error => {
    process.stderr.write(`${JSON.stringify({ body: await page.evaluate(() => document.body.innerText), requests, consoleErrors, pageErrors })}\n`);
    throw error;
  });
  await new Promise(resolve => setTimeout(resolve, 500));
  await clickText('Novo evento');
  await page.waitForSelector('dialog[open]');
  const focusedInitially = await page.evaluate(() => document.activeElement?.closest('label')?.textContent?.trim().startsWith('Título'));
  await setField('Título', 'Vistoria multidiária descartável');
  await setField('Projeto', '1');
  await setField('Início', '2026-10-19T09:00');
  await setField('Término', '2026-10-21T17:00');
  await setField('Local', 'Imóvel de teste');
  await setField('Descrição', 'Evento criado apenas pela automação local');
  await page.click('dialog button[type="submit"]');
  await page.waitForFunction(() => document.body.innerText.includes('Falha offline simulada no calendário.'), { timeout: 5000 }).catch(async error => {
    process.stderr.write(`${JSON.stringify({ body: await page.evaluate(() => document.body.innerText), form: await page.evaluate(() => ({ valid: document.querySelector('dialog form')?.checkValidity(), values: [...document.querySelectorAll('dialog input,dialog select,dialog textarea')].map(field => ({ type: field.type, value: field.value, valid: field.checkValidity() })) })), requests, consoleErrors, pageErrors })}\n`);
    throw error;
  });
  const failureState = await page.evaluate(() => ({ dialogOpen: Boolean(document.querySelector('dialog[open]')), title: document.querySelector('dialog label input')?.value, alert: document.querySelector('dialog [role="alert"]')?.textContent?.trim() }));
  await page.click('dialog button[type="submit"]');
  await page.waitForFunction(() => document.body.innerText.includes('Evento criado.') && document.body.innerText.includes('Vistoria multidiária descartável'));
  const multiDayOccurrences = await page.evaluate(() => (document.body.innerText.match(/Vistoria multidiária descartável/g) || []).length);
  await page.reload({ waitUntil: 'networkidle0' });
  await page.waitForFunction(() => document.body.innerText.includes('Vistoria multidiária descartável'));

  await page.click('button[aria-label="Editar Vistoria multidiária descartável"]');
  await page.waitForFunction(() => document.body.innerText.includes('Editar evento'));
  await setField('Título', 'Vistoria multidiária atualizada');
  await page.click('dialog button[type="submit"]');
  await page.waitForFunction(() => document.body.innerText.includes('Evento atualizado.') && document.body.innerText.includes('Vistoria multidiária atualizada'));
  if (!await page.$('button[aria-label="Excluir Vistoria multidiária atualizada"]')) {
    await page.evaluate(() => [...document.querySelectorAll('button')].find(button => button.textContent?.includes('Vistoria multidiária atualizada'))?.click());
    await page.waitForSelector('button[aria-label="Excluir Vistoria multidiária atualizada"]', { timeout: 3000 }).catch(async error => {
      process.stderr.write(`${JSON.stringify(await page.evaluate(() => ({ body: document.body.innerText, labels: [...document.querySelectorAll('button[aria-label]')].map(button => button.getAttribute('aria-label')) })))}\n`);
      throw error;
    });
  }

  await page.click('button[aria-label="Excluir Vistoria multidiária atualizada"]');
  await page.waitForFunction(() => document.body.innerText.includes('Excluir evento?'));
  await clickText('Excluir evento');
  await page.waitForFunction(() => document.body.innerText.includes('Evento excluído.') && !document.body.innerText.includes('Vistoria multidiária atualizada'));
  await page.click('button[aria-label="Próximo mês"]');
  await page.waitForFunction(() => document.body.innerText.toLocaleLowerCase('pt-BR').includes('novembro de 2026'));
  await page.click('button[aria-label="Mês anterior"]');
  await page.waitForFunction(() => document.body.innerText.toLocaleLowerCase('pt-BR').includes('outubro de 2026'));
  await page.screenshot({ path: '../meta/calendar-ui-crud-mobile.png', fullPage: true });
  const finalState = await page.evaluate(() => ({ path: location.pathname, dark: document.documentElement.classList.contains('dark'), width: innerWidth, scrollWidth: document.documentElement.scrollWidth, empty: document.body.innerText.includes('Nenhum evento neste dia.') }));
  const result = { checked_at: '2026-10-19', viewport: { width: 375, height: 812 }, focused_initially: focusedInitially, failure_state: failureState, multi_day_occurrences: multiDayOccurrences, final_state: finalState, requests, console_errors: consoleErrors, page_errors: pageErrors };
  result.passed = focusedInitially && failureState.dialogOpen && failureState.title === 'Vistoria multidiária descartável'
    && failureState.alert === 'Falha offline simulada no calendário.' && multiDayOccurrences >= 3
    && requests.some(item => item.method === 'PUT') && requests.some(item => item.method === 'DELETE')
    && finalState.path === '/calendario' && finalState.dark && finalState.scrollWidth <= finalState.width && finalState.empty
    && pageErrors.length === 0 && consoleErrors.every(message => message.includes('503 (Service Unavailable)'));
  await writeFile('../meta/calendar-ui-crud-checks.json', `${JSON.stringify(result, null, 2)}\n`);
  process.stdout.write(`${JSON.stringify(result)}\n`);
  if (!result.passed) process.exitCode = 1;
} finally { await browser.close(); }
