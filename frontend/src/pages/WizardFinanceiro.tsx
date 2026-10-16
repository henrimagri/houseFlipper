import { useEffect, useState } from 'react';
import { ArrowLeft, Check, Circle, Loader2, Sparkles } from 'lucide-react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import api from '@/lib/api';

const brl = (value: unknown) => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const pct = (value: unknown) => `${Number(value || 0).toLocaleString('pt-BR', { maximumFractionDigits: 2 })}%`;
const stages = ['Compra', 'Financiamento', 'Reforma', 'Custos', 'Venda', 'Resultado'];
const renovationItems = ['Pintura', 'Pisos', 'Elétrica', 'Hidráulica', 'Banheiros', 'Cozinha', 'Marcenaria', 'Gesso', 'Iluminação', 'Móveis', 'Paisagismo', 'Limpeza', 'Outros'];
const operatingItems = ['Condomínio', 'IPTU', 'Energia', 'Água', 'Internet', 'Seguro', 'Parcelas do financiamento', 'Funcionário', 'Contador', 'Outros operacionais'];
const acquisitionItems = ['ITBI', 'Cartório', 'Registro', 'Documentação', 'Corretagem', 'Outros custos de aquisição'];
const renovationTotalKey = 'Reforma total';
const operatingTotalKey = 'Custos operacionais mensais';

