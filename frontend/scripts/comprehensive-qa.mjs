import puppeteer from 'puppeteer-core';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const base = 'http://127.0.0.1:5173';
const artifacts = path.resolve('../docs/qa/artifacts/comprehensive');
await mkdir(artifacts, { recursive: true });
const browser = await puppeteer.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', args: ['--disable-dev-shm-usage'] });
const results = [];

const publicRoutes = ['/', '/solucoes/corretor', '/solucoes/imobiliaria', '/solucoes/house-flipper', '/funcionalidades', '/comparar-produtos', '/precos', '/sobre', '/contato', '/solicitar-demonstracao', '/ajuda', '/termos', '/privacidade', '/entrar', '/cadastro', '/recuperar-senha', '/redefinir-senha', '/confirmar-email', '/rota-inexistente'];
const accountRoutes = ['/app/inicio', '/app/produtos', '/app/assinaturas', '/app/configuracoes', '/app/ajuda'];
const brokerRoutes = ['/app/corretor/dashboard','/app/corretor/pesquisar','/app/corretor/favoritos','/app/corretor/captacao','/app/corretor/carteira','/app/corretor/carteira/1','/app/corretor/meu-site','/app/corretor/marketing','/app/corretor/leads','/app/corretor/agenda','/app/corretor/propostas','/app/corretor/mapa','/app/corretor/ia'];
const agencyRoutes = ['/app/imobiliaria/dashboard','/app/imobiliaria/imoveis','/app/imobiliaria/imoveis/1','/app/imobiliaria/clientes','/app/imobiliaria/proprietarios','/app/imobiliaria/atendimentos','/app/imobiliaria/captacoes','/app/imobiliaria/crm','/app/imobiliaria/roleta','/app/imobiliaria/propostas','/app/imobiliaria/equipes','/app/imobiliaria/filiais','/app/imobiliaria/comissoes','/app/imobiliaria/contratos','/app/imobiliaria/site','/app/imobiliaria/mapa','/app/imobiliaria/relatorios'];
const flipperRoutes = ['/app/house-flipper/dashboard','/app/house-flipper/oportunidades','/app/house-flipper/ia','/app/house-flipper/leiloes','/app/house-flipper/mapa','/app/house-flipper/analises','/app/house-flipper/simulador','/app/house-flipper/imoveis','/app/house-flipper/imoveis/1/3d','/app/house-flipper/projetos','/app/house-flipper/projetos-3d','/app/house-flipper/reformas','/app/house-flipper/financeiro','/app/house-flipper/despesas','/app/house-flipper/obras','/app/house-flipper/tarefas','/app/house-flipper/fornecedores','/app/house-flipper/documentos','/app/house-flipper/relatorios','/app/house-flipper/calendario','/app/house-flipper/importar-itbi','/app/house-flipper/analises-financeiras/1','/app/house-flipper/analises-financeiras/1/wizard'];

async function login(email) {
  const context = await browser.createBrowserContext();
  const page = await context.newPage();
  await page.goto(`${base}/entrar`, { waitUntil: 'networkidle0' });
  await page.type('#login-email', email); await page.type('#login-password', 'password');
  await Promise.all([page.click('button[type=submit]'), page.waitForNavigation({ waitUntil: 'networkidle0' })]);
  return page;
}

async function dismissTour(page) {
  const close = await page.$('[aria-label="Fechar tutorial"]');
  if (close) {
    await close.click();
    return;
  }
  const buttons = await page.$$('button');
  for (const button of buttons) {
    const text = await button.evaluate(node => node.textContent?.trim());
    if (text === 'Pular tutorial' || text === 'Fechar') { await button.click(); break; }
  }
}

async function audit(page, route, profile, mode) {
  const errors = []; const failed = []; const badResponses = [];
  const onConsole = message => { if (message.type() === 'error' && !message.text().startsWith('Failed to load resource')) errors.push(message.text()); };
  const onFailed = request => { if (request.failure()?.errorText !== 'net::ERR_ABORTED') failed.push({ url: request.url(), error: request.failure()?.errorText }); };
  const onResponse = response => { if (response.status() >= 500) badResponses.push({ status: response.status(), url: response.url() }); };
  page.on('console', onConsole); page.on('requestfailed', onFailed); page.on('response', onResponse);
  await page.setViewport({ width: mode.width, height: mode.height });
  await page.evaluateOnNewDocument(theme => localStorage.setItem('theme', theme), mode.theme);
  let mainStatus = 0; let exception = '';
  try {
    if (profile === 'public') {
      const response = await page.goto(`${base}${route}`, { waitUntil: 'domcontentloaded', timeout: 30000 });
      mainStatus = response?.status() ?? 0;
    } else {
      await page.evaluate(({ route, theme }) => {
        localStorage.setItem('theme', theme);
        document.documentElement.classList.toggle('dark', theme === 'dark');
        history.pushState({}, '', route);
        window.dispatchEvent(new PopStateEvent('popstate'));
      }, { route, theme: mode.theme });
      mainStatus = 200;
    }
    await page.waitForFunction(() => !document.body.innerText.trim().startsWith('Carregando ambiente...'), { timeout: 8000 }).catch(() => undefined);
    await new Promise(resolve => setTimeout(resolve, route.includes('mapa') ? 1200 : 450));
    await page.waitForNetworkIdle({ idleTime: 150, timeout: 2500 }).catch(() => undefined);
    await dismissTour(page);
  } catch (error) { exception = String(error); }
  const state = await page.evaluate(() => ({
    url: location.pathname, heading: document.querySelector('h1,h2')?.textContent?.trim() ?? '',
    overflowX: document.body.scrollWidth > document.body.clientWidth + 2,
    dialogs: document.querySelectorAll('[role="dialog"]').length,
    busy: document.querySelectorAll('[aria-busy="true"]').length,
    text: document.body.innerText.slice(0, 220),
  })).catch(() => ({ url: '', heading: '', overflowX: false, dialogs: 0, busy: 0, text: '' }));
  results.push({ profile, route, mode, mainStatus, exception, ...state, errors, failed, badResponses });
  page.off('console', onConsole); page.off('requestfailed', onFailed); page.off('response', onResponse);
}

