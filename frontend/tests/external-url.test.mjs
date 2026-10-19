import assert from 'node:assert/strict';
import test from 'node:test';
import ts from 'typescript';
import vm from 'node:vm';
import { readFile } from 'node:fs/promises';

const source = await readFile(new URL('../src/lib/externalUrl.ts', import.meta.url), 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText;
const module = { exports: {} };
vm.runInNewContext(compiled, { module, exports: module.exports, URL });
const { safeExternalHttpsUrl } = module.exports;

test('external links accept only public credential-free HTTPS URLs', () => {
  assert.equal(safeExternalHttpsUrl('https://portal.example/anuncio?id=1'), 'https://portal.example/anuncio?id=1');
  assert.equal(safeExternalHttpsUrl('https://8.8.8.8/anuncio'), 'https://8.8.8.8/anuncio');
  for (const value of [null, '', '#', 'javascript:alert(1)', 'data:text/html,test', 'http://portal.example/a', 'https://user:pass@portal.example/a', 'https://localhost/a', 'https://api.local/a', 'https://127.0.0.1/a', 'https://10.0.0.5/a', 'https://169.254.169.254/a', 'https://172.16.0.1/a', 'https://192.168.1.1/a', 'https://100.64.0.1/a', 'https://198.18.0.1/a', 'https://2130706433/a', 'https://0x7f000001/a', 'https://0177.0.0.1/a', 'https://127.1/a', 'https://127.0.1/a', 'https://0/a', 'https://[::1]/a', 'https://[::ffff:127.0.0.1]/a', 'https://portal.example:8443/a', 'not a url']) {
    assert.equal(safeExternalHttpsUrl(value), null, String(value));
  }
});

test('AI pages do not create placeholder or unchecked listing links', async () => {
  for (const file of ['IAInteligente.tsx', 'AnaliseMercadoOportunidade.tsx']) {
    const page = await readFile(new URL(`../src/pages/${file}`, import.meta.url), 'utf8');
    assert.match(page, /safeExternalHttpsUrl\(item\.source_url\)/);
    assert.doesNotMatch(page, /href=\{item\.source_url/);
    assert.doesNotMatch(page, /source_url \?\? '#'/);
  }
});

test('opportunity cache revalidates stored external covers and source links', async () => {
  const page = await readFile(new URL('../src/pages/Oportunidades.tsx', import.meta.url), 'utf8');
  assert.match(page, /const thumbnail = safeExternalHttpsUrl\(item\?\.thumbnail\)/);
  assert.match(page, /const sourceUrl = safeExternalHttpsUrl\(item\.source_url\)/);
  assert.doesNotMatch(page, /if \(!item\?\.thumbnail\) continue/);
});

test('market analysis revalidates a cached thumbnail before browser egress', async () => {
  const page = await readFile(new URL('../src/pages/AnaliseMercadoOportunidade.tsx', import.meta.url), 'utf8');
  const start = page.indexOf('function SafeImage(');
  const end = page.indexOf('\nfunction StatusPill(', start);
  const component = page.slice(start, end);

  assert.match(component, /const safeSrc = safeExternalHttpsUrl\(src\)/);
  assert.match(component, /if \(!safeSrc \|\| failed\)/);
  assert.match(component, /<img src=\{safeSrc\}/);
  assert.doesNotMatch(component, /<img src=\{src\}/);
});

test('market analysis declares hooks before the missing-context return and skips requests without an item', async () => {
  const page = await readFile(new URL('../src/pages/AnaliseMercadoOportunidade.tsx', import.meta.url), 'utf8');
  const missingContextReturn = page.indexOf('if (!context)');

  assert.ok(page.indexOf('React.useState', page.indexOf('export default function')) < missingContextReturn);
  assert.ok(page.indexOf('React.useEffect', page.indexOf('export default function')) < missingContextReturn);
  assert.match(page, /if \(!selectedItem\) \{\s*setExternalMarket\(null\)/);
  assert.match(page, /if \(!selectedItem\) \{\s*setLocationContext\(undefined\)/);
});