export default function WizardFinanceiro() {
  const { id } = useParams(); const navigate = useNavigate();
  const [data, setData] = useState<any>(null); const [step, setStep] = useState(0); const [form, setForm] = useState<any>({});
  const [financing, setFinancing] = useState<any>({ financing_system: 'SAC' }); const [costs, setCosts] = useState<Record<string, string>>({});
  const [banks, setBanks] = useState<any[]>([]); const [loadingReference, setLoadingReference] = useState(false);
  const [notes, setNotes] = useState<Record<string, string>>({}); const [saving, setSaving] = useState(false); const [processing, setProcessing] = useState(false); const [error, setError] = useState('');
  const [estimatesApplied, setEstimatesApplied] = useState(false);

  async function load() {
    const response = await api.get(`/investment-analyses/${id}`); const analysis = response.data.data; setData(analysis); setForm(analysis);
    setFinancing(analysis.financing_detail ?? { financing_system: 'SAC' });
    const values: Record<string, string> = {}; const descriptions: Record<string, string> = {};
    (analysis.costs ?? []).forEach((item: any) => { values[item.description] = String(item.amount ?? ''); descriptions[item.description] = item.notes ?? ''; }); setCosts(values);
    const savedNotes = localStorage.getItem(`investflip-wizard-notes-${id}`); setNotes(savedNotes ? JSON.parse(savedNotes) : descriptions);
    if (analysis.intelligence?.financial_analysis_complete) setStep(5);
  }
  useEffect(() => { load().catch(() => setError('Não foi possível carregar a análise.')); }, [id]);
  useEffect(() => { if (Object.keys(notes).length) localStorage.setItem(`investflip-wizard-notes-${id}`, JSON.stringify(notes)); }, [id, notes]);

  const opportunity = data?.opportunity; const opportunityScore = data?.intelligence?.opportunity_score;
  const purchase = Number(form.purchase_price || 0); const down = Number(form.down_payment || 0); const financed = Math.max(0, purchase - down);
  const sum = (names: string[]) => names.reduce((total, name) => total + Number(costs[name] || 0), 0);
  const acquisitionTotal = sum(acquisitionItems); const renovationDetailsTotal = sum(renovationItems); const operatingDetailsTotal = sum(operatingItems);
  const renovationTotal = renovationDetailsTotal || Number(costs[renovationTotalKey] || 0); const operatingMonthly = operatingDetailsTotal || Number(costs[operatingTotalKey] || 0); const months = Number(form.expected_months_until_sale || 1);

  function applyAcquisitionEstimates(force = false) {
    if (!data) return;
    const city = String(opportunity.city || '').toLocaleLowerCase('pt-BR'); const state = String(opportunity.state || '').toUpperCase();
    const isItapevi = city.includes('itapevi') && state === 'SP';
    const itbi = isItapevi && financed > 0 ? financed * .005 + down * .02 : purchase * (state === 'SP' ? .03 : .02);
    const estimates: Record<string, number> = { ITBI: itbi, Cartório: purchase * .004, Registro: purchase * .005, Documentação: purchase * .0015, Corretagem: 0, 'Outros custos de aquisição': 0 };
    setCosts(current => { const next = { ...current }; Object.entries(estimates).forEach(([name, value]) => { if (force || current[name] === undefined || current[name] === '') next[name] = value ? value.toFixed(2) : ''; }); return next; });
    setEstimatesApplied(true);
  }
  useEffect(() => { if (data && !estimatesApplied) applyAcquisitionEstimates(false); }, [data?.id, estimatesApplied]);

  async function loadFinancingReference(bankId?: string) {
    if (!data) return;
    setLoadingReference(true);
    try {
      const response = await api.get(`/investment-analyses/${id}/financing-reference`, { params: { bank: bankId ?? financing.bank_id ?? '', system: financing.financing_system ?? 'SAC', installments: Number(financing.total_installments || 360), paid_installments: Number(financing.paid_installments || 0), principal: financed } });
      setBanks(response.data.banks ?? []); setFinancing((current: any) => ({ ...current, ...response.data.data }));
    } finally { setLoadingReference(false); }
  }
  useEffect(() => {
    if (step !== 1 || !data) return;
    const timer = window.setTimeout(() => loadFinancingReference(), 350);
    return () => window.clearTimeout(timer);
  }, [step, data?.id, financing.bank_id, financing.financing_system, financing.total_installments, financing.paid_installments, financed]);

  async function syncCosts(names: string[], category: (name: string) => string) {
    for (const name of names) {
      const amount = Number(costs[name] || 0); if (!amount) continue;
      const baseName = name.replace(/ real$/, '');
      const payload = { category: category(baseName), description: name, amount, payment_status: name.endsWith(' real') ? 'paid' : 'pending' };
      const existing = (data.costs ?? []).find((item: any) => item.description === name);
      if (existing) await api.put(`/investment-costs/${existing.id}`, payload); else await api.post(`/investment-analyses/${id}/costs`, payload);
    }
  }

  async function deleteCosts(names: string[]) {
    for (const name of names) {
      const existing = (data.costs ?? []).find((item: any) => item.description === name);
      if (existing) await api.delete(`/investment-costs/${existing.id}`);
    }
  }

  async function saveCurrent() {
    setSaving(true); setError('');
    try {
      if (step === 0) {
        await api.put(`/investment-analyses/${id}`, { ...form, purchase_price: purchase, down_payment: down, expected_sale_price: Number(form.expected_sale_price || opportunity.estimated_market_price || 0), expected_months_until_sale: months });
        await syncCosts(acquisitionItems, name => name === 'ITBI' ? 'itbi' : name === 'Documentação' ? 'documentation' : name === 'Registro' || name === 'Cartório' ? 'registry' : name === 'Corretagem' ? 'brokerage' : 'acquisition');
      } else if (step === 1) {
        await api.put(`/investment-analyses/${id}`, { ...form, purchase_price: purchase, down_payment: down, expected_sale_price: Number(form.expected_sale_price || opportunity.estimated_market_price || 0), expected_months_until_sale: months });
        await api.put(`/investment-analyses/${id}/financing`, financing);
      }
      else if (step === 2) {
        const legacyRealNames = renovationItems.map(name => `${name} real`);
        if (renovationDetailsTotal > 0) { await deleteCosts([renovationTotalKey, ...legacyRealNames]); await syncCosts(renovationItems, name => name === 'Pintura' ? 'painting' : name === 'Elétrica' ? 'electricity' : name === 'Hidráulica' ? 'plumbing' : name === 'Marcenaria' ? 'woodwork' : name === 'Outros' ? 'other' : 'renovation'); }
        else { await deleteCosts([...renovationItems, ...legacyRealNames]); await syncCosts([renovationTotalKey], () => 'renovation'); }
      }
      else if (step === 3) {
        const legacyRealNames = operatingItems.map(name => `${name} real`);
        if (operatingDetailsTotal > 0) { await deleteCosts([operatingTotalKey, ...legacyRealNames]); await syncCosts(operatingItems, name => ({ Condomínio: 'condominium', IPTU: 'property_tax', Energia: 'energy', Água: 'water', Internet: 'internet', Seguro: 'insurance', 'Parcelas do financiamento': 'financing_installment' } as Record<string, string>)[name] ?? 'other'); }
        else { await deleteCosts([...operatingItems, ...legacyRealNames]); await syncCosts([operatingTotalKey], () => 'other'); }
      }
      else if (step === 4) {
        const saleCosts = Number(form.sale_commission || 0) + Number(form.sale_marketing || 0) + Number(form.sale_documentation || 0) + Number(form.sale_other_costs || 0);
        await api.post(`/investment-analyses/${id}/result`, { sale_price: Number(form.expected_sale_price || 0), sale_costs: saleCosts, flip_duration_months: months });
      }
      await load();
      if (step === 4) { setProcessing(true); window.setTimeout(() => { setProcessing(false); setStep(5); }, 2000); } else setStep(current => Math.min(5, current + 1));
    } catch (exception: any) { setError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Revise os dados informados.'); }
    finally { setSaving(false); }
  }

  async function createProject() {
    setSaving(true); setError('');
    try {
      const property = await api.post('/propriedades', { apelido: opportunity.title, tipo: 'imovel', area_m2: opportunity.area_m2, valor_aquisicao: purchase, valor_venda_alvo: form.expected_sale_price, status: 'prospeccao', endereco: { cidade: opportunity.city, estado: opportunity.state } });
      await api.post('/projetos', { propriedade_id: property.data.data?.id ?? property.data.id, nome: `Flip · ${opportunity.title}`, descricao: `Projeto originado da análise financeira #${id}.`, orcamento_total: down + acquisitionTotal + renovationTotal + operatingMonthly * months, status: 'planejamento' });
      navigate('/projetos');
    } catch (exception: any) { setError(exception.response?.data?.message || 'Não foi possível iniciar o projeto.'); } finally { setSaving(false); }
  }

  if (!data) return <div className="p-10 text-center">{error || 'Preparando assistente financeiro...'}</div>;
  if (processing) return <Processing />;
  const progress = Math.round((step / 5) * 100);

  return <div className="max-w-[1500px] mx-auto space-y-5 pb-10">
    <div className="flex items-center justify-between gap-4"><Link to={`/analises-financeiras/${id}`} className="text-sm text-primary-600 flex items-center gap-1"><ArrowLeft className="w-4 h-4" />Sair e continuar depois</Link><span className="text-sm font-bold">Etapa {Math.min(step + 1, 6)} de 6</span></div>
    <header className="sticky top-0 z-20 bg-white/95 backdrop-blur border rounded-2xl p-4 shadow-sm"><div className="flex flex-col lg:flex-row lg:items-center gap-5"><div className="flex items-center gap-3 lg:w-72">{opportunity.cover_url && <img src={opportunity.cover_url} className="w-16 h-14 rounded-xl object-cover" />}<div className="min-w-0"><strong className="block truncate">{opportunity.title}</strong><span className="text-xs text-gray-500">{opportunity.city} · Score {opportunityScore.score}</span></div></div><div className="flex-1 overflow-x-auto"><div className="flex min-w-[780px]">{['Opportunity Score', ...stages].map((label, index) => { const position = index - 1; const done = position < step; const active = position === step; return <div key={label} className="flex-1 flex items-center"><div className={`w-8 h-8 rounded-full grid place-items-center shrink-0 ${done ? 'bg-emerald-500 text-white' : active ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-400'}`}>{done ? <Check className="w-4 h-4" /> : index}</div><span className={`text-[11px] ml-2 ${active ? 'font-bold text-primary-700' : 'text-gray-500'}`}>{label}</span>{index < stages.length && <div className={`h-0.5 flex-1 mx-2 ${done ? 'bg-emerald-300' : 'bg-gray-200'}`} />}</div>; })}</div></div><div className="text-right min-w-32"><strong>{progress}%</strong><div className="h-2 bg-gray-100 rounded-full mt-2"><div className="h-full bg-primary-600 rounded-full transition-all" style={{ width: `${progress}%` }} /></div></div></div><div className="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 pt-4 border-t text-xs"><span>Compra <strong className="block text-sm">{brl(purchase)}</strong></span><span>Mercado <strong className="block text-sm">{brl(opportunity.estimated_market_price)}</strong></span><span>Opportunity Score <strong className="block text-sm">{opportunityScore.score} pontos</strong></span><span>Cidade <strong className="block text-sm">{opportunity.city}</strong></span></div></header>
    {error && <div className="bg-red-50 text-red-700 border border-red-200 p-3 rounded-xl">{error}</div>}
    <main className="grid xl:grid-cols-[1fr_340px] gap-5 items-start"><section className="bg-white border rounded-2xl p-6 md:p-8">{step === 0 && <PurchaseStep form={form} setForm={setForm} costs={costs} setCosts={setCosts} opportunity={opportunity} onEstimate={() => applyAcquisitionEstimates(true)} />}{step === 1 && <FinancingStep financing={financing} setFinancing={setFinancing} form={form} setForm={setForm} banks={banks} loading={loadingReference} onBankChange={loadFinancingReference} />}{step === 2 && <MacroCostStep title="Reforma" subtitle="Informe o orçamento total da reforma. Se precisar, abra o detalhamento por ambiente ou serviço." totalLabel="Valor total estimado da reforma" totalKey={renovationTotalKey} detailHint="Quero detalhar a reforma por item" items={renovationItems} costs={costs} setCosts={setCosts} notes={notes} setNotes={setNotes} />}{step === 3 && <MacroCostStep title="Custos Operacionais" subtitle="Informe o custo mensal total para manter o imóvel até a venda. Se quiser, detalhe cada despesa recorrente." totalLabel="Custo operacional mensal total" totalKey={operatingTotalKey} detailHint="Quero detalhar os custos por item" items={operatingItems} costs={costs} setCosts={setCosts} notes={notes} setNotes={setNotes} extra={<div className="grid md:grid-cols-2 gap-4"><IntegerField label="Tempo previsto da obra (meses)" value={form.renovation_months ?? ''} onChange={(value: string) => setForm({ ...form, renovation_months: value })} /><IntegerField label="Tempo previsto até venda (meses)" value={form.expected_months_until_sale ?? ''} onChange={(value: string) => setForm({ ...form, expected_months_until_sale: value })} /></div>} />}{step === 4 && <SaleStep form={form} setForm={setForm} />}{step === 5 && <ResultStep data={data} onCreateProject={createProject} saving={saving} />}</section><aside className="bg-slate-950 text-white rounded-2xl p-6 xl:sticky xl:top-56"><h3 className="font-bold text-lg">Resumo em tempo real</h3>{step === 0 && <Summary rows={[['Capital próprio', brl(down + acquisitionTotal)], ['Valor financiado', brl(financed)], ['Custos de aquisição', brl(acquisitionTotal)], ['Total até agora', brl(purchase + acquisitionTotal)]]} />}{step === 1 && <Summary rows={[['Valor financiado', brl(financed)], ['Total pago no flip', brl(Number(financing.installment_value || 0) * months)], ['Saldo previsto', brl(financing.outstanding_balance)]]} />}{step === 2 && <Summary rows={[['Total da reforma', brl(renovationTotal)], ['Detalhamento', renovationDetailsTotal > 0 ? brl(renovationDetailsTotal) : 'Não detalhado'], ['Valor por m²', brl(renovationTotal / Math.max(1, Number(opportunity.area_m2)))], ['Percentual do imóvel', pct(renovationTotal / Math.max(1, purchase) * 100)]]} />}{step === 3 && <Summary rows={[['Custo mensal', brl(operatingMonthly)], ['Detalhamento', operatingDetailsTotal > 0 ? brl(operatingDetailsTotal) : 'Não detalhado'], ['Até a venda', brl(operatingMonthly * months)], ['Prazo', `${months} meses`]]} />}{step === 4 && <Summary rows={[['Venda esperada', brl(form.expected_sale_price)], ['Custos da venda', brl(Number(form.sale_commission || 0) + Number(form.sale_marketing || 0) + Number(form.sale_documentation || 0) + Number(form.sale_other_costs || 0))], ['Lucro bruto', brl(Number(form.expected_sale_price || 0) - purchase)]]} />}{step === 5 && <Summary rows={[['Investment Score', `${data.intelligence.investment_score?.score ?? 0} pontos`], ['Recomendação', data.intelligence.investment_score?.signal?.label ?? 'Concluindo'], ['Confiança', pct(data.intelligence.investment_score?.confidence)]]} />}</aside></main>
    {step < 5 && <footer className="flex justify-between"><button type="button" disabled={step === 0} onClick={() => setStep(current => current - 1)} className="border px-5 py-3 rounded-xl font-semibold disabled:opacity-30">Voltar</button><button type="button" disabled={saving} onClick={saveCurrent} className="bg-primary-600 text-white px-7 py-3 rounded-xl font-bold">{saving ? 'Salvando...' : step === 4 ? 'Gerar Investment Score' : 'Salvar e ir para próxima etapa'}</button></footer>}
  </div>;
}

function PurchaseStep({ form, setForm, costs, setCosts, opportunity, onEstimate }: any) { return <><div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4"><StepTitle title="Compra" subtitle="Vamos entender quanto realmente será necessário investir para adquirir este imóvel." /><button type="button" onClick={onEstimate} className="border border-primary-200 text-primary-700 px-4 py-2 rounded-xl text-sm font-bold shrink-0">Recalcular estimativas</button></div><div className="bg-blue-50 border border-blue-100 text-blue-800 rounded-xl p-4 mb-5 text-xs"><strong>Estimativas automáticas e editáveis.</strong> O ITBI considera a referência de {opportunity.city}/{opportunity.state} quando disponível. Cartório e registro são estimativas por faixa; confirme antes da compra.</div><div className="grid md:grid-cols-2 gap-4"><MoneyField label="Valor da compra" value={form.purchase_price} onChange={(value: number) => setForm({ ...form, purchase_price: value })} /><MoneyField label="Entrada" value={form.down_payment} onChange={(value: number) => setForm({ ...form, down_payment: value })} />{acquisitionItems.map(item => <MoneyField key={item} label={`${item} · estimativa editável`} value={costs[item] ?? ''} onChange={(value: number) => setCosts({ ...costs, [item]: value })} />)}</div></>; }
function FinancingStep({ financing, setFinancing, form, setForm, banks, loading, onBankChange }: any) { return <><div className="flex items-start justify-between gap-4"><StepTitle title="Financiamento" subtitle="Compare bancos e simule as condições pela API de referência." />{loading && <span className="text-xs text-primary-600 flex gap-2 items-center"><Loader2 className="w-4 h-4 animate-spin" />Recalculando</span>}</div><div className="bg-emerald-50 border border-emerald-100 text-emerald-800 rounded-xl p-4 mb-5 text-xs"><strong>Menor taxa selecionada automaticamente.</strong> As taxas são referências de mercado; a proposta final depende da análise de crédito do banco.</div><div className="grid md:grid-cols-2 gap-4"><label className="text-sm">Banco<select className="input mt-1" value={financing.bank_id ?? ''} onChange={event => onBankChange(event.target.value)}>{banks.map((bank: any, index: number) => <option key={bank.id} value={bank.id}>{bank.name} — {pct(bank.yearly_rate)} a.a.{index === 0 ? ' · menor taxa' : ''}</option>)}</select></label><label className="text-sm">Sistema<select className="input mt-1" value={financing.financing_system ?? 'SAC'} onChange={event => setFinancing({ ...financing, financing_system: event.target.value })}><option>SAC</option><option>PRICE</option></select></label><PercentField label="Taxa anual" value={financing.interest_rate_yearly} disabled /><PercentField label="Taxa mensal" value={financing.interest_rate_monthly} disabled /><IntegerField label="Quantidade de parcelas" value={financing.total_installments ?? ''} onChange={(value: string) => setFinancing({ ...financing, total_installments: value })} /><IntegerField label="Parcelas pagas até a venda" value={financing.paid_installments ?? 0} onChange={(value: string) => setFinancing({ ...financing, paid_installments: value })} /><MoneyField label="Valor da parcela" value={financing.installment_value} disabled /><MoneyField label="Entrada" value={form.down_payment} onChange={(value: number) => setForm({ ...form, down_payment: value })} /><MoneyField label="Saldo devedor" value={financing.outstanding_balance} disabled /><MoneyField label="Quitação estimada" value={financing.payoff_amount} disabled /></div><p className="text-xs text-gray-500 mt-4">Menos parcelas aumentam a prestação; uma entrada maior reduz o valor financiado e, consequentemente, a parcela.</p></>; }
function SaleStep({ form, setForm }: any) { return <><StepTitle title="Venda" subtitle="Defina as premissas de saída para calcular o retorno líquido." /><div className="grid md:grid-cols-2 gap-4">{[['Valor esperado da venda', 'expected_sale_price'], ['Comissão imobiliária', 'sale_commission'], ['Marketing', 'sale_marketing'], ['Documentação da venda', 'sale_documentation'], ['Custos diversos', 'sale_other_costs']].map(([label, key]) => <MoneyField key={key} label={label} value={form[key] ?? ''} onChange={(value: number) => setForm({ ...form, [key]: value })} />)}<IntegerField label="Prazo esperado (meses)" value={form.expected_months_until_sale ?? ''} onChange={(value: string) => setForm({ ...form, expected_months_until_sale: value })} /></div></>; }
function MacroCostStep({ title, subtitle, totalLabel, totalKey, detailHint, items, costs, setCosts, notes, setNotes, extra }: any) { const detailTotal = items.reduce((total: number, item: string) => total + Number(costs[item] || 0), 0); const [showDetails, setShowDetails] = useState(detailTotal > 0); return <><StepTitle title={title} subtitle={subtitle} />{extra && <div className="mb-6">{extra}</div>}<div className="rounded-2xl border bg-gradient-to-br from-white to-slate-50 p-5 mb-5"><div className="grid md:grid-cols-[1fr_auto] gap-4 items-end"><MoneyField label={totalLabel} value={detailTotal > 0 ? detailTotal : costs[totalKey] ?? ''} disabled={detailTotal > 0} onChange={(value: number) => setCosts({ ...costs, [totalKey]: value })} /><button type="button" onClick={() => setShowDetails(!showDetails)} className="border border-primary-200 text-primary-700 px-4 py-3 rounded-xl text-sm font-bold">{showDetails ? 'Ocultar detalhamento' : detailHint}</button></div>{detailTotal > 0 && <p className="text-xs text-emerald-700 mt-3">Total calculado pelo detalhamento: <strong>{brl(detailTotal)}</strong>. Para voltar ao total manual, zere os itens detalhados.</p>}<p className="text-xs text-gray-500 mt-2">Use o valor total para uma análise rápida. Abra o detalhamento apenas se quiser controlar os itens separadamente.</p></div>{showDetails && <div className="grid md:grid-cols-2 gap-4">{items.map((item: string) => <div key={item} className="border rounded-xl p-4 bg-white"><strong>{item}</strong><div className="mt-3"><MoneyField label="Valor" value={costs[item] ?? ''} onChange={(value: number) => setCosts({ ...costs, [item]: value })} /></div><TextField label="Observação" value={notes[item] ?? ''} onChange={(value: string) => setNotes({ ...notes, [item]: value })} /></div>)}</div>}</>; }
function CostGrid({ title, subtitle, items, costs, setCosts, notes, setNotes, extra }: any) { return <><StepTitle title={title} subtitle={subtitle} />{extra && <div className="mb-6">{extra}</div>}<div className="grid md:grid-cols-2 gap-4">{items.map((item: string) => <div key={item} className="border rounded-xl p-4"><strong>{item}</strong><div className="grid grid-cols-2 gap-3 mt-3"><MoneyField label="Valor estimado" value={costs[item] ?? ''} onChange={(value: number) => setCosts({ ...costs, [item]: value })} /><MoneyField label="Valor real" value={costs[`${item} real`] ?? ''} onChange={(value: number) => setCosts({ ...costs, [`${item} real`]: value })} /></div><TextField label="Observação" value={notes[item] ?? ''} onChange={(value: string) => setNotes({ ...notes, [item]: value })} /></div>)}</div></>; }
function ResultStep({ data, onCreateProject, saving }: any) { const score = data.intelligence.investment_score; const result = data.result; return <div className="space-y-6"><div className="text-center py-4"><Sparkles className="w-10 h-10 text-violet-600 mx-auto" /><div className="text-xs uppercase tracking-widest text-violet-600 font-bold mt-3">Investment Score</div><div className="text-6xl font-black mt-2">{score?.score ?? 0}</div><div className="text-2xl font-black text-emerald-600 mt-2">{score?.signal?.label}</div><div className="text-sm text-gray-500 mt-1">Confiança {pct(score?.confidence)}</div></div><div className="grid md:grid-cols-3 gap-3">{[['Capital próprio', brl(result?.total_own_capital_invested)], ['Lucro líquido', brl(result?.net_profit)], ['ROI', pct(result?.roi_percentage)], ['ROI anualizado', pct(result?.annualized_roi_percentage)], ['Tempo', `${result?.flip_duration_months ?? 0} meses`], ['Retorno mensal', brl(Number(result?.net_profit || 0) / Math.max(1, Number(result?.flip_duration_months || 1)))], ['Retorno diário', brl(Number(result?.net_profit || 0) / Math.max(1, Number(result?.flip_duration_months || 1) * 30))]].map(([label, value]) => <div key={label} className="bg-gray-50 rounded-xl p-4"><span className="text-xs text-gray-500">{label}</span><strong className="block text-lg mt-1">{value}</strong></div>)}</div><div className="bg-violet-50 border border-violet-100 rounded-2xl p-5"><h3 className="font-bold">Análise Inteligente do InvestFlip</h3><p className="text-sm text-gray-700 mt-2 leading-6">{score?.explanation}</p><span className="text-[11px] text-gray-400">Estrutura preparada para futura integração com IA.</span></div><div className="flex flex-wrap justify-center gap-3"><button onClick={() => navigateToAnalysis(data.id)} className="border px-5 py-3 rounded-xl font-bold">Salvar análise</button><button disabled={saving} onClick={onCreateProject} className="bg-primary-600 text-white px-5 py-3 rounded-xl font-bold">{saving ? 'Criando...' : 'Iniciar Projeto'}</button></div></div>; }
function navigateToAnalysis(id: number) { window.location.href = `/analises-financeiras/${id}`; }
function Processing() { const messages = ['Analisando imóvel...', 'Calculando ROI...', 'Calculando lucro líquido...', 'Analisando riscos...', 'Comparando mercado...', 'Gerando Investment Score...']; return <div className="min-h-[70vh] grid place-items-center"><div className="text-center"><Loader2 className="w-14 h-14 text-primary-600 animate-spin mx-auto" /><h1 className="text-3xl font-black mt-6">Análise financeira em processamento</h1><div className="space-y-2 mt-6">{messages.map((message, index) => <div key={message} className="text-sm text-gray-500 animate-pulse" style={{ animationDelay: `${index * 150}ms` }}>{message}</div>)}</div></div></div>; }
function StepTitle({ title, subtitle }: { title: string; subtitle: string }) { return <div className="mb-7"><div className="text-xs uppercase tracking-widest text-primary-600 font-bold">Due Diligence Financeira</div><h1 className="text-3xl font-black mt-2">{title}</h1><p className="text-gray-500 mt-2">{subtitle}</p></div>; }
function Summary({ rows }: { rows: [string, string][] }) { return <div className="space-y-4 mt-6">{rows.map(([label, value]) => <div key={label} className="border-b border-white/10 pb-3"><span className="text-xs text-slate-400 block">{label}</span><strong className="text-xl block mt-1">{value}</strong></div>)}</div>; }
function NumberField({ label, value, onChange, disabled = false }: any) { return <label className="text-sm"><span>{label}</span><input className="input mt-1" inputMode="decimal" disabled={disabled} value={value ?? ''} onChange={event => onChange(event.target.value.replace(',', '.'))} /></label>; }
function IntegerField({ label, value, onChange, disabled = false }: any) { return <label className="text-sm"><span>{label}</span><input className="input mt-1" inputMode="numeric" disabled={disabled} value={value ?? ''} onChange={event => onChange(event.target.value.replace(/\D/g, ''))} /></label>; }
function TextField({ label, value, onChange }: any) { return <label className="text-sm block mt-3"><span>{label}</span><input className="input mt-1" value={value} onChange={event => onChange(event.target.value)} /></label>; }
function MoneyField({ label, value, onChange, disabled = false }: any) { return <label className="text-sm"><span>{label}</span><input className={`input mt-1 ${disabled ? 'bg-gray-50 font-semibold' : ''}`} inputMode="numeric" disabled={disabled} value={brl(value)} onChange={event => { const digits = event.target.value.replace(/\D/g, ''); onChange?.(digits ? Number(digits) / 100 : 0); }} /></label>; }
function PercentField({ label, value, disabled = false }: any) { return <label className="text-sm"><span>{label}</span><input className="input mt-1 bg-gray-50 font-semibold" disabled={disabled} value={pct(value)} readOnly /></label>; }
