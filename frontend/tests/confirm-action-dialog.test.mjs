import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const root = new URL('../src/', import.meta.url);

test('destructive operational actions use the shared accessible confirmation dialog', async () => {
  const component = await readFile(new URL('components/ConfirmActionDialog.tsx', root), 'utf8');
  assert.match(component, /aria-labelledby=/);
  assert.match(component, /aria-describedby=/);
  assert.match(component, /onCancel=/);
  assert.match(component, /trigger\?\.focus\(\)/);
  assert.match(component, /pending \? pendingLabel : actionLabel/);

  for (const page of ['Fornecedores', 'Documentos', 'Calendario', 'Oportunidades', 'CentralFinanceira', 'Tarefas']) {
    const source = await readFile(new URL(`pages/${page}.tsx`, root), 'utf8');
    assert.match(source, /ConfirmActionDialog/, `${page} must use ConfirmActionDialog`);
    assert.doesNotMatch(source, /(?:window\.)?confirm\s*\(/, `${page} must not use the native confirm dialog`);
  }
});
