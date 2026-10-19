import test from 'node:test';
import assert from 'node:assert/strict';
import { manualProjectPayload } from '../src/lib/projectForm.ts';

test('manual project payload normalizes identifiers and omits blank optional fields', () => {
  assert.deepEqual(manualProjectPayload({ propriedade_id: '12', nome: '  Reforma QA  ', descricao: ' ', orcamento_total: 0, data_inicio: '', data_termino_prevista: '', status: 'planejamento' }), {
    propriedade_id: 12, nome: 'Reforma QA', orcamento_total: 0, status: 'planejamento',
  });
});

test('manual project payload preserves dates, description and cents', () => {
  assert.deepEqual(manualProjectPayload({ propriedade_id: '3', nome: 'Projeto', descricao: '  Teste fictício  ', orcamento_total: 123.47, data_inicio: '2026-10-16', data_termino_prevista: '2026-12-16', status: 'em_andamento' }), {
    propriedade_id: 3, nome: 'Projeto', descricao: 'Teste fictício', orcamento_total: 123.47, data_inicio: '2026-10-16', data_termino_prevista: '2026-12-16', status: 'em_andamento',
  });
});
