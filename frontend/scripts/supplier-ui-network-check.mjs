import { writeFile } from 'node:fs/promises';
import puppeteer from 'puppeteer-core';
import { browserRuntime } from './browser-runtime.mjs';

const runtime = browserRuntime();
const browser = await puppeteer.launch({ ...runtime, headless: true, args: [...runtime.args, '--disable-gpu'] });
const page = await browser.newPage();
page.setDefaultTimeout(10000);
await page.setViewport({ width: 375, height: 812, deviceScaleFactor: 1 });
await page.evaluateOnNewDocument(() => {
  localStorage.setItem('token', 'supplier-visual-audit-token');
  localStorage.setItem('theme', 'dark');
});

let postAttempts = 0;
let deleteAttempts = 0;
let created = false;
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
  const json = (status, body) => request.respond({ status, contentType: 'application/json', body: JSON.stringify(body) });
  if (url.pathname === '/api/auth/me') json(200, { name: 'Auditoria Visual' });
  else if (url.pathname === '/api/dashboard') json(200, { kpis: { total_invested: 0, realized_profit: 0, realized_roi: 0 }, activities: [] });
  else if (url.pathname === '/api/fornecedores' && request.method() === 'GET') {
    const data = created ? [{ id: 91, nome: 'Fornecedor Persistido', categoria: 'Materiais', contato: 'Contato Teste', telefone: '(11) 99999-0000', email: 'fornecedor@example.test', avaliacao: 4.5, observacoes: 'Rascunho preservado após falha', ativo_flg: true }] : [];
    json(200, { data, meta: { current_page: 1, last_page: 1, total: data.length, from: data.length ? 1 : null, to: data.length || null } });
  } else if (url.pathname === '/api/fornecedores' && request.method() === 'POST') {
    postAttempts++;
    if (postAttempts === 1) json(503, { message: 'Falha simulada ao salvar.' });
    else {
      created = true;
      json(201, { data: { id: 91 } });
    }
  } else if (url.pathname === '/api/fornecedores/91' && request.method() === 'DELETE') {
    deleteAttempts++;
    json(503, { message: 'Falha simulada ao excluir.' });
  } else json(404, { message: 'Fixture ausente.' });
});

