import puppeteer from 'puppeteer-core';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const base = 'http://127.0.0.1:5173';
const artifacts = path.resolve('../docs/qa/artifacts');
await mkdir(artifacts, { recursive: true });
const browser = await puppeteer.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
const results = [];

async function clickText(page, text) {
  return page.$$eval('button,a', (elements, expected) => {
    const element = elements.find((item) => item.textContent?.trim() === expected);
    if (!element) return false;
    element.click();
    return true;
  }, text);
}

async function login(page, email) {
  await page.goto(`${base}/entrar`, { waitUntil: 'networkidle0' });
  await page.type('#login-email', email);
  await page.type('#login-password', 'password');
  await Promise.all([page.click('button[type=submit]'), page.waitForNavigation({ waitUntil: 'networkidle0' })]);
}

async function visit(page, route, name, viewport, screenshot = true) {
  const consoleErrors = [];
  const failed = [];
  const onConsole = (message) => { if (message.type() === 'error') consoleErrors.push(message.text()); };
  const onFailed = (request) => failed.push({ url: request.url(), reason: request.failure()?.errorText });
  page.on('console', onConsole); page.on('requestfailed', onFailed);
  const responses = [];
  const onResponse = (response) => { if (response.status() >= 400) responses.push({ status: response.status(), url: response.url() }); };
  page.on('response', onResponse);
  await page.setViewport(viewport);
  await page.goto(`${base}${route}`, { waitUntil: 'networkidle0' });
  await new Promise((resolve) => setTimeout(resolve, route.includes('mapa') || route.includes('dashboard') ? 1200 : 250));
  const tourClose = await page.$('[aria-label="Fechar tutorial"]');
  if (tourClose) await tourClose.click();
  else await clickText(page, 'Pular tutorial');
  const state = await page.evaluate(() => ({
    title: document.title,
    heading: document.querySelector('h1,h2')?.textContent?.trim() ?? '',
    overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
    mapCanvases: document.querySelectorAll('.maplibregl-canvas').length,
    body: document.body.innerText.slice(0, 300),
  }));
  if (screenshot) await page.screenshot({ path: path.join(artifacts, `${name}-${viewport.width}x${viewport.height}.png`), fullPage: true });
  results.push({ name, route, viewport, ...state, consoleErrors, failed, responses });
  page.off('console', onConsole); page.off('requestfailed', onFailed); page.off('response', onResponse);
}

const agency = await browser.newPage();
await login(agency, 'imobiliaria@houseflipper.local');
for (const viewport of [{ width: 375, height: 812 }, { width: 768, height: 1024 }, { width: 1366, height: 768 }, { width: 1920, height: 1080 }]) {
  await visit(agency, '/app/imobiliaria/dashboard', 'dashboard-imobiliaria', viewport);
}
for (const [route, name] of [
  ['/app/imobiliaria/crm', 'crm'],
  ['/app/imobiliaria/atendimentos', 'atendimentos'],
  ['/app/imobiliaria/site', 'site-imobiliaria'],
  ['/app/imobiliaria/mapa', 'mapa-imobiliaria'],
  ['/app/configuracoes', 'configuracoes'],
]) await visit(agency, route, name, { width: 1366, height: 768 });

// CRM persistence and history.
await agency.goto(`${base}/app/imobiliaria/crm`, { waitUntil: 'networkidle0' });
const dealTitle = `QA CRM ${Date.now()}`;
const crmResult = await agency.evaluate(async (title) => {
  const token = localStorage.getItem('token');
  const headers = { Authorization: `Bearer ${token}`, Accept: 'application/json', 'Content-Type': 'application/json', 'X-Product-Context': 'imobiliaria' };
  const created = await fetch('/api/imobiliaria/deals', { method: 'POST', headers, body: JSON.stringify({ title }) });
  const createdBody = await created.json();
  if (!created.ok) return { created: created.status, body: createdBody };
  const moved = await fetch(`/api/imobiliaria/deals/${createdBody.data.id}/stage`, { method: 'PATCH', headers, body: JSON.stringify({ stage: 'contato', lock_version: createdBody.data.lock_version }) });
  const detail = await fetch(`/api/imobiliaria/deals/${createdBody.data.id}`, { headers });
  const detailBody = await detail.json();
  return { created: created.status, moved: moved.status, persisted: detailBody.data?.stage === 'contato', history: detailBody.data?.activities?.some((item) => item.kind === 'stage_changed') };
}, dealTitle);
results.push({ operation: 'crm-create-move-persist', title: dealTitle, ...crmResult });

