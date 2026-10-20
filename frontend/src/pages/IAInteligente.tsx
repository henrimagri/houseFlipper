import { useEffect, useMemo, useRef, useState } from 'react';
import { Building2, CheckCircle2, Cloud, ExternalLink, Filter, Globe2, Loader2, MapPin, Search, SlidersHorizontal, Sparkles, Star, Target, TrendingDown } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import api from '@/lib/api';
import ComboField, { normalizeLocation } from '@/components/LocationComboField';
import AiConsultantPanel from '@/components/AiConsultantPanel';
import { readJson, writeJson } from '@/lib/browserStore';
import { safeExternalHttpsUrl } from '@/lib/externalUrl';

const money = (value: number) => value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const percent = (value: number) => `${value.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`;
const SEARCH_LIMIT = 250;
const PAGE_SIZE = 20;
const RADAR_STATE_KEY = 'ia-inteligente:radar-state';

function persistMarketAnalysis(key: string, payload: unknown) {
  return writeJson(key, payload);
}

const propertyTypes = [
  { value: 'apartment', label: 'Apartamento' },
  { value: 'house', label: 'Casa' },
  { value: 'commercial', label: 'Comércio' },
];

const states = [
  ['AC', 'Acre'], ['AL', 'Alagoas'], ['AP', 'Amapá'], ['AM', 'Amazonas'], ['BA', 'Bahia'], ['CE', 'Ceará'], ['DF', 'Distrito Federal'], ['ES', 'Espírito Santo'], ['GO', 'Goiás'], ['MA', 'Maranhão'], ['MT', 'Mato Grosso'], ['MS', 'Mato Grosso do Sul'], ['MG', 'Minas Gerais'], ['PA', 'Pará'], ['PB', 'Paraíba'], ['PR', 'Paraná'], ['PE', 'Pernambuco'], ['PI', 'Piauí'], ['RJ', 'Rio de Janeiro'], ['RN', 'Rio Grande do Norte'], ['RS', 'Rio Grande do Sul'], ['RO', 'Rondônia'], ['RR', 'Roraima'], ['SC', 'Santa Catarina'], ['SP', 'São Paulo'], ['SE', 'Sergipe'], ['TO', 'Tocantins'],
];

const citiesByState: Record<string, string[]> = {
  AC: ['Rio Branco', 'Cruzeiro do Sul', 'Sena Madureira'],
  AL: ['Maceió', 'Arapiraca', 'Rio Largo'],
  AP: ['Macapá', 'Santana', 'Laranjal do Jari'],
  AM: ['Manaus', 'Parintins', 'Itacoatiara'],
  BA: ['Salvador', 'Lauro de Freitas', 'Feira de Santana', 'Camaçari', 'Vitória da Conquista'],
  CE: ['Fortaleza', 'Caucaia', 'Eusébio', 'Maracanaú', 'Juazeiro do Norte'],
  DF: ['Brasília'],
  ES: ['Vitória', 'Vila Velha', 'Serra', 'Cariacica'],
  GO: ['Goiânia', 'Aparecida de Goiânia', 'Anápolis', 'Rio Verde'],
  MA: ['São Luís', 'Imperatriz', 'Timon'],
  MT: ['Cuiabá', 'Várzea Grande', 'Rondonópolis'],
  MS: ['Campo Grande', 'Dourados', 'Três Lagoas'],
  MG: ['Belo Horizonte', 'Contagem', 'Nova Lima', 'Uberlândia', 'Juiz de Fora', 'Betim'],
  PA: ['Belém', 'Ananindeua', 'Santarém', 'Marabá'],
  PB: ['João Pessoa', 'Campina Grande', 'Cabedelo'],
  PR: ['Curitiba', 'Londrina', 'Maringá', 'São José dos Pinhais', 'Foz do Iguaçu'],
  PE: ['Recife', 'Olinda', 'Jaboatão dos Guararapes', 'Caruaru'],
  PI: ['Teresina', 'Parnaíba', 'Picos'],
  RJ: ['Rio de Janeiro', 'Niterói', 'Petrópolis', 'Cabo Frio', 'Nova Iguaçu', 'Duque de Caxias'],
  RN: ['Natal', 'Mossoró', 'Parnamirim'],
  RS: ['Porto Alegre', 'Canoas', 'Caxias do Sul', 'Gramado', 'Novo Hamburgo'],
  RO: ['Porto Velho', 'Ji-Paraná', 'Ariquemes'],
  RR: ['Boa Vista', 'Rorainópolis', 'Caracaraí'],
  SC: ['Florianópolis', 'Balneário Camboriú', 'Joinville', 'Itajaí', 'Blumenau'],
  SP: ['São Paulo', 'Osasco', 'Barueri', 'Itapevi', 'Cotia', 'Santo André', 'São Bernardo do Campo', 'Guarulhos', 'Campinas', 'Santos'],
  SE: ['Aracaju', 'Nossa Senhora do Socorro', 'Lagarto'],
  TO: ['Palmas', 'Araguaína', 'Gurupi'],
};

