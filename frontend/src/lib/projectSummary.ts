export type ProjectSummaryTotals = {
  budget: number;
  spent: number;
  profit: number;
  progress: number;
  complete: boolean;
  missing: number;
};

type SummaryLike = {
  orcamento_total?: unknown;
  custo_real?: unknown;
  consumido_pct?: unknown;
  roi?: { lucro?: unknown };
};

export function summarizeProjects(projectIds: number[], summaries: Record<number, SummaryLike>): ProjectSummaryTotals {
  const available = projectIds.map(id => summaries[id]).filter((summary): summary is SummaryLike => Boolean(summary));
  const missing = projectIds.length - available.length;
  const sumMoney = (selector: (summary: SummaryLike) => unknown) => available.reduce((cents, item) => cents + Math.round(Number(selector(item) || 0) * 100), 0) / 100;

  return {
    budget: sumMoney(item => item.orcamento_total),
    spent: sumMoney(item => item.custo_real),
    profit: sumMoney(item => item.roi?.lucro),
    progress: available.length
      ? available.reduce((sum, item) => sum + Number(item.consumido_pct || 0), 0) / available.length
      : 0,
    complete: missing === 0,
    missing,
  };
}
