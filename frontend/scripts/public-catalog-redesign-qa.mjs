import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import puppeteer from 'puppeteer-core';

const base = 'http://127.0.0.1:5173';
const output = path.resolve('../docs/qa/artifacts/public-catalog-redesign');
const cases = [
  { width: 375, height: 812, theme: 'light' },
  { width: 1366, height: 768, theme: 'light' },
  { width: 1366, height: 768, theme: 'dark' },
];
await mkdir(output, { recursive: true });
const browser = await puppeteer.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', args: ['--disable-dev-shm-usage'] });
const results = [];

try {
  for (const route of ['/funcionalidades', '/precos']) {
    for (const viewport of cases) {
      const page = await browser.newPage();
      await page.setViewport(viewport);
      await page.evaluateOnNewDocument(theme => localStorage.setItem('flipcontrol:theme', theme), viewport.theme);
      const errors = [];
      const failures = [];
      page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
      page.on('requestfailed', request => failures.push(request.url()));
      const response = await page.goto(`${base}${route}`, { waitUntil: 'networkidle0', timeout: 30000 });
      await page.waitForSelector(route === '/precos' ? '[data-pricing-plan]' : '[data-product-features]', { timeout: 15000 });
      const state = await page.evaluate(currentRoute => ({
        overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
        title: document.querySelector('h1')?.textContent?.trim(),
        featureCards: document.querySelectorAll('[data-product-features]').length,
        featureRows: document.querySelectorAll('[data-product-features] li').length,
        featureIcons: document.querySelectorAll('[data-product-features] li svg').length,
        pricingCards: document.querySelectorAll('[data-pricing-plan]').length,
        sevenDayCopy: (document.body.innerText.match(/7 dias/g) || []).length,
        technicalCheckoutCopy: document.body.innerText.includes('not_wired'),
        visibleHeadings: [...document.querySelectorAll('[data-pricing-plan] h2')].every(node => {
          const style = getComputedStyle(node); const box = node.getBoundingClientRect();
          return style.visibility !== 'hidden' && style.opacity !== '0' && box.width > 0 && box.height > 0;
        }),
        route: currentRoute,
      }), route);
      const planValues = [];
      const discountedValues = [];
      if (route === '/precos') {
        const expected = [
          { monthly: 'R$ 149', quarterly: 'R$ 134,10', annual: 'R$ 119,20' },
          { monthly: 'R$ 499', quarterly: 'R$ 449,10', annual: 'R$ 399,20' },
          { monthly: 'R$ 249', quarterly: 'R$ 224,10', annual: 'R$ 199,20' },
        ];
        for (let index = 0; index < 3; index += 1) {
          await page.$$eval('[role="tab"]', (tabs, tabIndex) => tabs[tabIndex]?.click(), index);
          await new Promise(resolve => setTimeout(resolve, 120));
          await page.$$eval('button', buttons => buttons.find(button => button.textContent?.trim() === 'Mensal')?.click());
          const monthly = await page.$eval('[data-pricing-plan]:nth-of-type(2) strong', node => node.textContent?.trim());
          await page.$$eval('button', buttons => buttons.find(button => button.textContent?.includes('Trimestral'))?.click());
          const quarterly = await page.$eval('[data-pricing-plan]:nth-of-type(2) strong', node => node.textContent?.trim());
          await page.$$eval('button', buttons => buttons.find(button => button.textContent?.includes('Anual'))?.click());
          const annual = await page.$eval('[data-pricing-plan]:nth-of-type(2) strong', node => node.textContent?.trim());
          planValues.push(monthly);
          discountedValues.push({ monthly, quarterly, annual });
          assert.deepEqual(discountedValues[index], expected[index]);
        }
        assert.deepEqual(planValues, ['R$ 149', 'R$ 499', 'R$ 249']);
        await page.$$eval('[role="tab"]', tabs => tabs[0]?.click());
        await new Promise(resolve => setTimeout(resolve, 120));
        await page.$$eval('button', buttons => buttons.find(button => button.textContent?.includes('Trimestral'))?.click());
        await page.waitForFunction(() => document.body.innerText.replace(/\u00a0/g, ' ').includes('R$ 402,30 cobrados'));
        assert.equal(await page.$eval('[data-pricing-plan]:nth-of-type(2) strong', node => node.textContent?.trim()), 'R$ 134,10');
        await page.$$eval('button', buttons => buttons.find(button => button.textContent?.includes('Anual'))?.click());
        await page.waitForFunction(() => document.body.innerText.replace(/\u00a0/g, ' ').includes('R$ 1.430,40 cobrados'));
        assert.equal(await page.$eval('[data-pricing-plan]:nth-of-type(2) strong', node => node.textContent?.trim()), 'R$ 119,20');
        await page.$$eval('button', buttons => buttons.find(button => button.textContent?.trim() === 'Mensal')?.click());
        await new Promise(resolve => setTimeout(resolve, 120));
      }
      assert.ok((response?.status() ?? 500) < 400);
      assert.equal(state.overflow, false, `overflow em ${route} ${viewport.width}px`);
      if (route === '/funcionalidades') {
        assert.equal(state.featureCards, 3);
        assert.ok(state.featureRows >= 30);
        assert.equal(state.featureIcons, state.featureRows * 2);
      } else {
        assert.equal(state.pricingCards, 2);
        assert.ok(state.sevenDayCopy >= 3);
        assert.equal(state.technicalCheckoutCopy, false);
        assert.equal(state.visibleHeadings, true);
      }
      assert.deepEqual(errors, []);
      assert.deepEqual(failures, []);
      const slug = route.slice(1);
      await page.screenshot({ path: path.join(output, `${slug}-${viewport.theme}-${viewport.width}.png`), fullPage: true });
      results.push({ viewport, ...state, planValues, discountedValues, errors, failures, passed: true });
      await page.close();
    }
  }
  await writeFile(path.join(output, 'results.json'), JSON.stringify({ generatedAt: new Date().toISOString(), results }, null, 2));
  console.log(`PUBLIC_CATALOG_REDESIGN_QA_PASS ${results.length}/${results.length}`);
} finally {
  await browser.close();
}
