import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile(new URL('../src/pages/AnaliseMercadoOportunidade.tsx', import.meta.url), 'utf8');

test('analysis cache tries each browser storage independently', () => {
  const start = source.indexOf('function readContext(');
  const end = source.indexOf('\nfunction isMarketContext(', start);
  const helper = source.slice(start, end);

  assert.match(helper, /if \(!id\) return null/);
  assert.match(helper, /for \(const storage of \[sessionStorage, localStorage\]\)/);
  assert.match(helper, /try \{[\s\S]*storage\.getItem\(key\)[\s\S]*JSON\.parse\(raw\)[\s\S]*isMarketContext\(parsed, id\)/);
  assert.match(helper, /catch \{/);
  assert.match(helper, /return null/);
});

test('analysis cache rejects mismatched and oversized structures', () => {
  const start = source.indexOf('function isMarketContext(');
  const end = source.indexOf('\nfunction Metric(', start);
  const helper = source.slice(start, end);

  assert.match(helper, /isMarketItem\(context\.selected\)/);
  assert.match(helper, /context\.selected\.id !== expectedId/);
  assert.match(helper, /context\.comparables\.every\(isMarketItem\)/);
  assert.match(helper, /context\.comparables\.length > 250/);
  assert.match(helper, /context\.diagnostics\.length > 25/);
  assert.match(helper, /item\.source\.length > 120/);
  assert.match(helper, /typeof item\.asking_price === 'number'/);
  assert.match(helper, /Number\.isFinite\(item\.asking_price\)/);
});
