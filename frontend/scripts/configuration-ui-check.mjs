import { writeFile } from 'node:fs/promises';
import puppeteer from 'puppeteer-core';
import { browserRuntime } from './browser-runtime.mjs';

const runtime = browserRuntime();
const browser = await puppeteer.launch({
  ...runtime,
  headless: true,
  args: [...runtime.args, '--disable-gpu'],
});

const page = await browser.newPage();
await page.setViewport({ width: 375, height: 812, deviceScaleFactor: 1 });
await page.evaluateOnNewDocument(() => {
  localStorage.setItem('token', 'visual-audit-token');
  localStorage.setItem('theme', 'dark');
});

let sourceAttempts = 0;
let sessionAttempts = 0;
const consoleErrors = [];
const pageErrors = [];
page.on('console', message => {
  if (message.type() === 'error') consoleErrors.push(message.text());
});
page.on('pageerror', error => pageErrors.push(error.message));
await page.setRequestInterception(true);
page.on('request', request => {
  const url = new URL(request.url());
  if (!url.pathname.startsWith('/api/')) {
    request.continue();
    return;
  }

  const json = (status, body) => request.respond({
    status,
    contentType: 'application/json',
    body: JSON.stringify(body),
  });
  if (url.pathname === '/api/auth/me') {
    json(200, { name: 'Auditoria Visual' });
  } else if (url.pathname === '/api/dashboard') {
    json(200, { kpis: { total_invested: 0, realized_profit: 0, realized_roi: 0 }, activities: [] });
  } else if (url.pathname === '/api/data-sources/status') {
    sourceAttempts++;
    if (sourceAttempts === 1) json(503, { message: 'Falha simulada das fontes.' });
    else json(200, { data: [{ id: 'itbi', group: 'Transações reais', name: 'ITBI local', description: 'Base de teste', status: 'no_data', details: { record_count: 0, last_import_at: null } }] });
  } else if (url.pathname === '/api/auth/sessions') {
    sessionAttempts++;
    if (sessionAttempts === 1) json(503, { message: 'Falha simulada das sessões.' });
    else json(200, { data: [{ id: 1, name: 'Navegador de teste', current: true, last_used_at: '2026-10-17T12:00:00Z', created_at: '2026-10-17T11:00:00Z', expires_at: '2026-10-18T11:00:00Z' }] });
  } else {
    json(404, { message: 'Fixture ausente.' });
  }
});

try {
  await page.goto('http://127.0.0.1:5173/configuracoes', { waitUntil: 'networkidle0' });
  await page.waitForFunction(() => document.body.innerText.includes('Falha simulada das fontes.') && document.body.innerText.includes('Falha simulada das sessões.'));
  const failedState = await page.evaluate(() => ({
    path: location.pathname,
    width: innerWidth,
    scrollWidth: document.documentElement.scrollWidth,
    alerts: [...document.querySelectorAll('[role="alert"]')].map(element => element.textContent?.trim()),
    retryButtons: [...document.querySelectorAll('button')].filter(button => button.textContent?.includes('Tentar novamente')).length,
    dark: document.documentElement.classList.contains('dark'),
  }));
  await page.screenshot({ path: '../meta/configuration-ui-mobile-error.png', fullPage: true });

  const retryButtons = await page.$$('button');
  for (const button of retryButtons) {
    const label = await button.evaluate(element => element.textContent?.trim());
    if (label?.includes('Tentar novamente')) await button.click();
  }
  await page.waitForFunction(() => document.body.innerText.includes('Navegador de teste') && document.body.innerText.includes('ITBI local'));
  const recoveredState = await page.evaluate(() => ({
    alerts: document.querySelectorAll('[role="alert"]').length,
    sessionVisible: document.body.innerText.includes('Navegador de teste'),
    sourceVisible: document.body.innerText.includes('ITBI local'),
    scrollWidth: document.documentElement.scrollWidth,
    width: innerWidth,
  }));
  await page.screenshot({ path: '../meta/configuration-ui-mobile-recovered.png', fullPage: true });

  const result = {
    checked_at: '2026-10-17',
    viewport: { width: 375, height: 812 },
    failed_state: failedState,
    recovered_state: recoveredState,
    attempts: { sources: sourceAttempts, sessions: sessionAttempts },
    console_errors: consoleErrors,
    page_errors: pageErrors,
    passed: failedState.path === '/configuracoes'
      && failedState.dark
      && failedState.scrollWidth <= failedState.width
      && failedState.alerts.length === 2
      && failedState.retryButtons === 2
      && recoveredState.alerts === 0
      && recoveredState.sessionVisible
      && recoveredState.sourceVisible
      && recoveredState.scrollWidth <= recoveredState.width
      && sourceAttempts >= 2
      && sessionAttempts >= 2
      && pageErrors.length === 0
      && consoleErrors.every(message => message.includes('503 (Service Unavailable)')),
  };
  await writeFile('../meta/configuration-ui-checks.json', `${JSON.stringify(result, null, 2)}\n`);
  process.stdout.write(`${JSON.stringify(result)}\n`);
  if (!result.passed) process.exitCode = 1;
} finally {
  await browser.close();
}
