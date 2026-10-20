import { AlertCircle, CheckCircle2, Database, FileSpreadsheet, Loader2, UploadCloud } from 'lucide-react';
import * as React from 'react';
import api from '@/lib/api';

type Batch = {
  id: number;
  source_filename: string;
  status: string;
  total_rows: number;
  imported_rows: number;
  updated_rows: number;
  skipped_rows: number;
  failed_rows: number;
  error_message?: string | null;
};

type Transaction = {
  id: number;
  property_sql?: string | null;
  transaction_date?: string | null;
  declared_transaction_value?: number | null;
  tax_base_value?: number | null;
  transaction_nature?: string | null;
  street_name?: string | null;
  street_number?: string | null;
  address_complement?: string | null;
  address_reference?: string | null;
  neighborhood?: string | null;
  zip_code?: string | null;
  registry_office?: string | null;
  financing_type?: string | null;
  calculation_area_m2?: number | null;
  price_m2?: number | null;
  normalized_address?: string | null;
};

type ImportResponse = {
  can_import: boolean;
  summary: {
    batches: number;
    transactions: number;
    latest_batch?: Batch | null;
  };
  transactions: {
    data: Transaction[];
    current_page: number;
    last_page: number;
    total: number;
  };
};

const money = (value?: number | null) => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