// Ticket creation, message and close/reopen workflow.
await agency.goto(`${base}/app/imobiliaria/atendimentos`, { waitUntil: 'networkidle0' });
const ticketTitle = `QA Atendimento ${Date.now()}`;
const ticketResult = await agency.evaluate(async (subject) => {
  const token = localStorage.getItem('token');
  const headers = { Authorization: `Bearer ${token}`, Accept: 'application/json', 'Content-Type': 'application/json', 'X-Product-Context': 'imobiliaria' };
  const created = await fetch('/api/imobiliaria/tickets', { method: 'POST', headers, body: JSON.stringify({ subject, channel: 'internal', priority: 'alta' }) });
  const body = await created.json();
  if (!created.ok) return { created: created.status, body };
  const id = body.data.id;
  const message = await fetch(`/api/imobiliaria/tickets/${id}/messages`, { method: 'POST', headers, body: JSON.stringify({ body: 'Registro automatizado de QA local.', kind: 'note' }) });
  const closed = await fetch(`/api/imobiliaria/tickets/${id}`, { method: 'PATCH', headers, body: JSON.stringify({ status: 'encerrado' }) });
  const reopened = await fetch(`/api/imobiliaria/tickets/${id}`, { method: 'PATCH', headers, body: JSON.stringify({ status: 'em_andamento' }) });
  const detail = await fetch(`/api/imobiliaria/tickets/${id}`, { headers });
  const detailBody = await detail.json();
  return { created: created.status, message: message.status, closed: closed.status, reopened: reopened.status, persisted: detailBody.data?.status === 'em_andamento' && detailBody.data?.messages?.length > 0 };
}, ticketTitle);
results.push({ operation: 'ticket-create-message-close-reopen', title: ticketTitle, ...ticketResult });

// Site draft, publication, public load and unpublish.
await agency.goto(`${base}/app/imobiliaria/site`, { waitUntil: 'networkidle0' });
const siteResult = await agency.evaluate(async () => {
  const token = localStorage.getItem('token');
  const headers = { Authorization: `Bearer ${token}`, Accept: 'application/json', 'Content-Type': 'application/json', 'X-Product-Context': 'imobiliaria' };
  const current = await (await fetch('/api/imobiliaria/site', { headers })).json();
  const response = await fetch('/api/imobiliaria/site', { method: 'PUT', headers, body: JSON.stringify({ slug: current.data.slug, theme: current.data.theme_json ?? {}, publish: true }) });
  const body = await response.json();
  return { status: response.status, publicPath: `/i/${body.data?.slug}` };
});
const publicPath = siteResult.publicPath;
let publicStatus = null;
if (publicPath) publicStatus = (await agency.goto(`${base}${publicPath}`, { waitUntil: 'networkidle0' })).status();
await agency.goto(`${base}/app/imobiliaria/site`, { waitUntil: 'networkidle0' });
const unpublishStatus = await agency.evaluate(async () => {
  const token = localStorage.getItem('token');
  const headers = { Authorization: `Bearer ${token}`, Accept: 'application/json', 'Content-Type': 'application/json', 'X-Product-Context': 'imobiliaria' };
  const current = await (await fetch('/api/imobiliaria/site', { headers })).json();
  const response = await fetch('/api/imobiliaria/site', { method: 'PUT', headers, body: JSON.stringify({ slug: current.data.slug, theme: current.data.theme_json ?? {}, publish: false }) });
  return response.status;
});
results.push({ operation: 'agency-site-publish-unpublish', ...siteResult, publicStatus, unpublishStatus });

const flipper = await browser.newPage();
await login(flipper, 'admin@houseflipper.local');
for (const viewport of [{ width: 375, height: 812 }, { width: 1366, height: 768 }]) {
  await visit(flipper, '/app/house-flipper/dashboard', 'dashboard-house-flipper', viewport);
}
await visit(flipper, '/analises-financeiras/1', 'analise-financeira', { width: 1366, height: 768 });

const broker = await browser.newPage();
await login(broker, 'corretor@houseflipper.local');
for (const viewport of [{ width: 375, height: 812 }, { width: 1366, height: 768 }]) {
  await visit(broker, '/app/corretor/dashboard', 'dashboard-corretor', viewport);
}

await writeFile(path.resolve('../docs/qa/browser-results.json'), JSON.stringify(results, null, 2));
console.log(JSON.stringify({ checks: results.length, failures: results.filter((row) => row.consoleErrors?.length || row.failed?.length || row.responses?.length || row.overflow || row.persisted === false), results }, null, 2));
await browser.close();
