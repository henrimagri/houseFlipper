import puppeteer from 'puppeteer-core';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const base = 'http://127.0.0.1:5173';
const out = path.resolve('../docs/qa/artifacts/premium');
await mkdir(out, { recursive: true });
const browser = await puppeteer.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', args: ['--disable-dev-shm-usage'] });
const results = [];

for (const theme of ['light', 'dark']) {
  for (const width of [375, 430, 768, 1366, 1920]) {
    const page = await browser.newPage(); const errors = [];
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    await page.setViewport({ width, height: width < 700 ? 900 : 960 });
    await page.evaluateOnNewDocument(selected => localStorage.setItem('flipcontrol:theme', selected), theme);
    const response = await page.goto(base, { waitUntil: 'networkidle0' });
    await page.evaluate(() => document.querySelector('section:nth-of-type(1)')?.scrollIntoView());
    await new Promise(resolve => setTimeout(resolve, 700));
    const state = await page.evaluate(() => ({
      overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
      dark: document.documentElement.classList.contains('dark'),
      globeCanvas: document.querySelectorAll('canvas').length,
      heading: document.querySelector('h1')?.textContent?.trim(),
      globeDisclosure: document.body.innerText.includes('Brasil em foco · rede demonstrativa'),
    }));
    await page.screenshot({ path: path.join(out, `home-${theme}-${width}.png`), fullPage: true });
    results.push({ case: 'home', theme, width, status: response?.status(), errors, ...state });
    await page.close();
  }
}

const reduced = await browser.newPage();
await reduced.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
await reduced.goto(base, { waitUntil: 'networkidle0' });
await new Promise(resolve => setTimeout(resolve, 700));
results.push({ case: 'reduced-motion', canvas: await reduced.$eval('body', body => body.querySelectorAll('canvas').length), reduced: await reduced.evaluate(() => matchMedia('(prefers-reduced-motion: reduce)').matches) });
await reduced.close();

const publicActions = await browser.newPage();
await publicActions.setViewport({ width: 1366, height: 900 });
await publicActions.goto(base, { waitUntil: 'networkidle0' });
await publicActions.click('button[aria-label^="Tema:"]');
const themeItems = await publicActions.$$('[role="menuitemradio"]');
await themeItems[1].click();
results.push({ case: 'theme-menu', saved: await publicActions.evaluate(() => localStorage.getItem('flipcontrol:theme')), dark: await publicActions.evaluate(() => document.documentElement.classList.contains('dark')) });
await publicActions.$$eval('button', buttons => buttons.find(button => button.textContent?.trim() === 'Solicitar demonstração')?.click());
results.push({ case: 'commercial-modal', open: await publicActions.$eval('body', body => Boolean(body.querySelector('[role="dialog"] form'))) });
await publicActions.keyboard.press('Escape');
await publicActions.close();

const account = await browser.newPage();
await account.goto(`${base}/entrar`, { waitUntil: 'networkidle0' });
await account.type('#login-email', 'admin@houseflipper.local'); await account.type('#login-password', 'password');
await Promise.all([account.click('button[type=submit]'), account.waitForNavigation({ waitUntil: 'networkidle0' })]);
const accountTour = await account.$('[aria-label="Fechar tutorial"]');
if (accountTour) await accountTour.evaluate(button => button.click());
await account.keyboard.down('Control'); await account.keyboard.press('KeyK'); await account.keyboard.up('Control');
results.push({ case: 'command-palette', open: await account.$eval('body', body => Boolean(body.querySelector('[role="dialog"][aria-label="Comandos e páginas"]'))) });
await account.keyboard.press('Escape');
await account.click('[data-tour="profile-menu"]');
results.push({ case: 'profile-menu', open: await account.$eval('body', body => Boolean(body.querySelector('[role="menu"][aria-label="Perfil e produtos"]'))), productSwitchers: await account.$$eval('[role="menu"] [data-tour="product-switcher"]', nodes => nodes.length) });
await account.close();

await browser.close();
const failures = results.filter(item => item.errors?.length || item.overflow || item.status >= 400 || (item.theme && item.dark !== (item.theme === 'dark')) || (item.case === 'home' && (!item.globeCanvas || !item.globeDisclosure)) || (item.case === 'reduced-motion' && (!item.canvas || !item.reduced)) || (item.case === 'theme-menu' && (item.saved !== 'light' || item.dark)) || ((item.case === 'command-palette' || item.case === 'profile-menu' || item.case === 'commercial-modal') && !item.open));
await writeFile(path.resolve('../docs/qa/premium-results.json'), JSON.stringify({ results, failures }, null, 2));
console.log(JSON.stringify({ checks: results.length, failures: failures.length, failures }, null, 2));
