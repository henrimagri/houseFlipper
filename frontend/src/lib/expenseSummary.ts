export function normalizePaymentStatus(status?: string) {
  const normalized = String(status ?? '').toLowerCase();
  if (['pago', 'paid'].includes(normalized)) return 'pago';
  if (['atrasado', 'vencido', 'overdue'].includes(normalized)) return 'atrasado';
  if (['cancelado', 'cancelled', 'canceled'].includes(normalized)) return 'cancelado';
  return 'pendente';
}

export function summarizeExpenses(expenses: Array<{ valor: number | string; status_pagamento?: string }>) {
  const totals = { total: 0, paid: 0, pending: 0, overdue: 0 };
  for (const expense of expenses) {
    const status = normalizePaymentStatus(expense.status_pagamento);
    if (status === 'cancelado') continue;
    const amount = Math.round(Number(expense.valor || 0) * 100);
    totals.total += amount;
    if (status === 'pago') totals.paid += amount;
    else if (status === 'atrasado') totals.overdue += amount;
    else totals.pending += amount;
  }
  return { total: totals.total / 100, paid: totals.paid / 100, pending: totals.pending / 100, overdue: totals.overdue / 100 };
}
