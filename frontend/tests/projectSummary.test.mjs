import test from 'node:test';
import assert from 'node:assert/strict';
import { summarizeProjects } from '../src/lib/projectSummary.ts';

test('project totals are complete only when every requested summary is present', () => {
  assert.deepEqual(summarizeProjects([1, 2], {
    1: { orcamento_total: '100.10', custo_real: 20, consumido_pct: 20, roi: { lucro: 5 } },
    2: { orcamento_total: 200.20, custo_real: '30.30', consumido_pct: 40, roi: { lucro: '10' } },
  }), { budget: 300.3, spent: 50.3, profit: 15, progress: 30, complete: true, missing: 0 });
});

test('project totals expose missing summaries instead of presenting a partial total as final', () => {
  assert.deepEqual(summarizeProjects([1, 2, 3], {
    1: { orcamento_total: 100, custo_real: 20, consumido_pct: 20 },
    3: { orcamento_total: 50, custo_real: 5, consumido_pct: 10 },
  }), { budget: 150, spent: 25, profit: 0, progress: 15, complete: false, missing: 1 });
});
