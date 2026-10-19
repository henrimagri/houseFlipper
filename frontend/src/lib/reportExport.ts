export type ReportExportData = {
  generatedAt: string;
  totalInvested: number;
  salesRevenue: number;
  realizedProfit: number;
  realizedRoi: number;
  totalProjects: number;
  soldProperties: number;
};

export type LedgerExportEntry = {
  date: string;
  type: string;
  description: string;
  project: string | null;
  amount: number;
};

export type LedgerExportData = {
  generatedAt: string;
  dataInicio: string;
  dataFim: string;
  projeto: string;
  inflows: number;
  outflows: number;
  balance: number;
  entries: LedgerExportEntry[];
};

const TYPE_LABELS: Record<string, string> = {
  acquisition: 'Aquisição',
  expense: 'Despesa',
  sale: 'Venda',
};

function safeCell(value: unknown) {
  const text = String(value ?? '').replace(/\0/g, '');
  const safe = /^\s*[=+\-@]/.test(text) ? `'${text}` : text;

  return `"${safe.replace(/"/g, '""')}"`;
}

function moneyCell(value: number) {
  return Number(value || 0).toFixed(2).replace('.', ',');
}

export function ledgerTypeLabel(type: string) {
  return TYPE_LABELS[type] ?? type;
}

export function createSummaryReportCsv(data: ReportExportData) {
  const rows = [
    ['Indicador', 'Valor'],
    ['Gerado em', data.generatedAt],
    ['Total investido (R$)', moneyCell(data.totalInvested)],
    ['Faturamento de vendas (R$)', moneyCell(data.salesRevenue)],
    ['Lucro realizado (R$)', moneyCell(data.realizedProfit)],
    ['ROI realizado (%)', moneyCell(data.realizedRoi)],
    ['Projetos cadastrados', data.totalProjects],
    ['Imóveis vendidos', data.soldProperties],
  ];

  return `\uFEFF${rows.map(row => row.map(safeCell).join(';')).join('\r\n')}`;
}

export function createLedgerReportCsv(data: LedgerExportData) {
  const rows = [
    ['Campo', 'Valor'],
    ['Gerado em', data.generatedAt],
    ['Período inicial', data.dataInicio],
    ['Período final', data.dataFim],
    ['Projeto', data.projeto],
    ['Entradas (R$)', moneyCell(data.inflows)],
    ['Saídas (R$)', moneyCell(data.outflows)],
    ['Saldo (R$)', moneyCell(data.balance)],
    [],
    ['Data', 'Tipo', 'Descrição', 'Projeto', 'Valor (R$)'],
    ...data.entries.map(entry => [
      entry.date,
      ledgerTypeLabel(entry.type),
      entry.description,
      entry.project || 'Sem projeto',
      moneyCell(entry.amount),
    ]),
  ];

  return `\uFEFF${rows.map(row => row.map(safeCell).join(';')).join('\r\n')}`;
}
