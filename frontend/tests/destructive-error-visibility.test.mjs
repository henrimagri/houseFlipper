import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const cases = [
  ['Calendario.tsx', 'async function remove(', 'setDeleteTarget(null)'],
  ['Documentos.tsx', 'async function remove(', 'setDeleteTarget(null)'],
  ['Tarefas.tsx', 'async function remove(', 'setDeleteId(null)'],
  ['Fornecedores.tsx', 'async function remove(', 'setDeleteTarget(null)'],
  ['Oportunidades.tsx', 'async function archive(', 'setConfirmAction(null)'],
  ['Oportunidades.tsx', 'async function remove(', 'setConfirmAction(null)'],
  ['CentralFinanceira.tsx', 'async function deleteFinancingSimulation(', 'setDeleteAction(null)'],
  ['CentralFinanceira.tsx', 'async function deleteCost(', 'setDeleteAction(null)'],
  ['Configuracoes.tsx', 'async function revokeSessions(', 'setSessionAction(null)'],
];

test('destructive action errors dismiss their modal so the page alert is visible', async () => {
  for (const [file, signature, dismiss] of cases) {
    const source = await readFile(new URL(`../src/pages/${file}`, import.meta.url), 'utf8');
    const start = source.indexOf(signature);
    assert.notEqual(start, -1, `${file}: handler ${signature} was not found`);
    const nextHandler = source.indexOf('\n  async function ', start + signature.length);
    const handler = source.slice(start, nextHandler === -1 ? source.length : nextHandler);
    const catchStart = handler.indexOf('catch');
    assert.notEqual(catchStart, -1, `${file}: handler ${signature} has no error path`);
    const catchBranch = handler.slice(catchStart);
    assert.match(catchBranch, new RegExp(dismiss.replace(/[()]/g, '\\$&')), `${file}: ${signature} leaves the modal covering its error alert`);
  }
});
