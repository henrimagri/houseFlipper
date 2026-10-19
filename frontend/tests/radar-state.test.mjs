import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const radar = await readFile(new URL('../src/pages/IAInteligente.tsx', import.meta.url), 'utf8');
const analysis = await readFile(new URL('../src/pages/AnaliseMercadoOportunidade.tsx', import.meta.url), 'utf8');
const store = await readFile(new URL('../src/lib/browserStore.ts', import.meta.url), 'utf8');

test('radar persist skips the empty first write and uses both browser stores', () => {
  assert.match(radar, /skipRadarWrite = useRef\(true\)/);
  assert.match(radar, /if \(skipRadarWrite\.current\) \{\s*skipRadarWrite\.current = false;\s*return;/);
  assert.match(radar, /readJson<RadarStateSnapshot>\(RADAR_STATE_KEY\)/);
  assert.match(radar, /writeJson\(RADAR_STATE_KEY, snapshot\)/);
  assert.match(radar, /navigate\(url, \{ state: payload \}\)/);
});

test('browser store writes session and local copies and can drop thumbnails when oversized', () => {
  assert.match(store, /for \(const storage of \[sessionStorage, localStorage\]\)/);
  assert.match(store, /stripThumbnails/);
  assert.match(store, /MAX_JSON_CHARS = 3_500_000/);
});

test('listing analysis renders illustrative 3D and does not ask for a paid API', () => {
  assert.match(analysis, /ListingLayoutViewer/);
  assert.match(analysis, /Modelo 3D deste imóvel/);
  assert.match(analysis, /Não é fotogrametria nem tour virtual/);
  assert.match(analysis, /writeJson\(`market-analysis:\$\{id\}`, location\.state\)/);
  assert.doesNotMatch(analysis, /API paga\/oficial/);
  assert.doesNotMatch(analysis, /DataZAP, cartório, ITBI normalizado ou base própria/);
  assert.match(analysis, /CKAN \+ BCB/);
});
