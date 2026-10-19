export type TaskForm = {
  projeto_id: string;
  etapa_id: string;
  responsavel_user_id: string;
  titulo: string;
  descricao: string;
  data_prevista: string;
  data_conclusao: string;
  prioridade: 'baixa' | 'media' | 'alta' | 'critica';
  status: 'pendente' | 'em_andamento' | 'concluida' | 'cancelada';
  custo_estimado: string;
  custo_real: string;
};

export const emptyTaskForm: TaskForm = {
  projeto_id: '', etapa_id: '', responsavel_user_id: '', titulo: '', descricao: '', data_prevista: '',
  data_conclusao: '', prioridade: 'media', status: 'pendente', custo_estimado: '', custo_real: '',
};

const numberOrNull = (value: string) => value.trim() === '' ? null : Number(value);
const textOrNull = (value: string) => value.trim() || null;

export function taskPayload(form: TaskForm) {
  return {
    projeto_id: Number(form.projeto_id),
    etapa_id: form.etapa_id ? Number(form.etapa_id) : null,
    responsavel_user_id: form.responsavel_user_id ? Number(form.responsavel_user_id) : null,
    titulo: form.titulo.trim(),
    descricao: textOrNull(form.descricao),
    data_prevista: textOrNull(form.data_prevista),
    data_conclusao: form.status === 'concluida' ? textOrNull(form.data_conclusao) : null,
    prioridade: form.prioridade,
    status: form.status,
    custo_estimado: numberOrNull(form.custo_estimado),
    custo_real: numberOrNull(form.custo_real),
  };
}

export function isTaskOverdue(task: { status: string; data_prevista?: string | null }, todayIso: string) {
  return Boolean(task.data_prevista && task.data_prevista < todayIso && !['concluida', 'cancelada'].includes(task.status));
}
