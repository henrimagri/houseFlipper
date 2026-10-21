import { FormEvent, KeyboardEvent, useEffect, useMemo, useRef, useState } from 'react';
import { ArrowDown, Box, Briefcase, Check, Clock3, Copy, Database, History, Loader2, MapPin, Plus, RefreshCw, Send, Sparkles, ThumbsDown, ThumbsUp, X } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import api from '@/lib/api';
import { writeJson } from '@/lib/browserStore';

type Listing = {
  id: string;
  title: string;
  origin?: string;
  source?: string;
  asking_price?: number;
  neighborhood?: string;
  city?: string;
  state?: string;
  address?: string;
  bedrooms?: number | null;
  bathrooms?: number | null;
  parking_spaces?: number | null;
  area_m2?: number | null;
  visualization_path?: string | null;
  detail_path?: string | null;
  match_reasons?: string[];
  match_score?: number;
  model_note?: string;
  property_type?: string | null;
  source_url?: string | null;
  source_key?: string;
  thumbnail?: string | null;
  estimated_market_price?: number;
  score?: number;
  signal?: string;
};

type Conversation = {
  session_id: number;
  title?: string;
  product_slug?: string;
  provider?: string;
  answer: string;
  intent: string;
  criteria: { required?: Record<string, unknown>; preferred?: Record<string, unknown> };
  listings: Listing[];
  recommendation?: { title: string; explanation: string; listing_id?: string } | null;
  no_match?: { message: string; suggestions?: string[] } | null;
  messages: Array<{ id: number; role: string; content: string }>;
  market?: { status?: string; source?: string; count?: number };
  agent?: { key: string; label: string } | null;
  citations?: Array<{ id: string; title: string; excerpt: string; source_name: string; source_url?: string; source_version?: string; verified_at?: string }>;
  tool_events?: Array<{ tool: string; label: string; status: string; count?: number }>;
  assistant_message_id?: number | null;
};

type ConversationSummary = { id: number; title?: string; product_slug: string; active_agent?: string; updated_at: string };

type RewriteAction = 'shorten' | 'expand' | 'new_version';

const REWRITE_PROMPTS: Record<RewriteAction, string> = {
  shorten: 'Encurte sua resposta anterior para no máximo cinco linhas, sem alterar fatos, valores, fontes ou conclusões.',
  expand: 'Expanda sua resposta anterior com mais detalhes úteis, mantendo somente os dados já verificados e deixando as limitações explícitas.',
  new_version: 'Escreva uma nova versão da sua resposta anterior, preservando fatos, valores, fontes e conclusões.',
};

const TONE_PROMPTS: Record<string, string> = {
  direct: 'Reescreva sua resposta anterior em tom direto e objetivo, sem alterar fatos, valores, fontes ou conclusões.',
  professional: 'Reescreva sua resposta anterior em tom profissional, claro e sóbrio, sem alterar fatos, valores, fontes ou conclusões.',
  didactic: 'Reescreva sua resposta anterior em tom didático, explicando os termos importantes sem alterar fatos, valores, fontes ou conclusões.',
};

const PRODUCT_LABELS: Record<string, string> = {
  corretor: 'Corretor',
  imobiliaria: 'Imobiliária',
  house_flipper: 'House Flipper',
};

const EXAMPLES = [
  'Quero um apartamento em Fortaleza, Aldeota ou Meireles, até R$ 650 mil, dois quartos e duas vagas.',
  'Agora procure opções parecidas, mas com varanda e condomínio mais barato.',
  'Qual desses você considera mais interessante?',
];

