import test from 'node:test';
import assert from 'node:assert/strict';
import { createExpenseCsv } from '../src/lib/expenseExport.ts';

test('expense export keeps real fields, decimal cents and UTF-8 marker', () => {
  const csv = createExpenseCsv([{
    data: '2026-10-16', descricao: 'Tinta; acabamento', categoria: 'Material', projeto: 'Casa Sé',
    formaPagamento: 'PIX', valor: '10.5', status: 'Pago',
  }]);
  assert.ok(csv.startsWith('\uFEFF'));
  assert.match(csv, /"Tinta; acabamento"/);
  assert.match(csv, /"10,50"/);
  assert.match(csv, /"Casa Sé"/);
});

test('expense export neutralizes spreadsheet formulas and escapes quotes', () => {
  const csv = createExpenseCsv([{
    data: '2026-10-16', descricao: '=HYPERLINK("https://invalid.test")', categoria: '+cmd', projeto: '@tenant',
    formaPagamento: '-1', valor: 1, status: 'Pendente',
  }]);
  assert.match(csv, /"'=HYPERLINK\(""https:\/\/invalid\.test""\)"/);
  assert.match(csv, /"'\+cmd"/);
  assert.match(csv, /"'@tenant"/);
  assert.match(csv, /"'-1"/);
});
