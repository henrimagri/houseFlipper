import test from 'node:test';
import assert from 'node:assert/strict';
import { compositionShares, financialPosition } from '../src/lib/financeView.ts';

test('financial position preserves valid negative profit and normalizes invalid counts', () => {
  assert.deepEqual(financialPosition({ total_invested: '100.50', sales_revenue: 80, realized_profit: -20.5, realized_roi: '-20.39', active_projects: -1, total_projects: '3', sold_properties: 'invalid' }), {
    totalInvested: 100.5, salesRevenue: 80, realizedProfit: -20.5, realizedRoi: -20.39, activeProjects: 0, totalProjects: 3, soldProperties: 0,
  });
});

test('composition uses only finite non-negative values and avoids division by zero', () => {
  assert.deepEqual(compositionShares([{ name: 'A', valor: 75, color: '#1' }, { name: 'B', valor: 25, color: '#2' }]).map(item => item.share), [75, 25]);
  assert.deepEqual(compositionShares([{ name: 'A', valor: -1, color: '#1' }, { name: 'B', valor: 'bad', color: '#2' }]).map(item => item.share), [0, 0]);
});
