import test from 'node:test';
import assert from 'node:assert/strict';
import { normalizePaymentStatus, summarizeExpenses } from '../src/lib/expenseSummary.ts';

test('cancelled expenses remain cancelled and do not inflate payable or spent totals', () => {
  assert.equal(normalizePaymentStatus('cancelado'), 'cancelado');
  assert.deepEqual(summarizeExpenses([
    { valor: '10.10', status_pagamento: 'pago' },
    { valor: '20.20', status_pagamento: 'pendente' },
    { valor: '30.30', status_pagamento: 'atrasado' },
    { valor: '999.99', status_pagamento: 'cancelado' },
  ]), { total: 60.6, paid: 10.1, pending: 20.2, overdue: 30.3 });
});

test('money totals use cents and preserve supported historical status aliases', () => {
  assert.deepEqual(summarizeExpenses([
    { valor: 0.1, status_pagamento: 'paid' },
    { valor: 0.2, status_pagamento: 'pago' },
    { valor: 1, status_pagamento: 'overdue' },
    { valor: 100, status_pagamento: 'cancelled' },
  ]), { total: 1.3, paid: 0.3, pending: 0, overdue: 1 });
});
