import { FormEvent, useEffect, useState } from 'react';
import { ExternalLink, FileUp, Landmark, RefreshCw, Search, SlidersHorizontal } from 'lucide-react';
import api from '@/lib/api';
import PageHeader from '@/ui/PageHeader';
import EmptyState from '@/ui/EmptyState';
import ErrorState from '@/ui/ErrorState';

type Listing = { id:number; provider:string; source_id:string; title:string; property_type?:string; city?:string; state?:string; neighborhood?:string; price_cents?:number; appraisal_cents?:number; sale_method?:string; auction_at?:string; status:string; official_url?:string; notice_url?:string; collected_at:string };
type Source = { id:string; name:string; url:string; notices_url?:string };
type Filters = { q:string; state:string; city:string; neighborhood:string; property_type:string; max_price:string };
const emptyFilters: Filters = { q:'', state:'', city:'', neighborhood:'', property_type:'', max_price:'' };
const money = (cents?: number) => cents == null ? 'Não informado' : (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const cents = (value: string) => { const number = Number(value.replace(/\./g,'').replace(',','.').replace(/[^0-9.]/g,'')); return Number.isFinite(number) && number > 0 ? Math.round(number * 100) : undefined; };

export default function Auctions() {
  const [items, setItems] = useState<Listing[]>([]); const [sources, setSources] = useState<Source[]>([]);
  const [meta, setMeta] = useState({ total: 0, current_page: 1, last_page: 1 });
  const [filters, setFilters] = useState<Filters>(emptyFilters); const [applied, setApplied] = useState<Filters>(emptyFilters); const [provider, setProvider] = useState('');
  const [files, setFiles] = useState<File[]>([]); const [fileKey, setFileKey] = useState(0); const [importProvider, setImportProvider] = useState('caixa');
  const [error, setError] = useState(''); const [notice, setNotice] = useState(''); const [loading, setLoading] = useState(true); const [importing, setImporting] = useState(false); const [syncing, setSyncing] = useState(false);

  async function load(next = applied) {
    setLoading(true); setError('');
    try {
      const { data } = await api.get('/auctions', { params: { q: next.q || undefined, state: next.state || undefined, city: next.city || undefined, neighborhood: next.neighborhood || undefined, property_type: next.property_type || undefined, max_price_cents: cents(next.max_price), provider: provider || undefined } });
      setItems(data.data ?? []); setMeta(data.meta ?? {}); setSources(data.sources ?? []);
    } catch (exception:any) { setError(exception.response?.data?.message ?? 'Não foi possível carregar o catálogo oficial.'); }
    finally { setLoading(false); }
  }
  useEffect(() => { void load(applied); }, [applied, provider]);

  function search(event: FormEvent) { event.preventDefault(); setApplied({ ...filters, state: filters.state.toUpperCase() }); }
  async function automaticSearch() {
    if (!/^[A-Z]{2}$/.test(filters.state.toUpperCase())) { setError('Informe a UF com duas letras para buscar na fonte oficial.'); return; }
    setSyncing(true); setError(''); setNotice('');
    try {
      const { data } = await api.post('/auctions/sync', { state: filters.state.toUpperCase() });
      const next = { ...filters, state: filters.state.toUpperCase() }; setApplied(next);
      setNotice(`Fonte oficial atualizada: ${data.data.rows_imported} imóveis processados e ${data.data.rows_rejected} linhas ignoradas.`);
      await load(next);
    } catch (exception:any) { setError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Não foi possível consultar a lista oficial agora.'); }
    finally { setSyncing(false); }
  }
  async function upload(event: FormEvent) {
    event.preventDefault(); if (!files.length) return; setImporting(true); setError(''); setNotice('');
    let imported = 0; let rejected = 0;
    try {
      for (const file of files) {
        const body = new FormData(); body.append('provider', importProvider); body.append('file', file);
        const { data } = await api.post('/auctions/import', body); imported += data.data.rows_imported; rejected += data.data.rows_rejected;
      }
      setNotice(`${files.length} arquivo(s) processado(s): ${imported} imóveis importados e ${rejected} linhas ignoradas.`);
      setFiles([]); setFileKey((value)=>value+1); await load(applied);
    } catch (exception:any) { setError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Um dos arquivos não pôde ser importado. Confira se é CSV, TXT, XLSX ou ODS.'); }
    finally { setImporting(false); }
  }

  return <div className="space-y-6">
    <PageHeader title="Leilões e vendas públicas" subtitle="Informe o que procura. O sistema atualiza a lista oficial da CAIXA para a UF escolhida e aplica seus critérios automaticamente." />
    {error && <ErrorState message={error}/>}
    {notice && <p role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{notice}</p>}
    <form onSubmit={search} className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
      <div className="mb-4 flex items-start gap-3"><span className="rounded-xl bg-primary-50 p-2 text-primary-600 dark:bg-primary-950"><SlidersHorizontal className="h-5 w-5"/></span><div><h2 className="font-bold">Que imóvel você procura?</h2><p className="text-sm text-slate-500">A UF é obrigatória para atualizar a fonte. Os outros campos refinam o resultado.</p></div></div>
      <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        <label className="text-sm font-medium">UF *<input aria-label="UF da busca automática" required className="input mt-1 uppercase" maxLength={2} value={filters.state} onChange={(event)=>setFilters({...filters,state:event.target.value.toUpperCase().replace(/[^A-Z]/g,'')})} placeholder="SP"/></label>
        <label className="text-sm font-medium">Cidade<input className="input mt-1" value={filters.city} onChange={(event)=>setFilters({...filters,city:event.target.value})} placeholder="São Paulo"/></label>
        <label className="text-sm font-medium">Bairro<input className="input mt-1" value={filters.neighborhood} onChange={(event)=>setFilters({...filters,neighborhood:event.target.value})} placeholder="Perdizes"/></label>
        <label className="text-sm font-medium">Tipo do imóvel<select className="input mt-1" value={filters.property_type} onChange={(event)=>setFilters({...filters,property_type:event.target.value})}><option value="">Todos</option><option>Casa</option><option>Apartamento</option><option>Terreno</option><option>Comercial</option></select></label>
        <label className="text-sm font-medium">Valor máximo<input inputMode="decimal" className="input mt-1" value={filters.max_price} onChange={(event)=>setFilters({...filters,max_price:event.target.value})} placeholder="300.000,00"/></label>
        <label className="text-sm font-medium">Palavra-chave<input className="input mt-1" value={filters.q} onChange={(event)=>setFilters({...filters,q:event.target.value})} placeholder="Endereço ou descrição"/></label>
      </div>
      <div className="mt-4 flex flex-wrap gap-2"><button type="button" disabled={syncing} onClick={()=>void automaticSearch()} className="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-60"><RefreshCw className={`h-4 w-4 ${syncing?'animate-spin':''}`}/>{syncing?'Consultando a CAIXA...':'Buscar e atualizar automaticamente'}</button><button className="inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-bold"><Search className="h-4 w-4"/>Filtrar catálogo atual</button><button type="button" onClick={()=>{setFilters(emptyFilters);setApplied(emptyFilters);}} className="px-4 py-2.5 text-sm font-semibold text-slate-500">Limpar</button></div>
    </form>
    <details className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"><summary className="cursor-pointer font-bold">Importar arquivos manualmente</summary><div className="mt-4 grid gap-4 lg:grid-cols-[1fr_auto]"><div><p className="text-sm text-slate-500 dark:text-slate-400">Aceita vários arquivos oficiais de uma vez em CSV, TXT, XLSX ou ODS, inclusive CSV da CAIXA com linhas informativas antes do cabeçalho.</p><div className="mt-3 flex flex-wrap gap-2">{sources.map((source)=><span key={source.id} className="inline-flex gap-2"><a href={source.url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 rounded-lg border px-3 py-2 text-sm font-semibold">{source.name}<ExternalLink className="h-3.5 w-3.5"/></a>{source.notices_url&&<a href={source.notices_url} target="_blank" rel="noreferrer" className="rounded-lg border px-3 py-2 text-sm">Editais</a>}</span>)}</div></div><form onSubmit={upload} className="grid gap-2 sm:grid-cols-[9rem_minmax(12rem,1fr)_auto]"><select aria-label="Fonte dos arquivos" className="input" value={importProvider} onChange={(event)=>setImportProvider(event.target.value)}><option value="caixa">CAIXA</option><option value="vendasgov">VendasGov</option></select><input key={fileKey} aria-label="Arquivos oficiais" required multiple type="file" accept=".csv,.txt,.xlsx,.ods,text/csv" className="input" onChange={(event)=>setFiles(Array.from(event.target.files??[]))}/><button disabled={importing||!files.length} className="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white disabled:opacity-50 dark:bg-white dark:text-slate-950"><FileUp className="h-4 w-4"/>{importing?'Importando...':`Importar ${files.length||''}`}</button></form></div></details>
    <section className="flex flex-col gap-3 rounded-2xl border bg-white p-4 dark:bg-slate-900 sm:flex-row sm:items-center sm:justify-between"><div><label className="text-xs font-bold uppercase text-slate-500">Fonte exibida</label><select aria-label="Fonte" className="input mt-1 min-w-48" value={provider} onChange={(event)=>setProvider(event.target.value)}><option value="">Todas as fontes</option><option value="caixa">CAIXA</option><option value="vendasgov">VendasGov</option></select></div><div className="text-sm text-slate-500"><strong className="text-slate-900 dark:text-white">{meta.total}</strong> {meta.total===1?'imóvel encontrado':'imóveis encontrados'} · Página {meta.current_page} de {meta.last_page}</div></section>
    {loading?<p role="status" className="py-12 text-center text-slate-500">Buscando imóveis...</p>:items.length===0?<EmptyState title="Nenhum imóvel encontrado" description="Informe a UF e use “Buscar e atualizar automaticamente” para trazer a lista oficial mais recente."/>:<div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{items.map((item)=><article key={item.id} className="rounded-2xl border bg-white p-5 dark:bg-slate-900"><div className="flex items-start gap-3"><span className="rounded-xl bg-amber-100 p-2 text-amber-700 dark:bg-amber-950 dark:text-amber-300"><Landmark className="h-5 w-5"/></span><div className="min-w-0"><p className="text-xs font-bold uppercase text-slate-400">{item.provider} · {item.source_id}</p><h2 className="mt-1 font-bold">{item.title}</h2><p className="mt-1 text-xs text-slate-500">{[item.neighborhood,item.city,item.state].filter(Boolean).join(' · ')}</p></div></div><dl className="mt-4 grid grid-cols-2 gap-3 text-sm"><div><dt className="text-slate-500">Venda</dt><dd className="font-semibold">{money(item.price_cents)}</dd></div><div><dt className="text-slate-500">Avaliação</dt><dd>{money(item.appraisal_cents)}</dd></div><div><dt className="text-slate-500">Tipo</dt><dd>{item.property_type||'Não informado'}</dd></div><div><dt className="text-slate-500">Modalidade</dt><dd>{item.sale_method||'Consultar fonte'}</dd></div></dl><div className="mt-4 flex gap-3">{item.official_url&&<a href={item.official_url} target="_blank" rel="noreferrer" className="text-sm font-bold text-primary-600">Abrir fonte oficial</a>}{item.notice_url&&<a href={item.notice_url} target="_blank" rel="noreferrer" className="text-sm font-bold text-primary-600">Edital</a>}</div></article>)}</div>}
  </div>;
}
