import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import puppeteer from 'puppeteer-core';

const out = path.resolve('../docs/qa/artifacts/globe-refinement');
await mkdir(out, { recursive: true });
const browser = await puppeteer.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', args: ['--disable-dev-shm-usage', '--use-gl=angle', '--use-angle=swiftshader'] });
const results = [];
try {
  for (const theme of ['light', 'dark']) {
    const page = await browser.newPage();
    await page.setViewport({ width: 1366, height: 768 });
    await page.evaluateOnNewDocument(value => localStorage.setItem('flipcontrol:theme', value), theme);
    const errors = [];
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    await page.goto('http://127.0.0.1:5173', { waitUntil: 'networkidle0', timeout: 30000 });
    await page.waitForSelector('[data-component="aceternity-3d-globe-demo"] canvas');
    await page.evaluate(() => document.querySelector('[data-component="aceternity-3d-globe-demo"]')?.scrollIntoView({ block: 'center' }));
    await new Promise(resolve => setTimeout(resolve, 700));
    const visibleLabel = await page.$$eval('button[aria-label^="Selecionar "]', buttons => buttons.find(button => {
      const box = button.getBoundingClientRect();
      const target = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2);
      return target === button || Boolean(target && button.contains(target));
    })?.getAttribute('aria-label'));
    assert.ok(visibleLabel, 'nenhum marcador frontal interativo');
    const markerPosition = label => page.$eval(`button[aria-label="${label}"]`, button => {
      const box = button.getBoundingClientRect();
      return { x: box.left + box.width / 2, y: box.top + box.height / 2 };
    });
    const rotationStart = await markerPosition(visibleLabel);
    await new Promise(resolve => setTimeout(resolve, 1600));
    const rotationEnd = await markerPosition(visibleLabel);
    const rotationDistance = Math.hypot(rotationEnd.x - rotationStart.x, rotationEnd.y - rotationStart.y);
    assert.ok(rotationDistance < 1, `globo se moveu sem interação: ${rotationDistance}px`);
    const canvas = await page.$('[data-component="aceternity-3d-globe-demo"] canvas');
    const canvasBox = await canvas?.boundingBox();
    assert.ok(canvasBox, 'canvas do globo sem área interativa');
    const dragStart = await markerPosition(visibleLabel);
    await page.mouse.move(canvasBox.x + canvasBox.width * .58, canvasBox.y + canvasBox.height * .5);
    await page.mouse.down();
    await page.mouse.move(canvasBox.x + canvasBox.width * .64, canvasBox.y + canvasBox.height * .5, { steps: 8 });
    await page.mouse.up();
    await new Promise(resolve => setTimeout(resolve, 700));
    const dragEnd = await markerPosition(visibleLabel);
    const manualDistance = Math.hypot(dragEnd.x - dragStart.x, dragEnd.y - dragStart.y);
    assert.ok(manualDistance > 2, `arraste manual não girou o globo: ${manualDistance}px`);
    const marker = await page.$(`button[aria-label="${visibleLabel}"]`);
    assert.ok(marker, 'marcador frontal ausente');
    await marker.hover();
    await new Promise(resolve => setTimeout(resolve, 180));
    const state = await page.evaluate((markerLabel) => {
      const component = document.querySelector('[data-component="aceternity-3d-globe-demo"]');
      const stage = component?.firstElementChild;
      const button = document.querySelector(`button[aria-label="${markerLabel}"]`);
      const tooltip = button?.querySelector('span:last-child');
      const buttonBox = button?.getBoundingClientRect();
      const tooltipBox = tooltip?.getBoundingClientRect();
      return {
        selected: component?.querySelector('aside h2')?.textContent,
        background: stage ? getComputedStyle(stage).backgroundColor : '',
        overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
        button: buttonBox && { width: buttonBox.width, height: buttonBox.height },
        tooltip: tooltipBox && { width: tooltipBox.width, height: tooltipBox.height, text: tooltip?.textContent, display: getComputedStyle(tooltip).display },
        transformedMarkerContent: Boolean(button?.closest('[style*="matrix3d"]')),
      };
    }, visibleLabel);
    console.log(JSON.stringify({ theme, stationaryDistance: rotationDistance, manualDistance, state }, null, 2));
    assert.equal(state.selected, 'São Paulo');
    assert.equal(state.background, 'rgb(3, 8, 23)');
    assert.equal(state.overflow, false);
    assert.ok(state.button && state.button.width >= 31 && state.button.width <= 33 && state.button.height >= 31 && state.button.height <= 33);
    assert.ok(state.tooltip && state.tooltip.display !== 'none' && visibleLabel.endsWith(state.tooltip.text || '') && state.tooltip.width < 130);
    assert.equal(state.transformedMarkerContent, false);
    assert.deepEqual(errors, []);
    const component = await page.$('[data-component="aceternity-3d-globe-demo"]');
    await component.screenshot({ path: path.join(out, `globe-${theme}-hover.png`) });
    results.push({ theme, stationaryDistance: rotationDistance, manualDistance, ...state, errors, passed: true });
    await page.close();
  }
  await writeFile(path.resolve('../docs/qa/globe-refinement-results.json'), JSON.stringify({ generatedAt: new Date().toISOString(), results }, null, 2));
  console.log(`GLOBE_REFINEMENT_QA_PASS ${results.length}/${results.length}`);
} finally { await browser.close(); }
