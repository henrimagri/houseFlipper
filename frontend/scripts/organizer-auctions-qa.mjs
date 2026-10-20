import assert from 'node:assert/strict';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import puppeteer from 'puppeteer-core';

const base = 'http://127.0.0.1:5173';
const brokerToken = (await readFile('../storage/app/qa-organizer-token.txt', 'utf8')).trim();
const flipperToken = (await readFile('../storage/app/qa-auctions-token.txt', 'utf8')).trim();
const artifacts = path.resolve('../docs/qa/artifacts/organizer-auctions');
await mkdir(artifacts, { recursive: true });
const browser = await puppeteer.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', args: ['--disable-dev-shm-usage'] });
const results = [];

async function open(route, token, viewport) {
  const page = await browser.newPage(); await page.setViewport(viewport);
  await page.evaluateOnNewDocument((value)=>localStorage.setItem('token',value),token);
  const errors=[]; page.on('console',(message)=>{if(message.type()==='error')errors.push(message.text());});
  await page.goto(base+route,{waitUntil:'networkidle0'}); await page.waitForSelector('h1');
  return {page,errors};
}

try {
  for (const viewport of [{width:1366,height:850},{width:390,height:844}]) {
    const {page,errors}=await open('/app/corretor/comercial',brokerToken,viewport);
    await page.$$eval('button',(buttons)=>buttons.find((button)=>button.textContent?.includes('Nova tarefa'))?.click());
    await page.waitForSelector('#personal-task-form input');
    const input=await page.$('#personal-task-form input');
    await input.type('Planejar visita ao imóvel',{delay:35});
    const focus=await page.evaluate(()=>({value:document.querySelector('#personal-task-form input')?.value,active:document.activeElement===document.querySelector('#personal-task-form input'),overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth+2}));
    assert.equal(focus.value,'Planejar visita ao imóvel'); assert.equal(focus.active,true); assert.equal(focus.overflow,false); assert.deepEqual(errors,[]);
    await page.screenshot({path:path.join(artifacts,`organizador-${viewport.width}.png`),fullPage:true});
    results.push({case:`organizer-focus-${viewport.width}`,...focus,passed:true}); await page.close();
  }
  {
    const {page,errors}=await open('/app/house-flipper/leiloes',flipperToken,{width:1366,height:900});
    await new Promise((resolve)=>setTimeout(resolve,1000));
    if (!await page.$('input[aria-label="UF da busca automática"]')) {
      await page.$$eval('button',(buttons)=>buttons.find((button)=>button.textContent?.includes('Pular tutorial'))?.click());
      await page.goto(base+'/app/house-flipper/leiloes',{waitUntil:'networkidle0'});
    }
    await page.waitForSelector('input[aria-label="UF da busca automática"]');
    await page.type('input[aria-label="UF da busca automática"]','AC');
    await page.$$eval('button',(buttons)=>buttons.find((button)=>button.textContent?.includes('Buscar e atualizar automaticamente'))?.click());
    await page.waitForFunction(()=>document.body.innerText.includes('Fonte oficial atualizada:'),{timeout:60000});
    await page.waitForFunction(()=>document.body.innerText.includes('Buscar e atualizar automaticamente'));
    const state=await page.evaluate(()=>({hasResult:/\d+ imóveis? encontrado/.test(document.body.innerText),hasAutomatic:document.body.innerText.includes('Buscar e atualizar automaticamente'),overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth+2}));
    assert.equal(state.hasResult,true); assert.equal(state.hasAutomatic,true); assert.equal(state.overflow,false); assert.deepEqual(errors,[]);
    await page.screenshot({path:path.join(artifacts,'leiloes-automatico-1366.png'),fullPage:true});
    results.push({case:'official-auction-sync',...state,passed:true}); await page.close();
  }
  await writeFile(path.join(artifacts,'results.json'),JSON.stringify({generatedAt:new Date().toISOString(),results},null,2));
  console.log(`ORGANIZER_AUCTIONS_QA_PASS ${results.length}/${results.length}`);
} finally { await browser.close(); }
