import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile(new URL('../src/pages/Oportunidades.tsx', import.meta.url), 'utf8');

test('opportunity remote actions expose a handled error path', () => {
  for (const signature of ['openEdit', 'openDetail', 'favorite', 'openFinancialAnalysis']) {
    const start = source.indexOf(`async function ${signature}(`);
    assert.notEqual(start, -1, `${signature} was not found`);
    const next = source.indexOf('\n  async function ', start + 1);
    const handler = source.slice(start, next === -1 ? source.length : next);
    assert.match(handler, /catch\s*\(/, `${signature} leaves API rejection unhandled`);
    assert.match(handler, /setError\(/, `${signature} does not provide user feedback`);
    assert.match(handler, /finally\s*\{\s*setSaving\(false\)/, `${signature} does not restore its pending state`);
  }
});

test('detail dialog renders action errors inside the active modal', () => {
  const detail = source.slice(source.indexOf("modal === 'detail'"), source.indexOf('{confirmAction'));
  assert.match(detail, /error\s*&&\s*<div role="alert"/);
  assert.match(detail, /disabled=\{saving\}/);
});
