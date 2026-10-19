import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const panel = await readFile(new URL('../src/components/AiConsultantPanel.tsx', import.meta.url), 'utf8');
const page = await readFile(new URL('../src/pages/IAInteligente.tsx', import.meta.url), 'utf8');

test('chat composer is a textarea with enter-to-send and a long placeholder', () => {
  assert.match(panel, /Descreva o imóvel que você procura: cidade, bairro, valor, quartos, vagas ou características/);
  assert.match(panel, /event\.key === 'Enter' && !event\.shiftKey/);
  assert.match(panel, /<textarea/);
  assert.match(panel, /Ver 3D/);
  assert.match(panel, /Adicionar à carteira/);
  assert.match(panel, /EXAMPLES = \[/);
  assert.equal([...panel.matchAll(/Quero um apartamento/g)].length, 1);
});

test('IA page keeps chat as default workspace and radar behind a tab', () => {
  assert.match(page, /workspace === 'chat' && <AiConsultantPanel/);
  assert.match(page, /Radar por filtros/);
  assert.match(page, /if \(snapshot\.searched\) setWorkspace\('radar'\)/);
  assert.doesNotMatch(page, /Motor de prospecção inteligente/);
});
