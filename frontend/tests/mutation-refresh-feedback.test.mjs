import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const cases = [
  ['Calendario.tsx', 'async function save(', 'agenda não pôde ser recarregada'],
  ['Calendario.tsx', 'async function remove(', 'agenda não pôde ser recarregada'],
  ['Documentos.tsx', 'async function save(', 'lista não pôde ser recarregada'],
  ['Documentos.tsx', 'async function remove(', 'lista não pôde ser recarregada'],
  ['Fornecedores.tsx', 'async function save(', 'lista não pôde ser recarregada'],
  ['Fornecedores.tsx', 'async function remove(', 'lista não pôde ser recarregada'],
  ['Despesas.tsx', 'async function markPaid(', 'lista não pôde ser recarregada'],
  ['AnaliseImovel.tsx', 'async function save(', 'lista não pôde ser recarregada'],
  ['AnaliseImovel.tsx', 'async function removeSelected(', 'lista não pôde ser recarregada'],
  ['Tarefas.tsx', 'async function save(', 'lista não pôde ser recarregada'],
  ['Tarefas.tsx', 'async function complete(', 'lista não pôde ser recarregada'],
  ['Tarefas.tsx', 'async function remove(', 'lista não pôde ser recarregada'],
  ['Oportunidades.tsx', 'async function save(', 'lista não pôde ser recarregada'],
  ['Oportunidades.tsx', 'async function favorite(', 'lista não pôde ser recarregada'],
  ['Oportunidades.tsx', 'async function archive(', 'lista não pôde ser recarregada'],
  ['Oportunidades.tsx', 'async function remove(', 'lista não pôde ser recarregada'],
];

function handlerFrom(source, signature) {
  const start = source.indexOf(signature);
  assert.notEqual(start, -1, `handler ${signature} was not found`);
  const next = source.indexOf('\n  async function ', start + signature.length);
  return source.slice(start, next === -1 ? source.length : next);
}

test('successful mutations distinguish a failed follow-up refresh', async () => {
  for (const [file, signature, staleMessage] of cases) {
    const source = await readFile(new URL(`../src/pages/${file}`, import.meta.url), 'utf8');
    const handler = handlerFrom(source, signature);
    assert.match(handler, /const refreshed = await (?:load|loadEvents)\(/, `${file}: ${signature} does not inspect the refresh result`);
    assert.match(handler, /(?:refreshed\s*\?|if \(!refreshed\))/, `${file}: ${signature} does not branch on refreshed data`);
    assert.ok(handler.includes(staleMessage), `${file}: ${signature} does not explain that the mutation succeeded but the view is stale`);
  }
});

test('collection loaders report refresh success and failure explicitly', async () => {
  const loaders = [
    ['Calendario.tsx', 'async function loadEvents('],
    ['Documentos.tsx', 'async function load('],
    ['Fornecedores.tsx', 'async function load('],
    ['Despesas.tsx', 'async function load('],
    ['AnaliseImovel.tsx', 'async function load('],
    ['Tarefas.tsx', 'async function load('],
    ['Oportunidades.tsx', 'async function load('],
  ];

  for (const [file, signature] of loaders) {
    const source = await readFile(new URL(`../src/pages/${file}`, import.meta.url), 'utf8');
    const loader = handlerFrom(source, signature);
    assert.match(loader, /return true;/, `${file}: loader does not report a successful refresh`);
    assert.match(loader, /return false;/, `${file}: loader does not report an unavailable refresh`);
  }
});

test('remaining project, import and wizard mutations preserve confirmed-write feedback', async () => {
  const cases = [
    ['Projetos.tsx', 'async function saveManualProject(', 'Projeto salvo, mas a lista não pôde ser recarregada'],
    ['Projetos.tsx', 'async function deleteProject(', 'Projeto excluído, mas a lista não pôde ser recarregada'],
    ['Projetos.tsx', 'async function addExpense(', 'Despesa salva, mas os dados do projeto não puderam ser recarregados'],
    ['Projetos.tsx', 'async function updateExpense(', 'Despesa atualizada, mas os dados do projeto não puderam ser recarregados'],
    ['Projetos.tsx', 'async function updateStageBudget(', 'Orçamento atualizado, mas o projeto não pôde ser recarregado'],
    ['Projetos.tsx', 'async function addStage(', 'Etapa criada, mas o projeto não pôde ser recarregado'],
    ['ImportarItbi.tsx', 'async function upload(', 'O histórico não pôde ser recarregado'],
    ['WizardFinanceiro.tsx', 'async function saveCurrent(', 'Etapa salva, mas a análise não pôde ser recarregada'],
  ];

  for (const [file, signature, message] of cases) {
    const source = await readFile(new URL(`../src/pages/${file}`, import.meta.url), 'utf8');
    const handler = handlerFrom(source, signature);
    assert.match(handler, /const (?:refreshed|projectsRefreshed) = await (?:load|loadEvents)\(/, `${file}: ${signature} does not inspect the primary refresh`);
    assert.ok(handler.includes(message), `${file}: ${signature} does not preserve confirmed-write feedback`);
  }
});
