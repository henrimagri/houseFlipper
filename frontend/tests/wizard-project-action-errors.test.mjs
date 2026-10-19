import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const wizard = await readFile(new URL('../src/pages/WizardFinanceiro.tsx', import.meta.url), 'utf8');
const projects = await readFile(new URL('../src/pages/Projetos.tsx', import.meta.url), 'utf8');

test('wizard financing refresh handles remote failure and restores pending state', () => {
  const start = wizard.indexOf('async function loadFinancingReference(');
  const end = wizard.indexOf('\n  useEffect(', start);
  const handler = wizard.slice(start, end);
  assert.match(handler, /setLoadingReference\(true\); setError\(''\)/);
  assert.match(handler, /catch\s*\(exception/);
  assert.match(handler, /Não foi possível recalcular o financiamento/);
  assert.match(handler, /finally\s*\{ setLoadingReference\(false\)/);
});

test('stage budget update reports failures and keeps a single request pending', () => {
  const start = projects.indexOf('async function updateStageBudget(');
  const end = projects.indexOf('\n  async function ', start + 1);
  const handler = projects.slice(start, end);
  assert.match(handler, /setSaving\(true\); setError\(''\)/);
  assert.match(handler, /catch\s*\(exception/);
  assert.match(handler, /Não foi possível atualizar o orçamento da etapa/);
  assert.match(handler, /return false/);
  assert.match(handler, /finally\s*\{ setSaving\(false\)/);

  const row = projects.slice(projects.indexOf('function StageRow('), projects.indexOf('function ExpenseWorkspace('));
  assert.match(row, /disabled=\{updating\}/);
  assert.match(row, /if \(updating \|\| Number\(draftBudget\) === Number\(etapa\.orcamento\)\) return/);
  assert.match(row, /if \(!saved\) setDraftBudget\(etapa\.orcamento\)/);
});

test('wizard ignores malformed or unavailable local note storage', () => {
  const readStart = wizard.indexOf('function readSavedNotes(');
  const readEnd = wizard.indexOf('\nfunction saveNotes(', readStart);
  const readHelper = wizard.slice(readStart, readEnd);
  const writeStart = readEnd + 1;
  const writeEnd = wizard.indexOf('\nexport default function', writeStart);
  const writeHelper = wizard.slice(writeStart, writeEnd);

  assert.match(readHelper, /try \{/);
  assert.match(readHelper, /JSON\.parse\(raw\)/);
  assert.match(readHelper, /Array\.isArray\(parsed\)/);
  assert.match(readHelper, /entries\.length > 100/);
  assert.match(readHelper, /typeof value !== 'string'/);
  assert.match(readHelper, /catch \{[\s\S]*return fallback/);
  assert.match(writeHelper, /try \{[\s\S]*localStorage\.setItem/);
  assert.match(writeHelper, /catch \{/);
  assert.match(wizard, /setNotes\(readSavedNotes\(/);
  assert.match(wizard, /saveNotes\(`investflip-wizard-notes-/);
});
