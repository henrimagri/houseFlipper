import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

test('theme provider follows system changes and synchronizes tabs', async () => {
  const source = await readFile(new URL('../src/components/ThemeProvider.tsx', import.meta.url), 'utf8');
  assert.match(source, /matchMedia\('\(prefers-color-scheme: dark\)'\)/);
  assert.match(source, /media\.addEventListener\('change', update\)/);
  assert.match(source, /media\.removeEventListener\('change', update\)/);
  assert.match(source, /window\.addEventListener\('storage', update\)/);
  assert.match(source, /window\.removeEventListener\('storage', update\)/);
  assert.match(source, /isMode\(event\.newValue\)/);
});