const modes = [{ width: 375, height: 812, theme: 'light' }, { width: 1366, height: 768, theme: 'light' }, { width: 1366, height: 768, theme: 'dark' }];
const anonymousContext = await browser.createBrowserContext();
const anonymous = await anonymousContext.newPage();
for (const route of publicRoutes) for (const mode of modes) await audit(anonymous, route, 'public', mode);

const profilePages = {};
for (const [email, profile, routes] of [
  ['corretor@houseflipper.local', 'corretor', [...accountRoutes, ...brokerRoutes]],
  ['imobiliaria@houseflipper.local', 'imobiliaria', agencyRoutes],
  ['admin@houseflipper.local', 'house_flipper', flipperRoutes],
]) {
  const page = await login(email);
  profilePages[profile] = page;
  for (const route of routes) for (const mode of modes) await audit(page, route, profile, mode);
}

await writeFile(path.resolve('../docs/qa/comprehensive-results.json'), JSON.stringify(results, null, 2));
const profilePage = profilePages.house_flipper;
await profilePage.goto(`${base}/app/house-flipper/dashboard`, { waitUntil: 'networkidle0' }); await dismissTour(profilePage);
const profileButton = await profilePage.waitForSelector('[data-tour="profile-menu"]', { timeout: 10000 }).catch(() => null);
if (profileButton) {
  const beforeSwitcher = await profilePage.$$eval('[data-tour="product-switcher"]', nodes => nodes.filter(node => { const style = getComputedStyle(node); return style.display !== 'none' && style.visibility !== 'hidden'; }).length);
  await profileButton.click();
  const profileMenu = await profilePage.evaluate(() => ({ open: Boolean(document.querySelector('[role="menu"][aria-label="Perfil e produtos"]')), switchers: document.querySelectorAll('[role="menu"] [data-tour="product-switcher"]').length, bodyLocked: document.body.style.overflow === 'hidden' }));
  results.push({ operation: 'profile-menu-product-switcher', beforeSwitcher, ...profileMenu });
} else results.push({ operation: 'profile-menu-product-switcher', open: false, switchers: 0, beforeSwitcher: -1 });

for (const [route, name] of [['/app/house-flipper/dashboard','flipper-dashboard'],['/app/house-flipper/leiloes','auctions'],['/app/imobiliaria/dashboard','agency-dashboard'],['/app/corretor/meu-site','broker-site'],['/app/assinaturas','subscriptions'],['/cadastro','register']]) {
  const page = route === '/cadastro' ? anonymous : route.includes('imobiliaria') ? profilePages.imobiliaria : route.includes('corretor') ? profilePages.corretor : profilePage;
  for (const viewport of [{width:375,height:812},{width:430,height:932},{width:768,height:1024},{width:1366,height:768},{width:1920,height:1080}]) {
    await page.setViewport(viewport); await page.goto(`${base}${route}`, { waitUntil: 'networkidle0' }); await page.waitForFunction(() => !document.body.innerText.trim().startsWith('Carregando ambiente...'), { timeout: 8000 }).catch(() => undefined); await dismissTour(page);
    await page.screenshot({ path: path.join(artifacts, `${name}-${viewport.width}x${viewport.height}.png`), fullPage: true });
  }
}

await writeFile(path.resolve('../docs/qa/comprehensive-results.json'), JSON.stringify(results, null, 2));
const failures = results.filter(row => row.exception || row.mainStatus >= 500 || row.overflowX || row.errors?.length || row.failed?.length || row.badResponses?.length || (row.operation && (!row.open || row.switchers !== 1 || row.beforeSwitcher !== 0)));
console.log(JSON.stringify({ checks: results.length, failures: failures.length, failureSamples: failures.slice(0, 30) }, null, 2));
await browser.close();