function apiErrorMessage(error: any, fallback: string): string {
  const status = error?.response?.status;
  const data = error?.response?.data;

  if (data?.errors) {
    const validationError = Object.values(data.errors).flat().join(' ');
    if (validationError) {
      return validationError;
    }
  }

  if (data?.message) {
    return data.message;
  }

  if (typeof data === 'string' && data.trim()) {
    const plainText = data
      .replace(/<style[\s\S]*?<\/style>/gi, ' ')
      .replace(/<script[\s\S]*?<\/script>/gi, ' ')
      .replace(/<[^>]+>/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
      .slice(0, 260);

    if (plainText) {
      return `Erro ${status || ''}: ${plainText}`;
    }
  }

  if (status) {
    return `${fallback} Código HTTP: ${status}. Abra o DevTools > Network para ver a resposta da rota /api/itbi-imports/upload.`;
  }

  return error?.message ? `${fallback} Detalhe: ${error.message}` : fallback;
}

export default function ImportarItbi() {
  const [file, setFile] = React.useState<File | null>(null);
  const [loading, setLoading] = React.useState(false);
  const [uploading, setUploading] = React.useState(false);
  const [message, setMessage] = React.useState<{ type: 'success' | 'error' | 'warning'; text: string } | null>(null);
  const [data, setData] = React.useState<ImportResponse | null>(null);
  const fileInput = React.useRef<HTMLInputElement>(null);

  const load = React.useCallback(async () => {
    setLoading(true);
    try {
      const response = await api.get('/itbi-imports');
      setData(response.data);
      return true;
    } catch (error: any) {
      const text = error.response?.data?.message || 'Não foi possível carregar os dados de ITBI. Verifique se as migrations foram executadas.';
      setMessage({ type: 'error', text });
      return false;
    } finally {
      setLoading(false);
    }
  }, []);

  React.useEffect(() => {
    load();
  }, [load]);

  async function upload(event: React.FormEvent) {
    event.preventDefault();
    if (!file) {
      setMessage({ type: 'error', text: 'Selecione um arquivo ODS, CSV ou XLSX.' });
      return;
    }

    const form = new FormData();
    form.append('file', file);
    setUploading(true);
    setMessage(null);

    try {
      const response = await api.post('/itbi-imports/upload', form, { headers: { 'Content-Type': 'multipart/form-data' } });
      const resultType = response.data.batch?.status === 'completed_with_errors' ? 'warning' : 'success';
      const resultText = response.data.message || 'Arquivo importado com sucesso.';
      setFile(null);
      if (fileInput.current) fileInput.current.value = '';
      const refreshed = await load();
      setMessage(refreshed
        ? { type: resultType, text: resultText }
        : { type: 'warning', text: `${resultText} O histórico não pôde ser recarregado; atualize a página antes de importar novamente.` });
    } catch (error: any) {
      const text = apiErrorMessage(error, 'Falha ao importar arquivo.');
      setMessage({ type: 'error', text });
    } finally {
      setUploading(false);
    }
  }

  const latest = data?.summary.latest_batch;
  const transactions = data?.transactions.data ?? [];

  return <div className="space-y-6">
    <div className="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
      <div>
        <div className="text-primary-600 dark:text-primary-400 font-black tracking-widest uppercase text-sm">Dados reais de mercado</div>
        <h1 className="text-3xl font-black mt-2">Importar ITBI</h1>
        <p className="text-gray-500 dark:text-slate-400 mt-1">Envie a planilha pública da Prefeitura para alimentar vendas reais, preço/m² e futuramente o AVM.</p>
      </div>
    </div>

    <section className="grid xl:grid-cols-[420px_1fr] gap-5">
      <form onSubmit={upload} className="bg-white dark:bg-slate-900 border rounded-3xl p-5 shadow-xs space-y-4">
        <div className="flex items-center gap-3">
          <div className="w-12 h-12 rounded-2xl bg-violet-50 dark:bg-violet-950 text-primary-600 dark:text-primary-400 grid place-items-center"><UploadCloud className="w-6 h-6" /></div>
          <div>
            <h2 className="font-black text-lg">Upload da planilha</h2>
            <p className="text-sm text-gray-500 dark:text-slate-400">Formatos aceitos: ODS, CSV e XLSX.</p>
          </div>
        </div>

        {!data?.can_import && <p role="status" className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">Você pode selecionar e conferir o arquivo, mas o envio da base compartilhada exige permissão de operador.</p>}
        <label className="block border-2 border-dashed rounded-2xl p-6 text-center cursor-pointer hover:border-primary-300 hover:bg-violet-50/40 transition focus-within:border-primary-500 focus-within:ring-2 focus-within:ring-primary-500/20">
          <FileSpreadsheet className="w-10 h-10 mx-auto text-primary-600 dark:text-primary-400" />
          <div className="font-bold mt-3">{file ? file.name : 'Clique para selecionar o arquivo'}</div>
          <div className="text-xs text-gray-500 dark:text-slate-400 mt-1">Até 250 MB. O arquivo bruto fica guardado para auditoria.</div>
          <input
            ref={fileInput}
            type="file"
            disabled={uploading}
            accept=".ods,.csv,.txt,.xlsx"
            className="sr-only"
            onChange={(event) => { setFile(event.target.files?.[0] ?? null); setMessage(null); }}
          />
        </label>

        <button disabled={uploading || !data?.can_import || !file} className="w-full bg-primary-600 text-white rounded-xl px-5 py-3 font-black disabled:cursor-not-allowed disabled:opacity-60 inline-flex justify-center items-center gap-2">
          {uploading ? <Loader2 className="w-5 h-5 animate-spin" /> : <UploadCloud className="w-5 h-5" />}
          {uploading ? 'Importando...' : !file ? 'Selecione um arquivo' : !data?.can_import ? 'Permissão de operador necessária' : 'Importar arquivo'}
        </button>

        {message && <div role="status" className={`rounded-2xl p-4 text-sm flex gap-3 ${message.type === 'success' ? 'bg-emerald-50 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border border-emerald-100' : message.type === 'warning' ? 'bg-amber-50 dark:bg-amber-950 text-amber-900 dark:text-amber-200 border border-amber-200' : 'bg-red-50 dark:bg-red-950 text-red-700 dark:text-red-300 border border-red-100'}`}>
          {message.type === 'success' ? <CheckCircle2 className="w-5 h-5 shrink-0" /> : <AlertCircle className="w-5 h-5 shrink-0" />}
          <span>{message.text}</span>
        </div>}
      </form>

      <div className="grid md:grid-cols-3 gap-4">
        <Kpi title="Lotes importados" value={data?.summary.batches ?? 0} icon={Database} detail="arquivos enviados ou descobertos" />
        <Kpi title="Transações reais" value={data?.summary.transactions ?? 0} icon={FileSpreadsheet} detail="registros normalizados na base" />
        <Kpi title="Último lote" value={latest?.status || '—'} icon={CheckCircle2} detail={latest ? `${latest.source_filename} · ${latest.total_rows} linhas · ${latest.imported_rows} novas · ${latest.updated_rows} atualizadas · ${latest.skipped_rows} ignoradas · ${latest.failed_rows} falhas` : 'nenhum arquivo importado'} />
      </div>
    </section>

    <section className="bg-white dark:bg-slate-900 border rounded-3xl shadow-xs overflow-hidden">
      <div className="p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        <div>
          <h2 className="text-xl font-black">Transações importadas</h2>
          <p className="text-sm text-gray-500 dark:text-slate-400">Dados normalizados da planilha. Campos não mapeados ficam preservados no payload bruto.</p>
        </div>
        {loading && <span className="text-sm text-gray-500 dark:text-slate-400 inline-flex items-center gap-2"><Loader2 className="w-4 h-4 animate-spin" />Carregando...</span>}
      </div>

      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-slate-50 dark:bg-slate-950 text-xs text-gray-500 dark:text-slate-400 uppercase">
            <tr>
              <th className="text-left p-4">Cadastro</th>
              <th className="text-left p-4 min-w-[260px]">Endereço</th>
              <th className="text-left p-4">Bairro</th>
              <th className="text-left p-4">Data</th>
              <th className="text-left p-4">Valor transação</th>
              <th className="text-left p-4">Base cálculo</th>
              <th className="text-left p-4">Área</th>
              <th className="text-left p-4">R$/m²</th>
              <th className="text-left p-4">Cartório</th>
              <th className="text-left p-4">Natureza</th>
            </tr>
          </thead>
          <tbody className="divide-y">
            {transactions.map((item) => <tr key={item.id} className="hover:bg-slate-50 dark:hover:bg-slate-800">
              <td className="p-4 font-bold">{item.property_sql || '—'}</td>
              <td className="p-4">
                <div className="font-bold">{[item.street_name, item.street_number].filter(Boolean).join(', ') || item.normalized_address || '—'}</div>
                <div className="text-xs text-gray-500 dark:text-slate-400">{[item.address_complement, item.address_reference, item.zip_code].filter(Boolean).join(' · ')}</div>
              </td>
              <td className="p-4">{item.neighborhood || '—'}</td>
              <td className="p-4">{item.transaction_date ? new Date(`${item.transaction_date}T00:00:00`).toLocaleDateString('pt-BR') : '—'}</td>
              <td className="p-4 font-bold">{money(item.declared_transaction_value)}</td>
              <td className="p-4">{money(item.tax_base_value)}</td>
              <td className="p-4">{item.calculation_area_m2 ? `${item.calculation_area_m2.toLocaleString('pt-BR')} m²` : '—'}</td>
              <td className="p-4 font-bold">{item.price_m2 ? `${money(item.price_m2)}/m²` : '—'}</td>
              <td className="p-4">{item.registry_office || '—'}</td>
              <td className="p-4">{item.transaction_nature || '—'}</td>
            </tr>)}
            {!transactions.length && <tr><td colSpan={10} className="p-10 text-center text-gray-500 dark:text-slate-400">Nenhuma transação importada ainda.</td></tr>}
          </tbody>
        </table>
      </div>
    </section>
  </div>;
}

function Kpi({ title, value, detail, icon: Icon }: { title: string; value: React.ReactNode; detail: string; icon: any }) {
  return <div className="bg-white dark:bg-slate-900 border rounded-3xl p-5 shadow-xs">
    <div className="flex items-center gap-4">
      <div className="w-12 h-12 rounded-2xl bg-violet-50 dark:bg-violet-950 text-primary-600 dark:text-primary-400 grid place-items-center"><Icon className="w-6 h-6" /></div>
      <div>
        <div className="text-sm text-gray-500 dark:text-slate-400">{title}</div>
        <div className="text-2xl font-black mt-1">{value}</div>
        <div className="text-xs text-gray-400 mt-1">{detail}</div>
      </div>
    </div>
  </div>;
}
