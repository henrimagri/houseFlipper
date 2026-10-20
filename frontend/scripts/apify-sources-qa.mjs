import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import puppeteer from 'puppeteer-core';

const base = 'http://127.0.0.1:5173';
const output = path.resolve('../docs/qa/artifacts/apify-sources');
await mkdir(output, { recursive: true });
const browser = await puppeteer.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', args: ['--disable-dev-shm-usage'] });
const results = [];

try {
  const page = await browser.newPage();
  await page.goto(`${base}/entrar`, { waitUntil: 'networkidle0' });
  await page.type('#login-email', 'corretor@houseflipper.local');
  await page.type('#login-password', 'password');
  await Promise.all([page.click('button[type=submit]'), page.waitForNavigation({ waitUntil: 'networkidle0' })]);

  for (const viewport of [{ width: 1366, height: 900 }, { width: 390, height: 844 }]) {
    await page.setViewport(viewport);
    await page.goto(`${base}/app/corretor/ia`, { waitUntil: 'networkidle0' });
    const close = await page.$('[aria-label="Fechar tutorial"]');
    if (close) await close.click();
    await page.$$eval('button', buttons => buttons.find(button => button.textContent?.includes('Radar por filtros'))?.click());
    await page.waitForFunction(() => document.body.innerText.includes('Portais ligados ao radar'));

    const state = await page.evaluate(() => ({
      sources: ['ZAP Imóveis', 'VivaReal', 'OLX', 'ImovelWeb', 'Chaves na Mão', 'Casa Mineira', 'Loft', 'QuintoAndar', 'Mercado Livre', 'Facebook Marketplace'].filter(name => document.body.innerText.includes(name)),
      overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
      hasCredentialCopy: document.body.innerText.includes('Credenciais ficam somente no servidor'),
    }));
    assert.equal(state.sources.length, 10);
    assert.equal(state.overflow, false);
    assert.equal(state.hasCredentialCopy, true);
    await page.screenshot({ path: path.join(output, `ia-fontes-${viewport.width}.png`), fullPage: true });
    results.push({ viewport, ...state, passed: true });
  }

  await writeFile(path.join(output, 'results.json'), JSON.stringify({ generatedAt: new Date().toISOString(), results }, null, 2));
  console.log(`APIFY_SOURCES_QA_PASS ${results.length}/${results.length}`);
} finally {
  await browser.close();
}
