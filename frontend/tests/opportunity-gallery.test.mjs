import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile(new URL('../src/pages/Oportunidades.tsx', import.meta.url), 'utf8');

test('opportunity detail exposes an accessible navigable gallery', () => {
  assert.match(source, /function PhotoGallery/);
  assert.match(source, /aria-label="Foto anterior"/);
  assert.match(source, /aria-label="Próxima foto"/);
  assert.match(source, /aria-current=/);
  assert.match(source, /overflow-x-auto/);
});
