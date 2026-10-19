import test from 'node:test';
import assert from 'node:assert/strict';
import { periodicReturns } from '../src/lib/financialReturns.ts';

test('periodic returns use compound equivalence instead of dividing total ROI by months', () => {
  const result = periodicReturns(100, 12);
  assert.ok(Math.abs(result.monthly - 5.9463094359) < 1e-9);
  assert.ok(Math.abs(result.annualized - 100) < 1e-9);
});

test('periodic returns match six-month annualization and handle total loss safely', () => {
  const sixMonths = periodicReturns(21, 6);
  assert.ok(Math.abs(sixMonths.monthly - 3.2280115456) < 1e-9);
  assert.ok(Math.abs(sixMonths.annualized - 46.41) < 1e-9);
  assert.deepEqual(periodicReturns(-100, 8), { monthly: -100, annualized: -100 });
  assert.deepEqual(periodicReturns('invalid', 8), { monthly: 0, annualized: 0 });
});
