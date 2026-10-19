import test from 'node:test';
import assert from 'node:assert/strict';
import { createLedgerReportCsv, createSummaryReportCsv } from '../src/lib/reportExport.ts';

test('summary report export preserves cents and the real indicators', () => {
  const csv = createSummaryReportCsv({ generatedAt: '16/10/2026 12:00', totalInvested: 100.25, salesRevenue: 200.5, realizedProfit: 30.75, realizedRoi: 14.125, totalProjects: 2, soldProperties: 1 });
  assert.ok(csv.startsWith('\uFEFF'));
  assert.match(csv, /"Total investido \(R\$\)";"100,25"/);
  assert.match(csv, /"ROI realizado \(%\)";"14,13"/);
  assert.match(csv, /"Projetos cadastrados";"2"/);
});

test('summary report export neutralizes a formula-like generated label', () => {
  const csv = createSummaryReportCsv({ generatedAt: '=NOW()', totalInvested: 0, salesRevenue: 0, realizedProfit: 0, realizedRoi: 0, totalProjects: 0, soldProperties: 0 });
  assert.match(csv, /"'=NOW\(\)"/);
});

test('ledger export uses the filtered totals and every returned entry', () => {
  const csv = createLedgerReportCsv({
    generatedAt: '19/10/2026 06:40',
    dataInicio: '2026-02-01',
    dataFim: '2026-03-31',
    projeto: 'Reforma Sé',
    inflows: 120000.5,
    outflows: 2000.25,
    balance: 118000.25,
    entries: [
      { date: '2026-03-10', type: 'sale', description: 'Venda: Casa Sé', project: 'Reforma Sé', amount: 120000.5 },
      { date: '2026-02-01', type: 'expense', description: 'Piso; acabamento', project: 'Reforma Sé', amount: -2000.25 },
    ],
  });
  assert.ok(csv.startsWith('\uFEFF'));
  assert.match(csv, /"Período inicial";"2026-02-01"/);
  assert.match(csv, /"Projeto";"Reforma Sé"/);
  assert.match(csv, /"Entradas \(R\$\)";"120000,50"/);
  assert.match(csv, /"Piso; acabamento"/);
  assert.match(csv, /"Venda";"Venda: Casa Sé";"Reforma Sé";"120000,50"/);
});

test('ledger export neutralizes formula-like cells in the filtered trail', () => {
  const csv = createLedgerReportCsv({
    generatedAt: '=NOW()',
    dataInicio: '+1',
    dataFim: '@end',
    projeto: '=CMD',
    inflows: 0,
    outflows: 1,
    balance: -1,
    entries: [{ date: '2026-10-19', type: 'expense', description: '=HYPERLINK("https://invalid.test")', project: null, amount: -1 }],
  });
  assert.match(csv, /"'=NOW\(\)"/);
  assert.match(csv, /"'\+1"/);
  assert.match(csv, /"'@end"/);
  assert.match(csv, /"'=CMD"/);
  assert.match(csv, /"'=HYPERLINK\(""https:\/\/invalid\.test""\)"/);
  assert.match(csv, /"Sem projeto"/);
});
