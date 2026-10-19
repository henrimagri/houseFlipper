import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile(new URL('../src/pages/CentralFinanceira.tsx', import.meta.url), 'utf8');

test('financing reference and selection actions handle failures and pending state', () => {
  for (const signature of ['reloadFinancingReference', 'selectFinancingSimulation']) {
    const start = source.indexOf(`async function ${signature}(`);
    const next = source.indexOf('\n  async function ', start + 1);
    const handler = source.slice(start, next);
    assert.match(handler, /setSaving\(true\)/);
    assert.match(handler, /catch\s*\(/);
    assert.match(handler, /setError\(validationMessage\(exception\)\)/);
    assert.match(handler, /finally\s*\{\s*setSaving\(false\)/);
  }
});

test('financing action controls are disabled while a request is pending', () => {
  assert.match(source, /FinancingComparator simulations=.*pending=\{saving\}/);
  assert.match(source, /disabled=\{saving\} onClick=\{reloadFinancingReference\}/);
  const comparator = source.slice(source.indexOf('function FinancingComparator'), source.indexOf('function CostsTable'));
  assert.match(comparator, /disabled=\{pending\}/);
});

test('score guide remains usable when browser storage is blocked', () => {
  const readStart = source.indexOf('function shouldShowScoreGuide(');
  const writeStart = source.indexOf('function rememberScoreGuideDismissal(');
  const componentStart = source.indexOf('export default function', writeStart);
  const readHelper = source.slice(readStart, writeStart);
  const writeHelper = source.slice(writeStart, componentStart);

  assert.match(readHelper, /try \{[\s\S]*localStorage\.getItem\(scoreGuideStorageKey\)/);
  assert.match(readHelper, /catch \{[\s\S]*return true/);
  assert.match(writeHelper, /try \{[\s\S]*localStorage\.setItem\(scoreGuideStorageKey, '1'\)/);
  assert.match(writeHelper, /catch \{/);
  assert.match(source, /useState\(shouldShowScoreGuide\)/);
  assert.match(source, /rememberScoreGuideDismissal\(\); setShowScoreGuide\(false\)/);
});

test('financial create actions prevent duplicate submissions and restore pending state', () => {
  for (const signature of ['addFinancingSimulation', 'addCost', 'saveResult']) {
    const start = source.indexOf(`async function ${signature}(`);
    const next = source.indexOf('\n  async function ', start + 1);
    const handler = source.slice(start, next);
    assert.match(handler, /setSaving\(true\)/, `${signature} must enter pending state`);
    assert.match(handler, /catch\s*\(/, `${signature} must handle rejection`);
    assert.match(handler, /finally\s*\{ setSaving\(false\)/, `${signature} must restore pending state`);
  }

  assert.match(source, /CostEntryForm cost=.*pending=\{saving\}/);
  assert.match(source, /CostsTable costs=.*pending=\{saving\}/);
  assert.match(source, /disabled=\{saving\} className="mt-5 bg-primary-600[^>]+>\{saving \? 'Salvando\.\.\.'/);
});

test('financial mutations distinguish confirmed writes from a failed follow-up refresh', () => {
  const loadStart = source.indexOf('async function load()');
  const loadEnd = source.indexOf('\n  useEffect(', loadStart);
  const loader = source.slice(loadStart, loadEnd);
  assert.match(loader, /return true/);
  assert.match(loader, /catch\s*\(exception[^}]+return false/);

  for (const signature of ['saveFinancing', 'addFinancingSimulation', 'deleteFinancingSimulation', 'addCost', 'deleteCost', 'saveResult']) {
    const start = source.indexOf(`async function ${signature}(`);
    const next = source.indexOf('\n  async function ', start + 1);
    const handler = source.slice(start, next);
    assert.match(handler, /const refreshed = await load\(\)/, `${signature} must inspect refresh outcome`);
    assert.match(handler, /análise não pôde ser recarregada/, `${signature} must explain that the mutation succeeded while the view is stale`);
  }
});
