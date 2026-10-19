import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile(new URL('../src/pages/CentralFinanceira.tsx', import.meta.url), 'utf8');

test('financial market panels do not present unavailable metrics as forthcoming data', () => {
  assert.doesNotMatch(source, /Dados em breve|Preparado para dados reais|['"]Em breve['"]/);
  assert.match(source, /Valorização em 12 meses', 'Não disponível'/);
  assert.match(source, /Tempo médio de venda', 'Não disponível'/);
});

test('property estimate is not labeled as an observed city average', () => {
  assert.doesNotMatch(source, /Preço médio da cidade/);
  assert.match(source, /Valor de mercado estimado/);
  assert.match(source, /Dados informados e calculados/);
});
