import { FormEvent, KeyboardEvent, useEffect, useRef, useState } from 'react';
import { Box, Briefcase, Loader2, MapPin, Send, Sparkles } from 'lucide-react';
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
  answer: string;
  intent: string;
  criteria: { required?: Record<string, unknown>; preferred?: Record<string, unknown> };
  listings: Listing[];
  recommendation?: { title: string; explanation: string; listing_id?: string } | null;
  no_match?: { message: string; suggestions?: string[] } | null;
  messages: Array<{ id: number; role: string; content: string }>;
  market?: { status?: string; source?: string; count?: number };
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
  const threadRef = useRef<HTMLDivElement>(null);
  const areaRef = useRef<HTMLTextAreaElement>(null);

  useEffect(() => {
    api.get('/ai/conversations').then(({ data }) => {
      if (data.data?.messages?.length) {
        setConversation({
          session_id: data.data.session_id,
          answer: data.data.messages.at(-1)?.content ?? '',
          intent: 'search',
          criteria: data.data.criteria ?? {},
          listings: data.data.listings ?? [],
          recommendation: data.data.recommendation,
          messages: data.data.messages,
        });
      }
    }).catch(() => undefined);
  }, []);

  useEffect(() => {
    threadRef.current?.scrollTo({ top: threadRef.current.scrollHeight, behavior: 'smooth' });
  }, [conversation?.messages?.length, loading]);

  async function send(text = message) {
    if (text.trim().length < 3) return;
    setLoading(true);
    setError('');
    try {
      const response = await api.post('/ai/conversations', { message: text, session_id: conversation?.session_id });
      setConversation(response.data.data);
      setMessage('');
      setShowExamples(false);
      if (areaRef.current) areaRef.current.style.height = '52px';
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'Não foi possível interpretar a solicitação agora.');
    } finally {
      setLoading(false);
    }
  }

  function onSubmit(event: FormEvent) {
    event.preventDefault();
    void send();
  }

  function onKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      void send();
    }
  }

  function resize(event: FormEvent<HTMLTextAreaElement>) {
    const node = event.currentTarget;
    node.style.height = '52px';
    node.style.height = `${Math.min(node.scrollHeight, 160)}px`;
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

  return (
    <section className="flex flex-col min-h-[72vh] rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
      <header className="px-5 sm:px-7 pt-5 pb-3 border-b border-slate-100 dark:border-slate-800">
        <p className="text-[11px] font-bold uppercase tracking-widest text-primary-600">Consultor imobiliário</p>
        <h2 className="text-2xl font-black mt-1">Busca em linguagem natural</h2>
        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">Carteira da conta e anúncios já coletados. A conversa não inventa imóveis nem afirma cartório, DataZAP ou leilão sem conector.</p>
      </header>

      <div ref={threadRef} className="flex-1 overflow-y-auto px-5 sm:px-7 py-5 space-y-4">
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
          <p key={item.id} className={`max-w-3xl rounded-2xl px-4 py-3 text-sm leading-6 ${item.role === 'user' ? 'bg-slate-100 dark:bg-slate-800 ml-auto' : 'bg-violet-50 dark:bg-violet-950 text-slate-800 dark:text-slate-100'}`}>{item.content}</p>
        ))}

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
      </div>

      {error && <p role="alert" className="mx-5 sm:mx-7 mb-2 text-sm text-red-700 dark:text-red-300">{error}</p>}

      <form onSubmit={onSubmit} className="sticky bottom-0 border-t border-slate-200 dark:border-slate-800 bg-white/95 dark:bg-slate-900/95 backdrop-blur px-4 sm:px-6 py-3">
        <label className="flex items-end gap-2 rounded-2xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-950 px-3 py-2 focus-within:ring-2 focus-within:ring-primary-200">
          <span className="sr-only">Mensagem para a IA</span>
          <textarea
            ref={areaRef}
            value={message}
            onChange={event => setMessage(event.target.value)}
            onInput={resize}
            onKeyDown={onKeyDown}
            maxLength={2000}
            rows={1}
            placeholder="Descreva o imóvel que você procura: cidade, bairro, valor, quartos, vagas ou características."
            className="flex-1 resize-none bg-transparent text-sm leading-6 py-2 max-h-40 focus:outline-none"
          />
          <button type="submit" disabled={loading || message.trim().length < 3} className="shrink-0 rounded-xl bg-primary-600 text-white h-10 w-10 grid place-items-center disabled:opacity-50" aria-label="Enviar">
            {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
          </button>
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
