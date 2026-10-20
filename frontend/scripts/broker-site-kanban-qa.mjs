import assert from 'node:assert/strict';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import puppeteer from 'puppeteer-core';

const base = 'http://127.0.0.1:5173';
const token = (await readFile('../storage/app/qa-broker-token.txt', 'utf8')).trim();
const artifacts = path.resolve('../docs/qa/artifacts/broker-site-kanban');
await mkdir(artifacts, { recursive: true });
const browser = await puppeteer.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', args: ['--disable-dev-shm-usage'] });
const results = [];

try {
  for (const viewport of [{ width: 1366, height: 850 }, { width: 390, height: 844 }]) {
    for (const route of ['/app/onboarding/corretor', '/app/corretor/meu-site', '/app/corretor/comercial']) {
      const page = await browser.newPage();
      await page.setViewport(viewport);
      await page.evaluateOnNewDocument((value) => { localStorage.setItem('token', value); localStorage.setItem('theme', 'dark'); }, token);
      const errors = [];
      page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
      await page.goto(base + route, { waitUntil: 'networkidle0' });
      await page.waitForSelector('h1');
      const state = await page.evaluate(() => ({
        title: document.querySelector('h1')?.textContent?.trim(),
        overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
        bodyText: document.body.innerText,
      }));
      assert.equal(state.overflow, false, `overflow em ${route} (${viewport.width}px)`);
      assert.deepEqual(errors, [], `erros de console em ${route}`);
      if (route.endsWith('onboarding/corretor')) {
        const card = await page.$eval('main .rounded-2xl', (node) => getComputedStyle(node).backgroundColor);
        assert.notEqual(card, 'rgb(255, 255, 255)', 'card branco no modo escuro');
      }
      if (route.endsWith('meu-site')) {
        assert.match(state.bodyText, /Modelos prontos/);
        assert.match(state.bodyText, /Seções do site/);
        const input = await page.$('input[aria-label="Endereço personalizado"]');
        await input.type(' Meu Site Ágil ');
        const value = await page.$eval('input[aria-label="Endereço personalizado"]', (node) => node.value);
        assert.match(value, /^[a-z0-9]+(?:-[a-z0-9]+)*$/);
      }
      if (route.endsWith('comercial')) {
        assert.match(state.bodyText, /Kanban comercial/);
        assert.equal(await page.$$eval('section', (nodes) => nodes.filter((node) => node.textContent?.includes('Solte um cartão aqui') || node.querySelector('[draggable="true"]')).length >= 1), true);
      }
      const name = route.split('/').pop();
      await page.screenshot({ path: path.join(artifacts, `${name}-${viewport.width}.png`), fullPage: true });
      results.push({ route, viewport: viewport.width, title: state.title, passed: true });
      await page.close();
    }
  }
  {
    const page = await browser.newPage();
    await page.setViewport({ width: 1366, height: 850 });
    await page.evaluateOnNewDocument((value) => localStorage.setItem('token', value), token);
    await page.goto(`${base}/app/corretor/meu-site`, { waitUntil: 'networkidle0' });
    const publicPath = await page.$eval('a[href^="/corretor/"]', (node) => node.getAttribute('href'));
    assert.ok(publicPath);
    await page.goto(base + publicPath, { waitUntil: 'networkidle0' });
    const publicState = await page.evaluate(() => ({
      title: document.querySelector('h1')?.textContent?.trim(),
      hasCatalog: document.body.innerText.includes('Imóveis em destaque'),
      overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
    }));
    assert.equal(publicState.hasCatalog, true);
    assert.equal(publicState.overflow, false);
    await page.screenshot({ path: path.join(artifacts, 'vitrine-publica-1366.png'), fullPage: true });
    results.push({ route: publicPath, viewport: 1366, ...publicState, passed: true });
    await page.close();
  }
  await writeFile(path.join(artifacts, 'results.json'), JSON.stringify({ generatedAt: new Date().toISOString(), results }, null, 2));
  console.log(`BROKER_SITE_KANBAN_QA_PASS ${results.length}/${results.length}`);
} finally {
  await browser.close();
}
