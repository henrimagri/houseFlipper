import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile(new URL('../src/pages/IAInteligente.tsx', import.meta.url), 'utf8');
const store = await readFile(new URL('../src/lib/browserStore.ts', import.meta.url), 'utf8');

test('AI search cancels prior work and ignores late responses', () => {
  const start = source.indexOf('async function handleSearch()');
  const end = source.indexOf('\n  useEffect(', start);
  const handler = source.slice(start, end);
  assert.match(handler, /const request = \+\+searchSequence\.current/);
  assert.match(handler, /searchAbort\.current\?\.abort\(\)/);
  assert.match(handler, /signal: controller\.signal/);
  assert.match(handler, /if \(request !== searchSequence\.current\) return/);
  assert.match(handler, /controller\.signal\.aborted \|\| request !== searchSequence\.current/);
  assert.match(handler, /if \(request === searchSequence\.current\)/);
});

test('AI search blocks repeat submission and aborts on unmount', () => {
  assert.match(source, /useEffect\(\(\) => \(\) => searchAbort\.current\?\.abort\(\), \[\]\)/);
  assert.match(source, /<button type="button" disabled=\{loading\} onClick=\{handleSearch\}/);
});

test('market analysis navigation tolerates one unavailable browser storage', () => {
  const helperStart = source.indexOf('function persistMarketAnalysis(');
  const helperEnd = source.indexOf('\nconst propertyTypes', helperStart);
  const helper = source.slice(helperStart, helperEnd);
  const actionStart = source.indexOf('function openMarketAnalysis(');
  const actionEnd = source.indexOf('\n  return <', actionStart);
  const action = source.slice(actionStart, actionEnd);

  assert.match(helper, /return writeJson\(key, payload\)/);
  assert.match(store, /for \(const storage of \[sessionStorage, localStorage\]\)/);
  assert.match(store, /try \{[\s\S]*storage\.setItem\(key, serialized\)[\s\S]*persisted = true[\s\S]*\} catch \{/);
  assert.match(action, /if \(!persistMarketAnalysis\(storageKey, payload\)\)/);
  assert.match(action, /setError\('O navegador não permitiu salvar a análise temporária/);
  assert.match(action, /navigate\(url, \{ state: payload \}\)/);
});
