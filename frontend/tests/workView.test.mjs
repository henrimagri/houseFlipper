import test from 'node:test';
import assert from 'node:assert/strict';
import { deriveWorkStatus, matchesWorkFilter, summarizeWorks } from '../src/lib/workView.ts';

const today = '2026-10-16';

test('completed and cancelled projects never become delayed', () => {
  assert.equal(deriveWorkStatus({ id: 1, nome: 'A', status: 'concluido', data_termino_prevista: '2020-01-01' }, today), 'completed');
  assert.equal(deriveWorkStatus({ id: 2, nome: 'B', status: 'cancelado', data_termino_prevista: '2020-01-01' }, today), 'cancelled');
  assert.equal(deriveWorkStatus({ id: 3, nome: 'C', status: 'em_andamento', data_termino_prevista: '2020-01-01' }, today), 'delayed');
});

test('filters use persisted project and property names', () => {
  const project = { id: 1, nome: 'Reforma Norte', status: 'planejamento', propriedade: { id: 9, apelido: 'Casa Azul' } };
  assert.equal(matchesWorkFilter(project, 'planning', 'casa azul', today), true);
  assert.equal(matchesWorkFilter(project, 'in_progress', '', today), false);
  assert.equal(matchesWorkFilter(project, 'all', 'fictício', today), false);
});

test('financial totals are marked incomplete when any project summary is missing', () => {
  const projects = [{ id: 1, nome: 'A', status: 'em_andamento' }, { id: 2, nome: 'B', status: 'planejamento' }];
  const summaries = { 1: { projeto_id: 1, orcamento_total: 100.1, custo_real: 20.05, economia: 80.05, consumido_pct: 20, progresso_pct: 50, etapas: [], roi: { valor_aquisicao: 0, total_despesas: 0, custo_total: 0, valor_liquido_venda: 0, lucro: 0, roi_pct: 0, tem_venda: false } } };
  assert.deepEqual(summarizeWorks(projects, summaries, today), { total: 2, inProgress: 1, delayed: 0, completed: 0, budget: 100.1, spent: 20.05, financialComplete: false, missingSummaries: 1 });
});
