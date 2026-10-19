import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile(new URL('../src/pages/Relatorios.tsx', import.meta.url), 'utf8');

test('reports page keeps the dashboard summary and adds a filtered ledger trail', () => {
  assert.match(source, /api\.get\('\/dashboard'\)/);
  assert.match(source, /\/reports\/ledger/);
  assert.match(source, /Exportar resumo/);
  assert.match(source, /Exportar trilha filtrada/);
  assert.match(source, /data_inicio: startDate/);
  assert.match(source, /projeto_id: Number\(projectId\)/);
  assert.match(source, /aria-label="Página anterior"/);
  assert.match(source, /aria-label="Próxima página"/);
  assert.match(source, /createLedgerReportCsv/);
  assert.match(source, /per_page: perPage/);
});
