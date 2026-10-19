import test from 'node:test';
import assert from 'node:assert/strict';
import { emptyTaskForm, isTaskOverdue, taskPayload } from '../src/lib/taskView.ts';

test('task payload normalizes identifiers, optional values and zero cost', () => {
  const payload = taskPayload({ ...emptyTaskForm, projeto_id: '7', etapa_id: '9', responsavel_user_id: '3', titulo: '  Pintura  ', custo_estimado: '0', custo_real: '10.25' });
  assert.deepEqual(payload, { projeto_id: 7, etapa_id: 9, responsavel_user_id: 3, titulo: 'Pintura', descricao: null, data_prevista: null, data_conclusao: null, prioridade: 'media', status: 'pendente', custo_estimado: 0, custo_real: 10.25 });
});

test('completion date is sent only for completed tasks', () => {
  assert.equal(taskPayload({ ...emptyTaskForm, projeto_id: '1', titulo: 'A', status: 'concluida', data_conclusao: '2026-10-16' }).data_conclusao, '2026-10-16');
  assert.equal(taskPayload({ ...emptyTaskForm, projeto_id: '1', titulo: 'A', status: 'em_andamento', data_conclusao: '2026-10-16' }).data_conclusao, null);
});

test('overdue excludes completed and cancelled tasks', () => {
  assert.equal(isTaskOverdue({ status: 'pendente', data_prevista: '2026-10-15' }, '2026-10-16'), true);
  assert.equal(isTaskOverdue({ status: 'concluida', data_prevista: '2026-10-15' }, '2026-10-16'), false);
  assert.equal(isTaskOverdue({ status: 'cancelada', data_prevista: '2026-10-15' }, '2026-10-16'), false);
  assert.equal(isTaskOverdue({ status: 'pendente', data_prevista: '2026-10-16' }, '2026-10-16'), false);
});
