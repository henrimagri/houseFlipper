type KpisLike = {
  total_invested?: unknown;
  sales_revenue?: unknown;
  realized_profit?: unknown;
  realized_roi?: unknown;
  active_projects?: unknown;
  total_projects?: unknown;
  sold_properties?: unknown;
};

const finite = (value: unknown) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : 0;
};

export function financialPosition(kpis: KpisLike) {
  return {
    totalInvested: finite(kpis.total_invested),
    salesRevenue: finite(kpis.sales_revenue),
    realizedProfit: finite(kpis.realized_profit),
    realizedRoi: finite(kpis.realized_roi),
    activeProjects: Math.max(0, Math.trunc(finite(kpis.active_projects))),
    totalProjects: Math.max(0, Math.trunc(finite(kpis.total_projects))),
    soldProperties: Math.max(0, Math.trunc(finite(kpis.sold_properties))),
  };
}

export function compositionShares(items: Array<{ name: string; valor: unknown; color: string }>) {
  const normalized = items.map(item => ({ ...item, valor: Math.max(0, finite(item.valor)) }));
  const total = normalized.reduce((sum, item) => sum + item.valor, 0);
  return normalized.map(item => ({ ...item, share: total > 0 ? item.valor / total * 100 : 0 }));
}