try {
  await page.goto('http://127.0.0.1:5173/fornecedores', { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForFunction(() => document.body.innerText.includes('Nenhum fornecedor encontrado'));
  await page.evaluate(() => [...document.querySelectorAll('button')].find(button => button.textContent?.includes('Novo fornecedor'))?.click());
  await page.waitForSelector('dialog[open]');
  const focusOnFirstField = await page.evaluate(() => document.activeElement === document.querySelector('dialog input'));
  const inputs = await page.$$('dialog input');
  const values = ['Fornecedor Persistido', 'Materiais', 'Contato Teste', '(11) 99999-0000', 'fornecedor@example.test', '4.5'];
  for (let index = 0; index < values.length; index++) await inputs[index].type(values[index]);
  await page.type('dialog textarea', 'Rascunho preservado após falha');
  await page.click('dialog button[type="submit"]');
  await page.waitForFunction(() => document.querySelector('dialog [role="alert"]')?.textContent?.includes('Falha simulada ao salvar.'));
  const failedState = await page.evaluate(() => {
    const dialog = document.querySelector('dialog');
    const fields = [...dialog.querySelectorAll('input')];
    return {
      dialogOpen: dialog?.hasAttribute('open'),
      alert: dialog?.querySelector('[role="alert"]')?.textContent?.trim(),
      values: fields.slice(0, 6).map(field => field.value),
      notes: dialog?.querySelector('textarea')?.value,
      submitDisabled: dialog?.querySelector('button[type="submit"]')?.disabled,
      scrollWidth: document.documentElement.scrollWidth,
      width: innerWidth,
    };
  });
  await page.screenshot({ path: '../meta/supplier-ui-network-error.png', fullPage: true });

  await page.click('dialog button[type="submit"]');
  await page.waitForFunction(() => !document.querySelector('dialog[open]') && document.body.innerText.includes('Fornecedor cadastrado.') && document.body.innerText.includes('Fornecedor Persistido'));
  const recoveredState = await page.evaluate(() => ({
    dialogOpen: Boolean(document.querySelector('dialog[open]')),
    notice: document.querySelector('[role="status"]')?.textContent?.trim(),
    supplierCards: [...document.querySelectorAll('article h2')].filter(element => element.textContent === 'Fornecedor Persistido').length,
    scrollWidth: document.documentElement.scrollWidth,
    width: innerWidth,
  }));
  await page.screenshot({ path: '../meta/supplier-ui-network-recovered.png', fullPage: true });

  await page.click('button[aria-label="Excluir Fornecedor Persistido"]');
  await page.waitForFunction(() => document.querySelector('dialog[open]')?.textContent?.includes('Excluir fornecedor?'));
  await page.evaluate(() => [...document.querySelectorAll('dialog[open] button')].find(button => button.textContent?.trim() === 'Excluir fornecedor')?.click());
  await page.waitForFunction(() => !document.querySelector('dialog[open]') && document.querySelector('[role="alert"]')?.textContent?.includes('Falha simulada ao excluir.'));
  const deleteFailureState = await page.evaluate(() => ({
    dialogOpen: Boolean(document.querySelector('dialog[open]')),
    alert: document.querySelector('[role="alert"]')?.textContent?.trim(),
    supplierCards: [...document.querySelectorAll('article h2')].filter(element => element.textContent === 'Fornecedor Persistido').length,
    scrollWidth: document.documentElement.scrollWidth,
    width: innerWidth,
  }));
  await page.screenshot({ path: '../meta/supplier-ui-delete-error.png', fullPage: true });

  const result = {
    checked_at: '2026-10-18',
    viewport: { width: 375, height: 812 },
    focus_on_first_field: focusOnFirstField,
    failed_state: failedState,
    recovered_state: recoveredState,
    delete_failure_state: deleteFailureState,
    post_attempts: postAttempts,
    delete_attempts: deleteAttempts,
    console_errors: consoleErrors,
    page_errors: pageErrors,
    passed: focusOnFirstField
      && failedState.dialogOpen
      && failedState.alert === 'Falha simulada ao salvar.'
      && JSON.stringify(failedState.values) === JSON.stringify(values)
      && failedState.notes === 'Rascunho preservado após falha'
      && failedState.submitDisabled === false
      && failedState.scrollWidth <= failedState.width
      && !recoveredState.dialogOpen
      && recoveredState.notice === 'Fornecedor cadastrado.'
      && recoveredState.supplierCards === 1
      && recoveredState.scrollWidth <= recoveredState.width
      && postAttempts === 2
      && !deleteFailureState.dialogOpen
      && deleteFailureState.alert?.startsWith('Falha simulada ao excluir.')
      && deleteFailureState.supplierCards === 1
      && deleteFailureState.scrollWidth <= deleteFailureState.width
      && deleteAttempts === 1
      && pageErrors.length === 0
      && consoleErrors.every(message => message.includes('503 (Service Unavailable)')),
  };
  await writeFile('../meta/supplier-ui-network-checks.json', `${JSON.stringify(result, null, 2)}\n`);
  process.stdout.write(`${JSON.stringify(result)}\n`);
  if (!result.passed) process.exitCode = 1;
} catch (error) {
  const diagnostic = await page.evaluate(() => ({
    path: location.pathname,
    dialogOpen: Boolean(document.querySelector('dialog[open]')),
    text: document.body.innerText.slice(0, 3000),
  })).catch(() => null);
  process.stderr.write(`${JSON.stringify({ error: error.message, diagnostic, postAttempts })}\n`);
  process.exitCode = 1;
} finally {
  await browser.close();
}