const money = (value?: number | null) => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function AiConsultantPanel() {
  const navigate = useNavigate();
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [showExamples, setShowExamples] = useState(false);
  const [savingId, setSavingId] = useState<string | null>(null);
  const [savedIds, setSavedIds] = useState<Record<string, boolean>>({});
  const [compareIds, setCompareIds] = useState<string[]>([]);
  const [conversation, setConversation] = useState<Conversation | null>(null);
  const [showJump, setShowJump] = useState(false);
  const [history, setHistory] = useState<ConversationSummary[]>([]);
  const [showHistory, setShowHistory] = useState(false);
  const [feedback, setFeedback] = useState<'helpful' | 'not_helpful' | null>(null);
  const [memoryEnabled, setMemoryEnabled] = useState(false);
  const [copiedMessageId, setCopiedMessageId] = useState<number | null>(null);
  const [notice, setNotice] = useState('');
  const threadRef = useRef<HTMLDivElement>(null);
  const areaRef = useRef<HTMLTextAreaElement>(null);
  const abortRef = useRef<AbortController | null>(null);
  const copyTimerRef = useRef<number | null>(null);

  function refreshHistory() {
    api.get('/ai/conversation-history').then(({ data }) => setHistory(data.data ?? [])).catch(() => undefined);
  }

  useEffect(() => {
    api.get('/ai/conversations').then(({ data }) => {
      if (data.data?.messages?.length) {
        setConversation({
          ...data.data,
          answer: data.data.messages.at(-1)?.content ?? '',
          intent: 'search',
        });
      }
    }).catch(() => undefined);
    refreshHistory();
    api.get('/ai/preferences').then(({ data }) => setMemoryEnabled(Boolean(data.data?.enabled))).catch(() => undefined);
  }, []);

  useEffect(() => () => {
    abortRef.current?.abort();
    if (copyTimerRef.current !== null) window.clearTimeout(copyTimerRef.current);
  }, []);

  useEffect(() => {
    const node = areaRef.current;
    if (!node) return;
    node.style.height = '52px';
    node.style.height = `${Math.min(Math.max(node.scrollHeight, 52), 160)}px`;
  }, [message]);

  useEffect(() => {
    threadRef.current?.scrollTo({ top: threadRef.current.scrollHeight, behavior: 'smooth' });
  }, [conversation?.messages?.length, loading]);

  async function send(text = message) {
    if (loading || text.trim().length < 3) return;
    setLoading(true);
    setError('');
    setNotice('');
    setFeedback(null);
    const controller = new AbortController();
    abortRef.current = controller;
    try {
      const response = await api.post('/ai/conversations', { message: text, session_id: conversation?.session_id }, { signal: controller.signal });
      setConversation(current => ({ ...current, ...response.data.data } as Conversation));
      refreshHistory();
      setMessage('');
      setShowExamples(false);
    } catch (exception: any) {
      if (exception.code === 'ERR_CANCELED') setNotice('Geração cancelada nesta tela. Você pode ajustar a mensagem e tentar novamente.');
      else setError(exception.response?.data?.message ?? 'Não foi possível interpretar a solicitação agora.');
    } finally {
      abortRef.current = null;
      setLoading(false);
    }
  }

  async function newConversation() {
    abortRef.current?.abort();
    setError('');
    setNotice('');
    const { data } = await api.post('/ai/conversations/session', {});
    setConversation({ ...data.data, answer: '', intent: 'search' });
    setMessage('');
    setFeedback(null);
    setShowHistory(false);
    refreshHistory();
    requestAnimationFrame(() => areaRef.current?.focus());
  }

  async function openConversation(sessionId: number) {
    abortRef.current?.abort();
    setNotice('');
    const { data } = await api.get('/ai/conversations', { params: { session_id: sessionId } });
    setConversation({ ...data.data, answer: data.data.messages?.at(-1)?.content ?? '', intent: 'search' });
    setFeedback(null);
    setShowHistory(false);
  }

  async function rate(rating: 'helpful' | 'not_helpful') {
    if (!conversation?.session_id) return;
    await api.post(`/ai/conversations/${conversation.session_id}/feedback`, { rating, message_id: conversation.assistant_message_id });
    setFeedback(rating);
  }

  async function toggleMemory() {
    const enabled = !memoryEnabled;
    await api.put('/ai/preferences', { enabled, preferences: enabled ? { search_defaults: conversation?.criteria ?? { required: {}, preferred: {} } } : {} });
    setMemoryEnabled(enabled);
  }

  function onSubmit(event: FormEvent) {
    event.preventDefault();
    void send();
  }

  function onKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
    if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing) {
      event.preventDefault();
      void send();
    }
  }

  function cancelGeneration() {
    abortRef.current?.abort();
  }

  async function copyMessage(id: number, content: string) {
    try {
      await navigator.clipboard.writeText(content);
      setCopiedMessageId(id);
      if (copyTimerRef.current !== null) window.clearTimeout(copyTimerRef.current);
      copyTimerRef.current = window.setTimeout(() => setCopiedMessageId(null), 1800);
    } catch {
      setError('Não foi possível copiar a resposta neste navegador.');
    }
  }

  function rewrite(action: RewriteAction) {
    if (!canRewrite) return;
    void send(REWRITE_PROMPTS[action]);
  }

  function changeTone(tone: string) {
    if (!canRewrite || !TONE_PROMPTS[tone]) return;
    void send(TONE_PROMPTS[tone]);
  }

  function openListing(item: Listing, view3d = false) {
    if (item.origin === 'inventory_property' || item.origin === 'inventory_opportunity') {
      navigate(view3d ? (item.visualization_path || item.detail_path || '/') : (item.detail_path || item.visualization_path || '/'));
      return;
    }
    const payload = {
      selected: { ...item, property_type: item.property_type || 'apartment' },
      comparables: conversation?.listings ?? [item],
      collectedAt: new Date().toISOString(),
      propertyTypeLabel: 'Imóvel',
    };
    writeJson(`market-analysis:${item.id}`, payload);
    navigate(`/ia-inteligente/analise/${encodeURIComponent(item.id)}${view3d ? '#section-3d' : ''}`, { state: payload });
  }

  async function addToPortfolio(item: Listing) {
    if (item.origin === 'inventory_property') return;
    setSavingId(item.id);
    setError('');
    try {
      await api.post('/opportunities', {
        title: item.title,
        description: item.model_note || `Lead a partir da conversa da IA. Origem: ${originLabel(item)}.`,
        property_type: ['house', 'apartment', 'land', 'commercial', 'auction', 'other'].includes(String(item.property_type)) ? item.property_type : 'apartment',
        source: item.source_key && ['olx', 'zap', 'vivareal', 'quintoandar', 'facebook', 'realtor', 'auction', 'manual', 'other'].includes(item.source_key) ? item.source_key : 'other',
        source_url: item.source_url,
        cover_image_url: item.thumbnail || undefined,
        status: 'new',
        address: item.address,
        neighborhood: item.neighborhood,
        city: item.city,
        state: item.state || undefined,
        asking_price: item.asking_price,
        estimated_market_price: item.estimated_market_price,
        area_m2: item.area_m2,
        bedrooms: item.bedrooms,
        bathrooms: item.bathrooms,
        parking_spaces: item.parking_spaces,
      });
      setSavedIds(current => ({ ...current, [item.id]: true }));
    } catch (exception: any) {
      setError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Não foi possível adicionar à carteira.');
    } finally {
      setSavingId(null);
    }
  }

  function toggleCompare(id: string) {
    setCompareIds(current => current.includes(id) ? current.filter(item => item !== id) : [...current, id].slice(0, 4));
  }

  const empty = !conversation?.messages?.length;
  const listings = conversation?.listings ?? [];
  const lastAssistantId = useMemo(() => [...(conversation?.messages ?? [])].reverse().find(item => item.role === 'assistant')?.id ?? null, [conversation?.messages]);
  const contextItems = useMemo(() => describeCriteria(conversation?.criteria), [conversation?.criteria]);
  const canRewrite = ['local', 'openai'].includes(conversation?.provider ?? '');
  const rewriteUnavailableHint = 'Disponível depois que uma resposta for redigida por um modelo neural configurado.';

  return (
    <section className="relative flex flex-col min-h-[72vh] rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
      <header className="flex flex-wrap items-start justify-between gap-4 px-5 sm:px-7 pt-5 pb-3 border-b border-slate-100 dark:border-slate-800">
        <div><p className="text-[11px] font-bold uppercase tracking-widest text-primary-600">Consultor imobiliário</p>
        <h2 className="text-2xl font-black mt-1">Busca em linguagem natural</h2>
        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Carteira da conta e anúncios já coletados. A conversa não inventa imóveis nem afirma cartório, DataZAP ou leilão sem conector.</p></div>
        <div className="relative flex gap-2">
          <button type="button" onClick={() => void toggleMemory()} aria-pressed={memoryEnabled} title="Salva suas preferências de busca neste produto; você pode desligar a qualquer momento." className={`inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-bold ${memoryEnabled ? 'border-cyan-400 bg-cyan-50 text-cyan-800 dark:bg-cyan-950/40 dark:text-cyan-200' : 'hover:bg-slate-50 dark:hover:bg-slate-800'}`}><Database className="size-4" /> Memória {memoryEnabled ? 'ativa' : 'desligada'}</button>
          <button type="button" onClick={() => setShowHistory(value => !value)} className="inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-800"><History className="size-4" /> Histórico</button>
          <button type="button" onClick={() => void newConversation()} className="inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-800"><Plus className="size-4" /> Nova conversa</button>
          {showHistory && <div className="absolute right-0 top-11 z-30 w-72 max-h-80 overflow-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-900">
            {history.length === 0 && <p className="p-3 text-xs text-slate-500">Nenhuma conversa anterior.</p>}
            {history.map(item => <button key={item.id} type="button" onClick={() => void openConversation(item.id)} className="block w-full rounded-xl px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-800">
              <span className="block truncate text-sm font-bold">{item.title || 'Conversa'}</span>
              <span className="mt-1 flex items-center gap-1 text-[11px] text-slate-500"><Clock3 className="size-3" /> {new Date(item.updated_at).toLocaleString('pt-BR')}</span>
            </button>)}
          </div>}
        </div>
      </header>

      <div className="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50/70 px-5 py-2.5 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-300 sm:px-7" aria-label="Contexto atual da conversa">
        <span className="font-black text-slate-800 dark:text-slate-100">Contexto atual</span>
        {conversation?.product_slug && <span className="rounded-full border border-slate-200 bg-white px-2.5 py-1 font-bold dark:border-slate-700 dark:bg-slate-900">{PRODUCT_LABELS[conversation.product_slug] ?? conversation.product_slug}</span>}
        {conversation?.agent?.label && <span className="rounded-full border border-violet-200 bg-violet-50 px-2.5 py-1 font-bold text-violet-700 dark:border-violet-900 dark:bg-violet-950/50 dark:text-violet-200">{conversation.agent.label}</span>}
        {contextItems.length > 0 ? contextItems.map(item => <span key={item} className="rounded-full bg-slate-200/70 px-2.5 py-1 dark:bg-slate-800">{item}</span>) : <span className="text-slate-400">Os filtros da busca aparecerão aqui.</span>}
      </div>

      <div ref={threadRef} onScroll={event => { const node = event.currentTarget; setShowJump(node.scrollHeight - node.scrollTop - node.clientHeight > 160); }} className="relative flex-1 overflow-y-auto px-5 sm:px-7 py-5 space-y-4" aria-live="polite">
        {empty && !showExamples && (
          <div className="max-w-2xl">
            <p className="text-sm text-slate-500">Descreva cidade, bairro, valor e o que é inegociável. Três atalhos, se quiser:</p>
            <div className="mt-3 flex flex-col gap-2">
              {EXAMPLES.map(item => (
                <button key={item} type="button" onClick={() => void send(item)} className="text-left text-sm rounded-2xl border border-slate-200 dark:border-slate-700 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800">
                  {item}
                </button>
              ))}
            </div>
          </div>
        )}
        {!empty && (
          <div className="flex justify-end">
            <button type="button" onClick={() => setShowExamples(value => !value)} className="text-xs font-bold text-primary-600">Ver exemplos</button>
          </div>
        )}
        {showExamples && !empty && EXAMPLES.map(item => (
          <button key={item} type="button" onClick={() => void send(item)} className="block text-left text-xs rounded-2xl border border-slate-200 dark:border-slate-700 px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-800">{item}</button>
        ))}

        {(conversation?.messages ?? []).map(item => (
          <div key={item.id} className={`group max-w-3xl ${item.role === 'user' ? 'ml-auto' : ''}`}>
            <p className={`rounded-2xl px-4 py-3 text-sm leading-6 whitespace-pre-wrap ${item.role === 'user' ? 'bg-slate-100 dark:bg-slate-800' : 'bg-violet-50 dark:bg-violet-950 text-slate-800 dark:text-slate-100'}`}>{item.content}</p>
            {item.role === 'assistant' && <div className="mt-1 flex justify-end">
              <button type="button" onClick={() => void copyMessage(item.id, item.content)} className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-bold text-slate-500 hover:bg-slate-100 hover:text-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-300 dark:hover:bg-slate-800 dark:hover:text-slate-100" aria-label="Copiar resposta">
                {copiedMessageId === item.id ? <Check className="size-3" /> : <Copy className="size-3" />}{copiedMessageId === item.id ? 'Copiada' : 'Copiar'}
              </button>
            </div>}
          </div>
        ))}

        {lastAssistantId && !loading && <div className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
          <div className="flex flex-wrap items-center gap-2">
            <span className="mr-1 inline-flex items-center gap-1 text-xs font-black text-slate-600 dark:text-slate-300"><RefreshCw className="size-3.5" /> Ajustar última resposta</span>
            <button type="button" disabled={!canRewrite} title={!canRewrite ? rewriteUnavailableHint : undefined} onClick={() => rewrite('shorten')} className="rounded-lg border px-2.5 py-1.5 text-xs font-bold disabled:cursor-not-allowed disabled:opacity-45">Encurtar</button>
            <button type="button" disabled={!canRewrite} title={!canRewrite ? rewriteUnavailableHint : undefined} onClick={() => rewrite('expand')} className="rounded-lg border px-2.5 py-1.5 text-xs font-bold disabled:cursor-not-allowed disabled:opacity-45">Expandir</button>
            <select defaultValue="" disabled={!canRewrite} title={!canRewrite ? rewriteUnavailableHint : undefined} onChange={event => { changeTone(event.target.value); event.currentTarget.value = ''; }} aria-label="Alterar tom da última resposta" className="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-bold disabled:cursor-not-allowed disabled:opacity-45 dark:border-slate-600 dark:bg-slate-900">
              <option value="" disabled>Alterar tom</option>
              <option value="direct">Direto</option>
              <option value="professional">Profissional</option>
              <option value="didactic">Didático</option>
            </select>
            <button type="button" disabled={!canRewrite} title={!canRewrite ? rewriteUnavailableHint : undefined} onClick={() => rewrite('new_version')} className="rounded-lg border px-2.5 py-1.5 text-xs font-bold disabled:cursor-not-allowed disabled:opacity-45">Nova versão</button>
          </div>
          {!canRewrite && <p className="mt-2 text-[11px] text-slate-400">{rewriteUnavailableHint}</p>}
        </div>}

        {conversation?.tool_events && conversation.tool_events.length > 0 && <div className="max-w-3xl rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
          <p className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500"><Database className="size-4" /> Etapas verificáveis {conversation.agent?.label ? `· ${conversation.agent.label}` : ''}</p>
          <ul className="mt-3 flex flex-wrap gap-2">{conversation.tool_events.map(event => <li key={event.tool} className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs dark:bg-slate-800"><Check className="size-3 text-emerald-500" />{event.label}{typeof event.count === 'number' ? ` · ${event.count}` : ''}</li>)}</ul>
        </div>}

        {conversation?.citations && conversation.citations.length > 0 && <section className="max-w-3xl rounded-2xl border border-cyan-200 bg-cyan-50/50 p-4 dark:border-cyan-900 dark:bg-cyan-950/20">
          <h3 className="text-sm font-black">Fontes consultadas</h3>
          <ul className="mt-2 space-y-3">{conversation.citations.map(source => <li key={source.id} className="text-xs leading-5 text-slate-600 dark:text-slate-300">
            <p className="font-bold text-slate-900 dark:text-white">{source.source_url ? <a href={source.source_url} target="_blank" rel="noreferrer" className="underline underline-offset-2">{source.title}</a> : source.title}</p>
            <p>{source.excerpt}</p><p className="text-[11px] text-slate-500">{source.source_name}{source.source_version ? ` · ${source.source_version}` : ''}</p>
          </li>)}</ul>
        </section>}

        {conversation?.assistant_message_id && !loading && <div className="flex items-center gap-2 text-xs text-slate-500">
          <span>Esta resposta ajudou?</span>
          <button type="button" onClick={() => void rate('helpful')} aria-label="Resposta útil" className={`rounded-lg border p-2 ${feedback === 'helpful' ? 'bg-emerald-100 text-emerald-700' : ''}`}><ThumbsUp className="size-3.5" /></button>
          <button type="button" onClick={() => void rate('not_helpful')} aria-label="Resposta não útil" className={`rounded-lg border p-2 ${feedback === 'not_helpful' ? 'bg-rose-100 text-rose-700' : ''}`}><ThumbsDown className="size-3.5" /></button>
        </div>}

        {conversation?.recommendation && (
          <div className="rounded-2xl border border-violet-200 dark:border-violet-900 bg-violet-50/70 dark:bg-violet-950/40 px-4 py-3 text-sm">
            <p className="inline-flex items-center gap-2 font-bold text-violet-800 dark:text-violet-200"><Sparkles className="h-4 w-4" /> Destaque</p>
            <p className="mt-1 text-slate-700 dark:text-slate-200">{conversation.recommendation.explanation} {conversation.recommendation.title}.</p>
          </div>
        )}

        {conversation?.no_match && <p className="text-sm text-amber-800 dark:text-amber-200">{conversation.no_match.message}</p>}

        {listings.length > 0 && (
          <div>
            <p className="text-sm text-slate-500 mb-3">Resultados desta rodada · origem no card · 3D é maquete do próprio anúncio.</p>
            <ul className="grid md:grid-cols-2 gap-3">
              {listings.slice(0, 8).map(item => (
                <li key={item.id} className="rounded-2xl border border-slate-200 dark:border-slate-700 p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <h3 className="font-black">{item.title}</h3>
                      <p className="text-xs text-slate-500 mt-1 inline-flex items-center gap-1"><MapPin className="h-3 w-3" />{[item.neighborhood, item.city].filter(Boolean).join(' · ') || 'Local não informado'}</p>
                    </div>
                    <span className="text-[11px] font-bold rounded-full bg-slate-100 dark:bg-slate-800 px-2 py-1">{originLabel(item)}</span>
                  </div>
                  <p className="text-lg font-black mt-3">{money(item.asking_price)}</p>
                  <p className="text-xs text-slate-500 mt-1">
                    {item.area_m2 ?? '—'} m² · {item.bedrooms ?? '—'} quartos · {item.bathrooms ?? '—'} banheiros · {item.parking_spaces ?? '—'} vagas
                  </p>
                  {item.match_score != null && <p className="text-[11px] font-bold text-emerald-700 dark:text-emerald-300 mt-2">Compatibilidade {item.match_score}/100</p>}
                  {item.model_note && <p className="text-[11px] text-slate-400 mt-1">{item.model_note}</p>}
                  <div className="flex flex-wrap gap-2 mt-3">
                    <button type="button" onClick={() => openListing(item, false)} className="rounded-lg border px-3 py-1.5 text-xs font-bold">Ver detalhes</button>
                    <button type="button" onClick={() => openListing(item, true)} className="rounded-lg border px-3 py-1.5 text-xs font-bold inline-flex items-center gap-1"><Box className="h-3 w-3" /> Ver 3D</button>
                    <button type="button" disabled={savingId === item.id || savedIds[item.id] || item.origin === 'inventory_property'} onClick={() => void addToPortfolio(item)} className="rounded-lg bg-primary-600 text-white px-3 py-1.5 text-xs font-bold disabled:opacity-50 inline-flex items-center gap-1">
                      <Briefcase className="h-3 w-3" /> {savedIds[item.id] ? 'Na carteira' : 'Adicionar à carteira'}
                    </button>
                    {listings.length >= 2 && (
                      <button type="button" onClick={() => toggleCompare(item.id)} className={`rounded-lg px-3 py-1.5 text-xs font-bold ${compareIds.includes(item.id) ? 'bg-slate-900 text-white' : 'border'}`}>Comparar</button>
                    )}
                  </div>
                </li>
              ))}
            </ul>
            {compareIds.length >= 2 && (
              <button type="button" onClick={() => void send('Compare as vantagens desses imóveis já listados.')} className="mt-3 text-sm font-bold text-primary-600">Comparar selecionados</button>
            )}
          </div>
        )}
        {loading && <div role="status" className="flex items-center gap-2 text-sm text-slate-500"><Loader2 className="size-4 animate-spin text-primary-500" /> Consultando as fontes disponíveis…</div>}
      </div>

      {showJump && <button type="button" onClick={() => threadRef.current?.scrollTo({ top: threadRef.current.scrollHeight, behavior: 'smooth' })} className="absolute bottom-24 left-1/2 z-10 grid size-9 -translate-x-1/2 place-items-center rounded-full border bg-white shadow-lg dark:bg-slate-900" aria-label="Ir para a mensagem mais recente"><ArrowDown className="size-4" /></button>}

      {error && <p role="alert" className="mx-5 sm:mx-7 mb-2 text-sm text-red-700 dark:text-red-300">{error}</p>}
      {notice && <p role="status" className="mx-5 sm:mx-7 mb-2 text-sm text-slate-600 dark:text-slate-300">{notice}</p>}

      <form onSubmit={onSubmit} className="sticky bottom-0 border-t border-slate-200 dark:border-slate-800 bg-white/95 dark:bg-slate-900/95 backdrop-blur px-4 sm:px-6 py-3">
        <label className="flex items-end gap-2 rounded-2xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-950 px-3 py-2 focus-within:ring-2 focus-within:ring-primary-200">
          <span className="sr-only">Mensagem para a IA</span>
          <textarea
            ref={areaRef}
            value={message}
            onChange={event => setMessage(event.target.value)}
            onKeyDown={onKeyDown}
            maxLength={2000}
            rows={1}
            placeholder="Descreva o imóvel que você procura: cidade, bairro, valor, quartos, vagas ou características."
            className="flex-1 resize-none bg-transparent text-sm leading-6 py-2 max-h-40 focus:outline-none"
          />
          {loading ? <button type="button" onClick={cancelGeneration} className="shrink-0 rounded-xl bg-slate-800 text-white h-10 px-3 inline-flex items-center gap-1 text-xs font-bold" aria-label="Cancelar resposta"><X className="h-4 w-4" /> Cancelar</button> : <button type="submit" disabled={message.trim().length < 3} className="shrink-0 rounded-xl bg-primary-600 text-white h-10 w-10 grid place-items-center disabled:opacity-50" aria-label="Enviar"><Send className="h-4 w-4" /></button>}
        </label>
        <p className="text-[11px] text-slate-400 mt-2 px-1">Enter envia · Shift+Enter quebra linha</p>
      </form>
    </section>
  );
}

function originLabel(item: Listing) {
  if (item.origin === 'inventory_property') return 'Carteira';
  if (item.origin === 'inventory_opportunity') return 'Lead';
  return item.source || 'Portal';
}

function describeCriteria(criteria?: Conversation['criteria']) {
  const required = criteria?.required ?? {};
  const preferred = criteria?.preferred ?? {};
  const items: string[] = [];
  if (typeof required.city === 'string') items.push(required.city);
  if (typeof required.state === 'string') items.push(required.state.toUpperCase());
  if (typeof required.property_type === 'string') items.push(propertyTypeLabel(required.property_type));
  if (typeof required.max_price === 'number') items.push(`Até ${money(required.max_price)}`);
  if (typeof required.min_bedrooms === 'number') items.push(`${required.min_bedrooms}+ quartos`);
  if (typeof required.min_bathrooms === 'number') items.push(`${required.min_bathrooms}+ banheiros`);
  if (typeof required.min_parking === 'number') items.push(`${required.min_parking}+ vagas`);
  if (typeof required.min_area_m2 === 'number') items.push(`${required.min_area_m2}+ m²`);
  if (Array.isArray(preferred.neighborhoods) && preferred.neighborhoods.length > 0) items.push(preferred.neighborhoods.slice(0, 2).join(' ou '));
  if (Array.isArray(preferred.features) && preferred.features.length > 0) items.push(preferred.features.slice(0, 2).join(' · '));
  return [...new Set(items)].slice(0, 7);
}

function propertyTypeLabel(type: string) {
  return ({ apartment: 'Apartamento', house: 'Casa', commercial: 'Comercial', land: 'Terreno', auction: 'Leilão', other: 'Outro' } as Record<string, string>)[type] ?? type;
}
