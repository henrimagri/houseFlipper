import { writeFile } from 'node:fs/promises';
import puppeteer from 'puppeteer-core';
import { browserRuntime } from './browser-runtime.mjs';

const runtime = browserRuntime();
const browser = await puppeteer.launch({ ...runtime, headless: true, args: [...runtime.args, '--disable-gpu'] });
const page = await browser.newPage();
await page.setViewport({ width: 375, height: 812, deviceScaleFactor: 1 });
await page.evaluateOnNewDocument(() => { localStorage.setItem('token', 'fictional-property-token'); localStorage.setItem('theme', 'dark'); });

let properties = [];
let failNextSave = true;
const requests = [];
const pageErrors = [];
const consoleErrors = [];
page.on('pageerror', error => pageErrors.push(error.message));
page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
await page.setRequestInterception(true);
page.on('request', request => {
  const url = new URL(request.url());
  if (!url.pathname.startsWith('/api/')) return request.continue();
  const method = request.method(); requests.push({ method, path: url.pathname });
  const respond = (status, body) => request.respond({ status, contentType: 'application/json', body: JSON.stringify(body) });
  const collection = data => ({ data, meta: { current_page: 1, last_page: 1, per_page: 100, total: data.length } });
  if (method === 'GET' && url.pathname === '/api/propriedades') return respond(200, collection(properties));
  if (method === 'GET' && url.pathname === '/api/auth/me') return respond(200, { id: 7, name: 'Auditoria Visual' });
  if (method === 'GET' && url.pathname === '/api/dashboard') return respond(200, { kpis: { active_projects: 0, total_projects: 0, realized_profit: 0, sales_revenue: 0, sold_properties: 0, realized_roi: 0, total_invested: 0 }, performance: [], composition: [], projects: [], activities: [] });
  if (method === 'POST' && url.pathname === '/api/propriedades') {
    if (failNextSave) { failNextSave = false; return respond(503, { message: 'Falha offline simulada.' }); }
    const body = JSON.parse(request.postData() || '{}');
    const item = { id: 31, ...body, endereco: body.endereco ?? {} }; properties = [item];
    return respond(201, { data: item });
  }
  if (method === 'PUT' && url.pathname === '/api/propriedades/31') {
    const body = JSON.parse(request.postData() || '{}'); properties = [{ ...properties[0], ...body, id: 31 }];
    return respond(200, { data: properties[0] });
  }
  if (method === 'DELETE' && url.pathname === '/api/propriedades/31') { properties = []; return respond(200, { message: 'Imóvel excluído.' }); }
  return respond(404, { message: `Fixture ausente: ${method} ${url.pathname}` });
});

const click = async text => {
  const ok = await page.evaluate(label => { const el = [...document.querySelectorAll('button')].find(node => node.textContent?.trim() === label); el?.click(); return Boolean(el); }, text);
  if (!ok) throw new Error(`Button not found: ${text}`);
};
const setLabel = async (label, value) => page.evaluate(({ label, value }) => {
  const wrapper = [...document.querySelectorAll('label')].find(node => node.textContent?.trim().startsWith(label));
  const field = wrapper?.querySelector('input,select,textarea'); if (!field) throw new Error(`Field not found: ${label}`);
  const setter = Object.getOwnPropertyDescriptor(Object.getPrototypeOf(field), 'value')?.set; setter?.call(field, value);
  field.dispatchEvent(new Event('input', { bubbles: true })); field.dispatchEvent(new Event('change', { bubbles: true }));
}, { label, value });

try {
  await page.goto('http://127.0.0.1:5173/imoveis', { waitUntil: 'networkidle0' });
  await page.waitForFunction(() => document.body.innerText.includes('Nenhum imóvel cadastrado'));
  await click('Cadastrar imóvel');
  await page.waitForSelector('[role="dialog"]');
  const focused = await page.evaluate(() => document.activeElement?.closest('label')?.textContent?.includes('Nome do imóvel'));
  await setLabel('Nome do imóvel', 'Imóvel descartável QA');
  await setLabel('Tipo', 'Apartamento');
  await setLabel('Status', 'prospeccao');
  await setLabel('Área', '85.5');
  await setLabel('Valor de aquisição', '400000');
  await setLabel('CEP', '05010000');
  await setLabel('Cidade', 'São Paulo');
  await setLabel('UF', 'SP');
  await page.click('[role="dialog"] button[type="submit"]');
  await page.waitForFunction(() => document.body.innerText.includes('Falha offline simulada.'));
  const failed = await page.evaluate(() => ({ open: Boolean(document.querySelector('[role="dialog"]')), draft: [...document.querySelectorAll('[role="dialog"] input')].some(input => input.value === 'Imóvel descartável QA') }));
  await page.click('[role="dialog"] button[type="submit"]');
  await page.waitForFunction(() => document.body.innerText.includes('Imóvel descartável QA') && !document.querySelector('[role="dialog"]'));
  await page.reload({ waitUntil: 'networkidle0' });
  await page.waitForFunction(() => document.body.innerText.includes('Imóvel descartável QA'));
  await page.type('input[placeholder="Buscar nome, tipo ou endereço"]', 'descartável');
  await page.select('select', 'prospeccao');
  await page.evaluate(() => [...document.querySelectorAll('button')].find(node => node.textContent?.includes('Imóvel descartável QA'))?.click());
  await click('Editar');
  await setLabel('Nome do imóvel', 'Imóvel QA editado');
  await click('Salvar alterações');
  await page.waitForFunction(() => document.body.innerText.includes('Imóvel QA editado'));
  await page.click('button[aria-label="Excluir imóvel"]');
  await page.waitForFunction(() => document.body.innerText.includes('Confirmar exclusão'));
  await click('Confirmar exclusão');
  await page.waitForFunction(() => document.body.innerText.includes('Nenhum imóvel cadastrado'));
  await page.screenshot({ path: '../meta/property-ui-crud-mobile.png', fullPage: true });
  const final = await page.evaluate(() => ({ dark: document.documentElement.classList.contains('dark'), width: innerWidth, scrollWidth: document.documentElement.scrollWidth, empty: document.body.innerText.includes('Nenhum imóvel cadastrado') }));
  const result = { checked_at: '2026-10-19', focused, failed, final, requests, page_errors: pageErrors, console_errors: consoleErrors };
  result.passed = focused && failed.open && failed.draft && final.dark && final.empty && final.scrollWidth <= final.width && pageErrors.length === 0 && consoleErrors.every(value => value.includes('503'));
  await writeFile('../meta/property-ui-crud-checks.json', `${JSON.stringify(result, null, 2)}\n`);
  if (!result.passed) process.exitCode = 1;
} finally { await browser.close(); }
