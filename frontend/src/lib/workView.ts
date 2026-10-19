export type WorkProject = {
  id: number;
  nome: string;
  status?: string | null;
  data_inicio?: string | null;
  data_termino_prevista?: string | null;
  data_termino_real?: string | null;
  propriedade?: { id: number; apelido: string } | null;
};

export type WorkSummary = {
  projeto_id: number;
  orcamento_total: number;
  custo_real: number;
  economia: number;
  consumido_pct: number;
  progresso_pct: number;
  etapas: Array<{ id: number; nome: string; orcamento: number; custo_real: number; economia: number; status: string; progresso_pct: number }>;
  roi: { valor_aquisicao: number; total_despesas: number; custo_total: number; valor_liquido_venda: number; lucro: number; roi_pct: number; tem_venda: boolean };
};

export type WorkStatus = 'planning' | 'in_progress' | 'delayed' | 'paused' | 'completed' | 'cancelled';
export type WorkFilter = 'all' | 'planning' | 'in_progress' | 'delayed' | 'completed';

function normalizedStatus(value?: string | null) {
  return String(value || '').trim().toLocaleLowerCase('pt-BR').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[\s-]+/g, '_');
}

export function deriveWorkStatus(project: WorkProject, todayIso: string): WorkStatus {
  const status = normalizedStatus(project.status);
  if (['concluido', 'concluida', 'finalizado', 'finalizada'].includes(status) || project.data_termino_real) return 'completed';
  if (['cancelado', 'cancelada'].includes(status)) return 'cancelled';
  if (project.data_termino_prevista && project.data_termino_prevista < todayIso) return 'delayed';
  if (['em_andamento', 'andamento', 'ativo', 'ativa'].includes(status)) return 'in_progress';
  if (['pausado', 'pausada'].includes(status)) return 'paused';
  return 'planning';
}

export function matchesWorkFilter(project: WorkProject, filter: WorkFilter, search: string, todayIso: string) {
  if (filter !== 'all' && deriveWorkStatus(project, todayIso) !== filter) return false;
  const needle = search.trim().toLocaleLowerCase('pt-BR');
  if (!needle) return true;
  return `${project.nome} ${project.propriedade?.apelido || ''}`.toLocaleLowerCase('pt-BR').includes(needle);
}

export function summarizeWorks(projects: WorkProject[], summaries: Record<number, WorkSummary>, todayIso: string) {
  const available = projects.map(project => summaries[project.id]).filter((summary): summary is WorkSummary => Boolean(summary));
  const sum = (field: 'orcamento_total' | 'custo_real') => available.reduce((cents, item) => cents + Math.round(Number(item[field] || 0) * 100), 0) / 100;
  return {
    total: projects.length,
    inProgress: projects.filter(project => deriveWorkStatus(project, todayIso) === 'in_progress').length,
    delayed: projects.filter(project => deriveWorkStatus(project, todayIso) === 'delayed').length,
    completed: projects.filter(project => deriveWorkStatus(project, todayIso) === 'completed').length,
    budget: sum('orcamento_total'),
    spent: sum('custo_real'),
    financialComplete: available.length === projects.length,
    missingSummaries: projects.length - available.length,
  };
}
