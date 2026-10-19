export type ExpenseExportRow = {
  data: string;
  descricao: string;
  categoria: string;
  projeto: string;
  formaPagamento: string;
  valor: number | string;
  status: string;
};

function spreadsheetSafe(value: unknown) {
  const text = String(value ?? '').replace(/\0/g, '');
  return /^\s*[=+\-@]/.test(text) ? `'${text}` : text;
}

function csvCell(value: unknown) {
  return `"${spreadsheetSafe(value).replace(/"/g, '""')}"`;
}

export function createExpenseCsv(rows: ExpenseExportRow[]) {
  const header = ['Data', 'Descrição', 'Categoria', 'Projeto', 'Forma de pagamento', 'Valor (R$)', 'Status'];
  const lines = rows.map(row => [
    row.data,
    row.descricao,
    row.categoria,
    row.projeto,
    row.formaPagamento,
    Number(row.valor || 0).toFixed(2).replace('.', ','),
    row.status,
  ]);

  return `\uFEFF${[header, ...lines].map(line => line.map(csvCell).join(';')).join('\r\n')}`;
}