const neighborhoodsByCity: Record<string, string[]> = {
  'SP|São Paulo': ['Perdizes', 'Moema', 'Pinheiros', 'Vila Mariana', 'Itaim Bibi', 'Tatuapé', 'Santana', 'Brooklin', 'Campo Belo', 'Lapa'],
  'SP|Osasco': ['Centro', 'Continental', 'Umuarama', 'Presidente Altino', 'Jaguaribe', 'Pestana'],
  'SP|Barueri': ['Alphaville', 'Tamboré', 'Bethaville', 'Jardim Tupanci', 'Centro'],
  'SP|Cotia': ['Granja Viana', 'Centro', 'Jardim Ísis'],
  'RJ|Rio de Janeiro': ['Barra da Tijuca', 'Copacabana', 'Ipanema', 'Tijuca', 'Botafogo'],
  'MG|Belo Horizonte': ['Savassi', 'Funcionários', 'Lourdes', 'Buritis', 'Sion'],
  'PR|Curitiba': ['Batel', 'Água Verde', 'Bigorrilho', 'Cabral', 'Centro'],
  'SC|Florianópolis': ['Centro', 'Trindade', 'Ingleses', 'Campeche', 'Jurerê'],
  'RS|Porto Alegre': ['Moinhos de Vento', 'Petrópolis', 'Menino Deus', 'Centro Histórico'],
  'CE|Fortaleza': ['Meireles', 'Aldeota', 'Cocó', 'Papicu', 'Dionísio Torres'],
  'BA|Salvador': ['Pituba', 'Barra', 'Ondina', 'Caminho das Árvores', 'Rio Vermelho'],
  'PE|Recife': ['Boa Viagem', 'Casa Forte', 'Graças', 'Espinheiro'],
  'GO|Goiânia': ['Setor Bueno', 'Marista', 'Oeste', 'Jardim Goiás'],
  'DF|Brasília': ['Asa Sul', 'Asa Norte', 'Sudoeste', 'Águas Claras'],
};

type SearchResult = {
  id: string;
  title: string;
  address: string;
  city: string;
  neighborhood: string;
  state?: string | null;
  source: string;
  source_key: string;
  source_url?: string | null;
  thumbnail?: string | null;
  asking_price: number;
  estimated_market_price: number;
  discount_percentage: number;
  area_m2?: number | null;
  bedrooms?: number | null;
  bathrooms?: number | null;
  parking_spaces?: number | null;
  price_per_m2?: number | null;
  market_estimate_method?: string;
  market_reference_price_per_m2?: number | null;
  market_reference_count?: number | null;
  market_reference_sources?: string[];
  confidence_percentage?: number | null;
  confidence_basis?: string;
  valuation_version?: string;
  transaction_reference_window_months?: number;
  confirmed_transactions_count?: number | null;
  score: number;
  signal: 'Boa oportunidade' | 'Negociar' | 'Atenção';
};

type ScraperDiagnostic = {
  source: string;
  status?: number;
  found?: number;
  message?: string;
  state?: 'connected' | 'not_configured' | 'local_fallback' | 'unavailable';
};

const portalSources = [
  { name: 'OLX', mode: 'Coleta local' },
  { name: 'ZAP Imóveis', mode: 'Apify' },
  { name: 'VivaReal', mode: 'Apify' },
  { name: 'ImovelWeb', mode: 'Apify' },
  { name: 'Chaves na Mão', mode: 'Coleta local' },
  { name: 'Casa Mineira', mode: 'Coleta local' },
  { name: 'Loft', mode: 'Apify' },
  { name: 'QuintoAndar', mode: 'Apify' },
  { name: 'Mercado Livre', mode: 'API pública' },
  { name: 'Facebook Marketplace', mode: 'Apify' },
];

type RadarStateSnapshot = {
  state: string;
  city: string;
  neighborhood: string;
  propertyType: string;
  maxPrice: string;
  minDiscount: string;
  page: number;
  searched: boolean;
  results: SearchResult[];
  diagnostics: ScraperDiagnostic[];
  savedIds: Record<string, boolean>;
  showRefine: boolean;
  refine: {
    minPrice: string;
    maxPrice: string;
    minArea: string;
    maxArea: string;
    bedrooms: string;
    bathrooms: string;
    parking: string;
    minScore: string;
    source: string;
    onlyWithPhoto: boolean;
  };
};

export default function IAInteligente() {
  const navigate = useNavigate();
  const [state, setState] = useState('SP');
  const [city, setCity] = useState('São Paulo');
  const [neighborhood, setNeighborhood] = useState('');
  const [propertyType, setPropertyType] = useState('apartment');
  const [maxPrice, setMaxPrice] = useState('');
  const [minDiscount, setMinDiscount] = useState('');
  const [cityOptions, setCityOptions] = useState<string[]>(citiesByState.SP);
  const [citiesLoading, setCitiesLoading] = useState(false);
  const [page, setPage] = useState(1);
  const [searched, setSearched] = useState(false);
  const [loading, setLoading] = useState(false);
  const [results, setResults] = useState<SearchResult[]>([]);
  const [diagnostics, setDiagnostics] = useState<ScraperDiagnostic[]>([]);
  const [error, setError] = useState('');
  const [savedIds, setSavedIds] = useState<Record<string, boolean>>({});
  const [savingId, setSavingId] = useState<string | null>(null);
  const searchSequence = useRef(0);
  const searchAbort = useRef<AbortController | null>(null);
  const [showRefine, setShowRefine] = useState(false);
  const [refine, setRefine] = useState({
    minPrice: '',
    maxPrice: '',
    minArea: '',
    maxArea: '',
    bedrooms: '',
    bathrooms: '',
    parking: '',
    minScore: '',
    source: '',
    onlyWithPhoto: false,
  });
  const skipRadarWrite = useRef(true);
  const [workspace, setWorkspace] = useState<'chat' | 'radar'>('chat');

  useEffect(() => {
    const snapshot = readRadarState();
    if (!snapshot) return;

    setState(snapshot.state || 'SP');
    setCity(snapshot.city || 'São Paulo');
    setNeighborhood(snapshot.neighborhood || '');
    setPropertyType(snapshot.propertyType || 'apartment');
    setMaxPrice(snapshot.maxPrice || '');
    setMinDiscount(snapshot.minDiscount || '');
    setPage(Math.max(1, Number(snapshot.page || 1)));
    setSearched(Boolean(snapshot.searched));
    setResults(Array.isArray(snapshot.results) ? snapshot.results : []);
    setDiagnostics(Array.isArray(snapshot.diagnostics) ? snapshot.diagnostics : []);
    setSavedIds(snapshot.savedIds || {});
    setShowRefine(Boolean(snapshot.showRefine));
    if (snapshot.searched) setWorkspace('radar');
    setRefine({
      minPrice: snapshot.refine?.minPrice || '',
      maxPrice: snapshot.refine?.maxPrice || '',
      minArea: snapshot.refine?.minArea || '',
      maxArea: snapshot.refine?.maxArea || '',
      bedrooms: snapshot.refine?.bedrooms || '',
      bathrooms: snapshot.refine?.bathrooms || '',
      parking: snapshot.refine?.parking || '',
      minScore: snapshot.refine?.minScore || '',
      source: snapshot.refine?.source || '',
      onlyWithPhoto: Boolean(snapshot.refine?.onlyWithPhoto),
    });
  }, []);

  useEffect(() => {
    if (skipRadarWrite.current) {
      skipRadarWrite.current = false;
      return;
    }

    writeRadarState({
      state,
      city,
      neighborhood,
      propertyType,
      maxPrice,
      minDiscount,
      page,
      searched,
      results,
      diagnostics,
      savedIds,
      showRefine,
      refine,
    });
  }, [state, city, neighborhood, propertyType, maxPrice, minDiscount, page, searched, results, diagnostics, savedIds, showRefine, refine]);

  const filteredResults = useMemo(() => {
    return results.filter(item => {
      const askingPrice = Number(item.asking_price || 0);
      const area = Number(item.area_m2 || 0);
      const bedrooms = Number(item.bedrooms || 0);
      const bathrooms = Number(item.bathrooms || 0);
      const parking = Number(item.parking_spaces || 0);
      const score = Number(item.score || 0);
      const minPrice = currencyDraftToNumber(refine.minPrice);
      const maxRefinePrice = currencyDraftToNumber(refine.maxPrice);
      const minArea = Number(refine.minArea || 0);
      const maxArea = Number(refine.maxArea || 0);
      const minBedrooms = Number(refine.bedrooms || 0);
      const minBathrooms = Number(refine.bathrooms || 0);
      const minParking = Number(refine.parking || 0);
      const minScore = Number(refine.minScore || 0);

      if (minPrice > 0 && askingPrice < minPrice) return false;
      if (maxRefinePrice > 0 && askingPrice > maxRefinePrice) return false;
      if (minArea > 0 && area < minArea) return false;
      if (maxArea > 0 && area > maxArea) return false;
      if (minBedrooms > 0 && bedrooms < minBedrooms) return false;
      if (minBathrooms > 0 && bathrooms < minBathrooms) return false;
      if (minParking > 0 && parking < minParking) return false;
      if (minScore > 0 && score < minScore) return false;
      if (refine.source && item.source !== refine.source) return false;
      if (refine.onlyWithPhoto && !item.thumbnail) return false;

      return true;
    });
  }, [results, refine]);

  const sourceOptions = useMemo(() => Array.from(new Set(results.map(item => item.source).filter(Boolean))).sort(), [results]);

  const stats = useMemo(() => {
    const total = filteredResults.length;
    const best = filteredResults.length ? Math.max(...filteredResults.map(item => item.score)) : 0;
    const avgDiscount = filteredResults.length ? filteredResults.reduce((sum, item) => sum + Number(item.discount_percentage || 0), 0) / filteredResults.length : 0;
    const avgPrice = filteredResults.length ? filteredResults.reduce((sum, item) => sum + Number(item.asking_price || 0), 0) / filteredResults.length : 0;

    return { total, best, avgDiscount, avgPrice };
  }, [filteredResults]);

  const totalPages = Math.max(1, Math.ceil(filteredResults.length / PAGE_SIZE));
  const paginatedResults = useMemo(() => {
    const start = (page - 1) * PAGE_SIZE;

    return filteredResults.slice(start, start + PAGE_SIZE);
  }, [page, filteredResults]);

  useEffect(() => {
    let cancelled = false;
    setCitiesLoading(true);
    fetch(`https://servicodados.ibge.gov.br/api/v1/localidades/estados/${state}/municipios?orderBy=nome`)
      .then(response => response.ok ? response.json() : Promise.reject())
      .then((items: Array<{ nome: string }>) => {
        if (cancelled) return;
        const nextCities = items.map(item => item.nome).filter(Boolean);
        setCityOptions(nextCities.length ? nextCities : (citiesByState[state] ?? []));
      })
      .catch(() => {
        if (!cancelled) setCityOptions(citiesByState[state] ?? []);
      })
      .finally(() => {
        if (!cancelled) setCitiesLoading(false);
      });

    return () => { cancelled = true; };
  }, [state]);

  async function handleSearch() {
    const request = ++searchSequence.current;
    searchAbort.current?.abort();
    const controller = new AbortController();
    searchAbort.current = controller;
    setLoading(true);
    setSearched(true);
    setError('');
    try {
      const response = await api.get('/ai-property-search', {
        signal: controller.signal,
        params: {
          city,
          neighborhood,
          state,
          property_type: propertyType,
          max_price: Number(maxPrice.replace(/\D/g, '') || 0) / 100 || undefined,
          min_discount: Number(minDiscount || 0) || undefined,
          limit: SEARCH_LIMIT,
        },
      });
      if (request !== searchSequence.current) return;
      setResults(response.data.data ?? []);
      setPage(1);
      setDiagnostics(response.data.source?.diagnostics ?? []);
    } catch (exception: any) {
      if (controller.signal.aborted || request !== searchSequence.current) return;
      setResults([]);
      setDiagnostics(exception.response?.data?.diagnostics ?? []);
      const message = exception.response?.data?.message ?? 'Não foi possível buscar anúncios reais agora.';
      const details = exception.response?.data?.details;
      setError(details ? `${message} ${details}` : message);
    } finally {
      if (request === searchSequence.current) {
        setLoading(false);
        if (searchAbort.current === controller) searchAbort.current = null;
      }
    }
  }

  useEffect(() => () => searchAbort.current?.abort(), []);

  const neighborhoodOptions = Object.entries(neighborhoodsByCity).find(([key]) => normalizeLocation(key) === normalizeLocation(`${state}|${city}`))?.[1] ?? [];
  const selectedTypeLabel = propertyTypes.find(item => item.value === propertyType)?.label.toLowerCase() ?? 'imóvel';

  async function saveLead(item: SearchResult) {
    setSavingId(item.id);
    setError('');
    try {
      await api.post('/opportunities', {
        title: item.title,
        description: `Lead encontrado pela IA Inteligente via ${item.source}. Score ${item.score}/100. ${item.signal}.`,
        property_type: propertyType,
        source: item.source_key || 'other',
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
        notes: `Preço/m²: ${item.price_per_m2 ? money(Number(item.price_per_m2)) : 'não informado'}. Referência mercado/m²: ${item.market_reference_price_per_m2 ? money(Number(item.market_reference_price_per_m2)) : 'não informado'}. Método: ${marketMethodLabel(item.market_estimate_method)}. Fonte da estimativa: ${marketSourceLabel(item)}. Desconto estimado: ${percent(Number(item.discount_percentage || 0))}.`,
      });
      setSavedIds(current => ({ ...current, [item.id]: true }));
    } catch (exception: any) {
      setError(Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Não foi possível salvar o lead.');
    } finally {
      setSavingId(null);
    }
  }

  function openMarketAnalysis(item: SearchResult, openInNewTab = false) {
    const payload = {
      selected: { ...item, property_type: propertyType },
      comparables: filteredResults,
      collectedAt: new Date().toISOString(),
      propertyTypeLabel: propertyTypes.find(type => type.value === propertyType)?.label ?? 'Imóvel',
      diagnostics,
    };
    const storageKey = `market-analysis:${item.id}`;
    if (!persistMarketAnalysis(storageKey, payload)) {
      setError('O navegador não permitiu salvar a análise temporária. Libere o armazenamento do site e tente novamente.');
      return;
    }

    const url = `/ia-inteligente/analise/${encodeURIComponent(item.id)}`;
    if (openInNewTab) {
      window.open(url, '_blank', 'noopener,noreferrer');

      return;
    }

    navigate(url, { state: payload });
  }

  return <div className="space-y-5">
    <header className="px-1">
      <p className="text-[11px] font-bold uppercase tracking-widest text-primary-600 inline-flex items-center gap-2"><Sparkles className="w-4 h-4" /> Assistente de busca</p>
      <h1 className="text-3xl font-black mt-2">IA Inteligente</h1>
      <p className="text-slate-500 dark:text-slate-400 mt-1 max-w-3xl">Converse em português ou use o radar por filtros. A coleta é o scraper e a carteira da conta; nenhum anúncio é inventado.</p>
      <div className="flex gap-2 mt-4">
        <button type="button" onClick={() => setWorkspace('chat')} className={`rounded-full px-4 py-2 text-sm font-bold ${workspace === 'chat' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'border border-slate-300 dark:border-slate-700'}`}>Conversar</button>
        <button type="button" onClick={() => setWorkspace('radar')} className={`rounded-full px-4 py-2 text-sm font-bold ${workspace === 'radar' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'border border-slate-300 dark:border-slate-700'}`}>Radar por filtros</button>
      </div>
    </header>

    {workspace === 'chat' && <AiConsultantPanel />}
    {workspace === 'radar' && <>

    <section className="bg-white dark:bg-slate-900 border rounded-3xl p-5 shadow-xs">
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
          <h2 className="text-xl font-black">Onde você quer procurar?</h2>
          <p className="text-sm text-gray-500 dark:text-slate-400 mt-1">Use estado, cidade, bairro, tipo e filtros para montar o radar de imóveis. As avaliações usam comparáveis e regras explicáveis; a qualidade da base não é uma probabilidade de acerto.</p>
        </div>
        <span className="bg-violet-50 dark:bg-violet-950 text-primary-700 dark:text-primary-300 px-3 py-2 rounded-full text-xs font-bold">IA de prospecção</span>
      </div>

      <div className="grid md:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-[180px_1fr_1fr_150px_160px_150px_auto] gap-3 mt-5 items-end">
        <label className="text-sm block">
          <span>Estado</span>
          <select className="input mt-1" value={state} onChange={event => {
            const nextState = event.target.value;
            setState(nextState);
            setCity(citiesByState[nextState]?.[0] ?? '');
            setNeighborhood('');
            setPage(1);
          }}>
            {states.map(([uf, name]) => <option key={uf} value={uf}>{name}</option>)}
          </select>
        </label>
        <ComboField label="Cidade" placeholder={citiesLoading ? 'Carregando cidades...' : 'Ex: São Paulo'} value={city} options={cityOptions} onChange={(value) => { setCity(value); setNeighborhood(''); setPage(1); }} icon={MapPin} />
        <ComboField label="Bairro" placeholder="Ex: Perdizes" value={neighborhood} options={neighborhoodOptions} onChange={setNeighborhood} icon={Building2} />
        <label className="text-sm block">
          <span>Tipo</span>
          <select className="input mt-1" value={propertyType} onChange={event => { setPropertyType(event.target.value); setPage(1); }}>
            {propertyTypes.map(item => <option key={item.value} value={item.value}>{item.label}</option>)}
          </select>
        </label>
        <TextField label="Preço máximo" placeholder="R$ 0,00" value={maxPrice} onChange={value => setMaxPrice(formatCurrencyDraft(value))} />
        <TextField label="Desconto mínimo" placeholder="Opcional" value={minDiscount} onChange={setMinDiscount} suffix="%" />
        <button type="button" disabled={loading} onClick={handleSearch} className="bg-primary-600 text-white px-5 py-3 rounded-xl font-bold flex items-center justify-center gap-2 disabled:opacity-60">
          {loading ? <Loader2 className="w-4 h-4 animate-spin" /> : <Search className="w-4 h-4" />}
          Buscar
        </button>
      </div>
    </section>

    {error && <div className="bg-red-50 dark:bg-red-950 border border-red-200 text-red-700 dark:text-red-300 rounded-2xl p-4">{error}</div>}

    <ProviderNetwork diagnostics={diagnostics} searched={searched} />

    {searched && <section className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <Kpi title="Anúncios ranqueados" value={stats.total.toString()} detail={`até ${SEARCH_LIMIT} imóveis por busca`} icon={Target} tone="violet" />
      <Kpi title="Melhor score" value={`${stats.best}/100`} detail="maior oportunidade da busca" icon={Star} tone="emerald" />
      <Kpi title="Desconto médio" value={percent(stats.avgDiscount)} detail="preço pedido vs mercado" icon={TrendingDown} tone="amber" />
      <Kpi title="Preço médio" value={money(stats.avgPrice)} detail="média do preço pedido filtrado" icon={Building2} tone="blue" />
    </section>}

    {searched ? <section className="bg-white dark:bg-slate-900 border rounded-3xl overflow-hidden shadow-xs">
      <div className="p-5 border-b flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
          <h2 className="text-xl font-black">{capitalize(selectedTypeLabel)}s encontrados</h2>
          <p className="text-sm text-gray-500 dark:text-slate-400 mt-1">
            Resultados reais retornados pela fonte conectada e normalizados para virar oportunidade. Página {page} de {totalPages}, com {PAGE_SIZE} por página.
          </p>
        </div>
        <button type="button" onClick={() => setShowRefine(current => !current)} className="border px-4 py-2.5 rounded-xl font-bold text-sm flex items-center gap-2 text-slate-700 dark:text-slate-200"><Filter className="w-4 h-4" />Refinar busca</button>
      </div>

      {showRefine && <RefinePanel
        refine={refine}
        sourceOptions={sourceOptions}
        onChange={(next) => {
          setRefine(next);
          setPage(1);
        }}
        onClear={() => {
          setRefine({ minPrice: '', maxPrice: '', minArea: '', maxArea: '', bedrooms: '', bathrooms: '', parking: '', minScore: '', source: '', onlyWithPhoto: false });
          setPage(1);
        }}
      />}

      <div className="grid xl:grid-cols-[1fr_360px] gap-0">
        <div className="divide-y">
          {loading ? <div className="p-12 text-center text-gray-500 dark:text-slate-400"><Loader2 className="w-8 h-8 animate-spin mx-auto text-primary-600 dark:text-primary-400" /><p className="mt-3">Consultando anúncios reais da região...</p></div> : paginatedResults.map(item => <ListingRow key={item.id} item={item} onSave={saveLead} onOpenAnalysis={openMarketAnalysis} saving={savingId === item.id} saved={!!savedIds[item.id]} />)}
          {!loading && filteredResults.length === 0 && <div className="p-12 text-center text-gray-500 dark:text-slate-400">
            <Search className="w-10 h-10 mx-auto text-gray-300" />
            <h3 className="font-black text-gray-700 dark:text-slate-200 mt-3">{results.length ? 'Nenhum anúncio passou nos filtros' : 'Nenhum anúncio retornado agora'}</h3>
            <p className="mt-2 max-w-xl mx-auto">{results.length ? 'Afrouxe preço, área, quartos, score ou fonte para ampliar os resultados.' : 'Tente buscar só pela cidade, remova preço máximo/desconto mínimo ou escolha outra região. Algumas fontes públicas podem bloquear consultas em determinados momentos.'}</p>
          </div>}
          {!loading && filteredResults.length > PAGE_SIZE && <Pagination page={page} totalPages={totalPages} total={filteredResults.length} onPageChange={setPage} />}
        </div>
        <aside className="border-l bg-slate-50 dark:bg-slate-950 p-5 hidden xl:block">
          <div className="sticky top-24">
            <div className="w-12 h-12 bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 rounded-2xl grid place-items-center"><SlidersHorizontal className="w-6 h-6" /></div>
            <h3 className="font-black mt-4">Como a IA prioriza</h3>
            <div className="space-y-3 mt-4 text-sm">
              <Rule label="Preço abaixo do mercado" />
              <Rule label="Desconto por m²" />
              <Rule label="Liquidez do bairro" />
              <Rule label="Potencial de reforma" />
              <Rule label="Margem estimada para flip" />
            </div>
            <div className="bg-white dark:bg-slate-900 border rounded-2xl p-4 mt-5 text-xs text-gray-500 dark:text-slate-400 leading-5">
              As fontes Apify configuradas são consultadas em paralelo. O coletor local e a API pública do Mercado Livre continuam como alternativas quando uma fonte não responde.
            </div>
            {diagnostics.length > 0 && <div className="bg-white dark:bg-slate-900 border rounded-2xl p-4 mt-4 text-xs">
              <div className="font-bold text-gray-800 dark:text-slate-100 mb-2">Diagnóstico da coleta</div>
              <div className="space-y-2">
                {diagnostics.map((item, index) => <div key={`${item.source}-${index}`} className="flex justify-between gap-3">
                  <span className="text-gray-600 dark:text-slate-300">{item.source}</span>
                  <span className={Number(item.found || 0) > 0 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-gray-400'}>{item.found ?? item.message ?? item.status ?? '-'}</span>
                </div>)}
              </div>
            </div>}
          </div>
        </aside>
      </div>
    </section> : <EmptyState />}
    </>}
  </div>;
}

function ProviderNetwork({ diagnostics, searched }: { diagnostics: ScraperDiagnostic[]; searched: boolean }) {
  const entries = portalSources.map(source => {
    const matches = diagnostics.filter(item => item.source.toLocaleLowerCase('pt-BR') === source.name.toLocaleLowerCase('pt-BR'));
    const count = Math.max(0, ...matches.map(item => Number(item.found || 0)));
    const failed = matches.some(item => Number(item.status || 0) >= 400 || (item.status === 0 && Boolean(item.message)));
    const state = matches.find(item => item.state === 'connected' && Number(item.found || 0) > 0)?.state
      ?? matches.find(item => item.state)?.state;
    const label = !searched ? 'Aguardando primeira busca'
      : count > 0 ? `${count} ${count === 1 ? 'anúncio' : 'anúncios'}`
      : failed ? 'Portal bloqueou esta busca'
      : state === 'not_configured' ? 'Informe o token Apify'
      : state === 'local_fallback' ? 'Coletor local disponível'
      : state === 'unavailable' ? 'Indisponível nesta busca'
      : 'Nenhum anúncio encontrado';

    return { ...source, count, state, label };
  });

  return <section className="rounded-3xl border bg-white p-5 shadow-xs dark:bg-slate-900">
    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <p className="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-widest text-primary-600"><Cloud className="h-4 w-4" /> Rede de fontes</p>
        <h2 className="mt-1 text-lg font-black">Portais ligados ao radar</h2>
      </div>
      <p className="max-w-xl text-xs text-slate-500 dark:text-slate-400">Resultados reais, deduplicados e filtrados pela localização informada. Credenciais ficam somente no servidor.</p>
    </div>
    <div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
      {entries.map(entry => <div key={entry.name} className="flex items-center gap-3 rounded-2xl border border-slate-200 p-3 dark:border-slate-700">
        <span className={`grid h-9 w-9 shrink-0 place-items-center rounded-xl ${entry.count > 0 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300'}`}><Globe2 className="h-4 w-4" /></span>
        <div className="min-w-0">
          <p className="truncate text-sm font-bold">{entry.name}</p>
          <p className="truncate text-[11px] text-slate-500 dark:text-slate-400">{entry.mode} · {entry.label}</p>
        </div>
      </div>)}
    </div>
  </section>;
}

function ListingRow({ item, onSave, onOpenAnalysis, saving, saved }: { item: SearchResult; onSave: (item: SearchResult) => void; onOpenAnalysis: (item: SearchResult, openInNewTab?: boolean) => void; saving: boolean; saved: boolean }) {
  const itemDiscount = Number(item.discount_percentage || 0);
  const sourceUrl = safeExternalHttpsUrl(item.source_url);
  const scoreColor = item.score >= 80 ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950' : item.score >= 65 ? 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950' : 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950';

  return <div className="p-5 hover:bg-slate-50 dark:hover:bg-slate-800 transition cursor-pointer" onClick={() => onOpenAnalysis(item)}>
    <div className="grid lg:grid-cols-[1fr_160px_160px_120px_180px] gap-4 items-center">
      <div className="flex gap-4 min-w-0">
        <SafeListingImage src={item.thumbnail} alt={item.title} />
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="font-black truncate">{item.title}</h3>
            <span className="text-[11px] bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300 px-2 py-1 rounded-full font-bold">{item.source}</span>
          </div>
          <p className="text-sm text-gray-500 dark:text-slate-400 mt-1 truncate">{item.address} · {item.neighborhood}, {item.city}</p>
          <p className="text-xs text-gray-400 mt-2">
            {item.area_m2 ?? '-'} m² · {item.bedrooms ?? '-'} quartos · {item.bathrooms ?? '-'} banheiros · {item.parking_spaces ?? '-'} vaga{item.parking_spaces === 1 ? '' : 's'}
          </p>
        </div>
      </div>
      <Small label="Preço pedido" value={money(Number(item.asking_price || 0))} />
      <Small label="Valor de mercado estimado" value={money(Number(item.estimated_market_price || 0))} hint={marketBaseLabel(item)} />
      <Small label="Desconto" value={percent(itemDiscount)} accent={itemDiscount >= 18 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'} />
      <div className="flex items-center justify-between gap-3">
        <div className={`w-16 h-16 rounded-2xl grid place-items-center font-black ${scoreColor}`}>{item.score}</div>
        <div className="flex flex-col gap-2">
          <button type="button" onClick={(event) => { event.stopPropagation(); onOpenAnalysis(item, true); }} className="border border-primary-200 bg-primary-50 dark:bg-primary-950 text-primary-700 dark:text-primary-300 px-3 py-2 rounded-xl font-bold text-sm">Ver análise / 3D</button>
          <button type="button" disabled={saving || saved} onClick={(event) => { event.stopPropagation(); onSave(item); }} className="bg-primary-600 text-white px-3 py-2 rounded-xl font-bold text-sm disabled:opacity-60">{saving ? 'Salvando...' : saved ? 'Lead salvo' : 'Salvar lead'}</button>
          {sourceUrl && <a onClick={(event) => event.stopPropagation()} href={sourceUrl} target="_blank" rel="noopener noreferrer" className="border px-3 py-2 rounded-xl font-bold text-sm flex items-center gap-1">Abrir <ExternalLink className="w-3 h-3" /></a>}
        </div>
      </div>
    </div>
  </div>;
}

function SafeListingImage({ src, alt }: { src?: string | null; alt: string }) {
  const [failed, setFailed] = useState(false);

  if (!src || failed) {
    return <div className="w-20 h-16 rounded-2xl bg-linear-to-br from-slate-200 dark:from-slate-700 to-slate-100 dark:to-slate-800 grid place-items-center shrink-0 text-slate-400">
      <Building2 className="w-8 h-8" />
    </div>;
  }

  return <img src={src} alt={alt} className="w-20 h-16 rounded-2xl object-cover shrink-0 bg-slate-100 dark:bg-slate-800" referrerPolicy="no-referrer" onError={() => setFailed(true)} />;
}

function RefinePanel({ refine, sourceOptions, onChange, onClear }: {
  refine: {
    minPrice: string;
    maxPrice: string;
    minArea: string;
    maxArea: string;
    bedrooms: string;
    bathrooms: string;
    parking: string;
    minScore: string;
    source: string;
    onlyWithPhoto: boolean;
  };
  sourceOptions: string[];
  onChange: (value: any) => void;
  onClear: () => void;
}) {
  const update = (key: string, value: string | boolean) => onChange({ ...refine, [key]: value });

  return <div className="border-b bg-slate-50 dark:bg-slate-950 p-5">
    <div className="flex items-center justify-between gap-3 mb-4">
      <div>
        <h3 className="font-black">Refinar resultados</h3>
        <p className="text-sm text-gray-500 dark:text-slate-400">Filtre a lista já carregada para recalcular score médio, preço médio e paginação.</p>
      </div>
      <button type="button" onClick={onClear} className="text-sm font-bold text-primary-600 dark:text-primary-400">Limpar filtros</button>
    </div>

    <div className="grid md:grid-cols-2 xl:grid-cols-5 gap-3 items-end">
      <TextField label="Preço mínimo" placeholder="R$ 0,00" value={refine.minPrice} onChange={value => update('minPrice', formatCurrencyDraft(value))} />
      <TextField label="Preço máximo" placeholder="R$ 0,00" value={refine.maxPrice} onChange={value => update('maxPrice', formatCurrencyDraft(value))} />
      <TextField label="Área mínima" placeholder="Ex: 60" value={refine.minArea} onChange={value => update('minArea', numericDraft(value))} suffix="m²" />
      <TextField label="Área máxima" placeholder="Ex: 120" value={refine.maxArea} onChange={value => update('maxArea', numericDraft(value))} suffix="m²" />
      <label className="text-sm block">
        <span>Fonte</span>
        <select className="input mt-1" value={refine.source} onChange={event => update('source', event.target.value)}>
          <option value="">Todas</option>
          {sourceOptions.map(source => <option key={source} value={source}>{source}</option>)}
        </select>
      </label>
      <TextField label="Quartos mín." placeholder="0" value={refine.bedrooms} onChange={value => update('bedrooms', integerDraft(value))} />
      <TextField label="Banheiros mín." placeholder="0" value={refine.bathrooms} onChange={value => update('bathrooms', integerDraft(value))} />
      <TextField label="Vagas mín." placeholder="0" value={refine.parking} onChange={value => update('parking', integerDraft(value))} />
      <TextField label="Score mínimo" placeholder="Ex: 70" value={refine.minScore} onChange={value => update('minScore', integerDraft(value, 100))} />
      <label className="flex items-center gap-3 bg-white dark:bg-slate-900 border rounded-xl px-4 py-3 text-sm font-bold">
        <input type="checkbox" checked={refine.onlyWithPhoto} onChange={event => update('onlyWithPhoto', event.target.checked)} />
        Só com foto
      </label>
    </div>
  </div>;
}

function TextField({ label, value, onChange, placeholder, icon: Icon, suffix }: { label: string; value: string; onChange: (value: string) => void; placeholder?: string; icon?: any; suffix?: string }) {
  return <label className="text-sm block">
    <span>{label}</span>
    <div className="relative mt-1">
      {Icon && <Icon className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />}
      <input className={`input ${Icon ? 'pl-9' : ''} ${suffix ? 'pr-8' : ''}`} value={value} placeholder={placeholder} onChange={event => onChange(event.target.value)} />
      {suffix && <span className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">{suffix}</span>}
    </div>
  </label>;
}

function Kpi({ title, value, detail, icon: Icon, tone }: { title: string; value: string; detail: string; icon: any; tone: 'violet' | 'emerald' | 'amber' | 'blue' }) {
  const colors = {
    violet: 'bg-violet-50 dark:bg-violet-950 text-violet-700 dark:text-violet-300',
    emerald: 'bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300',
    amber: 'bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-300',
    blue: 'bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300',
  };

  return <div className="bg-white dark:bg-slate-900 border rounded-2xl p-5 shadow-xs flex items-start gap-4">
    <div className={`w-12 h-12 rounded-xl grid place-items-center ${colors[tone]}`}><Icon className="w-6 h-6" /></div>
    <div><span className="text-xs text-gray-500 dark:text-slate-400">{title}</span><strong className="block text-2xl mt-1">{value}</strong><p className="text-xs text-gray-400 mt-1">{detail}</p></div>
  </div>;
}

function Small({ label, value, accent = '', hint }: { label: string; value: string; accent?: string; hint?: string }) {
  return <div><span className="text-xs text-gray-500 dark:text-slate-400 block">{label}</span><strong className={`block mt-1 ${accent}`}>{value}</strong>{hint && <span className="text-[11px] text-gray-400 block mt-0.5">{hint}</span>}</div>;
}

function Pagination({ page, totalPages, total, onPageChange }: { page: number; totalPages: number; total: number; onPageChange: (page: number) => void }) {
  const pages = Array.from({ length: totalPages }, (_, index) => index + 1)
    .filter(item => item === 1 || item === totalPages || Math.abs(item - page) <= 2);

  return <div className="p-5 border-t flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <span className="text-sm text-gray-500 dark:text-slate-400">
      Mostrando {((page - 1) * PAGE_SIZE) + 1} a {Math.min(page * PAGE_SIZE, total)} de {total} anúncios
    </span>
    <div className="flex items-center gap-2">
      <button type="button" className="border px-3 py-2 rounded-xl text-sm font-bold disabled:opacity-40" disabled={page === 1} onClick={() => onPageChange(page - 1)}>Anterior</button>
      {pages.map((item, index) => {
        const previous = pages[index - 1];

        return <div key={item} className="flex items-center gap-2">
          {previous && item - previous > 1 && <span className="text-gray-400">...</span>}
          <button type="button" onClick={() => onPageChange(item)} className={`w-10 h-10 rounded-xl text-sm font-black ${item === page ? 'bg-primary-600 text-white' : 'border text-slate-700 dark:text-slate-200'}`}>{item}</button>
        </div>;
      })}
      <button type="button" className="border px-3 py-2 rounded-xl text-sm font-bold disabled:opacity-40" disabled={page === totalPages} onClick={() => onPageChange(page + 1)}>Próxima</button>
    </div>
  </div>;
}

function capitalize(value: string) {
  return value ? value.charAt(0).toUpperCase() + value.slice(1) : value;
}

function marketMethodLabel(method?: string) {
  if (method === 'itbi_same_building_median_m2') return 'Vendas reais no mesmo prédio';
  if (method === 'itbi_radius_500m_median_m2') return 'Vendas reais em até 500m';
  if (method === 'itbi_radius_900m_median_m2') return 'Vendas reais em até 900m';
  if (method === 'itbi_neighborhood_median_m2') return 'Vendas reais no bairro';
  if (method === 'itbi_city_median_m2') return 'Vendas reais na cidade';
  if (method === 'comparables_neighborhood_median_m2') return 'Mediana R$/m² do bairro';
  if (method === 'comparables_region_median_m2') return 'Mediana R$/m² da região';
  if (method === 'fallback_heuristic') return 'Fallback por regra';
  return 'Comparáveis da busca';
}

function marketSourceLabel(item: SearchResult) {
  if (item.market_estimate_method === 'fallback_heuristic') {
    return 'Sem comparáveis suficientes; regra interna temporária';
  }

  const method = marketMethodLabel(item.market_estimate_method);
  const count = Number(item.market_reference_count || 0);
  const realSales = Number(item.confirmed_transactions_count || 0);
  const activeListings = Math.max(0, count - realSales);
  const sources = item.market_reference_sources?.length ? item.market_reference_sources.join(', ') : item.source;
  const pricePerM2 = item.market_reference_price_per_m2 ? ` · ${money(Number(item.market_reference_price_per_m2))}/m²` : '';

  return `${method}${realSales ? ` · ${realSales} vendas reais` : ''}${activeListings ? ` · ${activeListings} anúncios` : ''} · ${sources}${pricePerM2}`;
}

function marketBaseLabel(item: SearchResult) {
  const count = Number(item.market_reference_count || 0);
  const realSales = Number(item.confirmed_transactions_count || 0);
  const activeListings = Math.max(0, count - realSales);
  const quality = item.confidence_basis === 'heuristic_evidence_quality' && item.confidence_percentage != null
    ? `Qualidade da base ${Number(item.confidence_percentage)}/100 (heurística)` : 'Qualidade da base não avaliada nesta versão';

  if (count <= 0 || item.market_estimate_method === 'fallback_heuristic') {
    return `Baseado somente em regra interna temporária · ${quality} · Sem validação por comparáveis`;
  }

  const period = realSales > 0 && item.transaction_reference_window_months ? ` nos últimos ${item.transaction_reference_window_months} meses` : '';
  return `Baseado em ${realSales} vendas reais${period} e ${activeListings} anúncios comparáveis · ${quality}`;
}

function Rule({ label }: { label: string }) {
  return <div className="flex items-center gap-2"><CheckCircle2 className="w-4 h-4 text-emerald-600 dark:text-emerald-400" /><span>{label}</span></div>;
}

function EmptyState() {
  return <section className="bg-white dark:bg-slate-900 border border-dashed rounded-3xl p-10 text-center">
    <div className="w-16 h-16 bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 rounded-2xl grid place-items-center mx-auto"><Search className="w-8 h-8" /></div>
    <h2 className="text-2xl font-black mt-5">Comece buscando uma região</h2>
    <p className="text-gray-500 dark:text-slate-400 mt-2 max-w-2xl mx-auto">Digite uma cidade ou bairro para o InvestFlip montar o radar de anúncios e apontar quais imóveis parecem bons candidatos para flip.</p>
  </section>;
}

function formatCurrencyDraft(value: string) {
  const digits = value.replace(/\D/g, '');
  if (!digits) return '';

  return (Number(digits) / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function currencyDraftToNumber(value: string) {
  const digits = value.replace(/\D/g, '');

  return digits ? Number(digits) / 100 : 0;
}

function readRadarState(): RadarStateSnapshot | null {
  return readJson<RadarStateSnapshot>(RADAR_STATE_KEY);
}

function writeRadarState(snapshot: RadarStateSnapshot) {
  writeJson(RADAR_STATE_KEY, snapshot);
}

function integerDraft(value: string, max?: number) {
  const digits = value.replace(/\D/g, '');
  if (!digits) return '';

  const number = Number(digits);

  return String(max ? Math.min(max, number) : number);
}

function numericDraft(value: string) {
  return value
    .replace(/[^\d.,]/g, '')
    .replace(',', '.')
    .replace(/(\..*)\./g, '$1');
}
