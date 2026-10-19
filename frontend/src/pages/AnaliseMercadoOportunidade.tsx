import { ArrowLeft, Box, Building2, CheckCircle2, ExternalLink, Info, Landmark, MapPin, PlugZap, ShieldAlert, Sparkles, Star, Tag } from 'lucide-react';
import * as React from 'react';
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom';
import ListingLayoutViewer from '@/components/ListingLayoutViewer';
import api from '@/lib/api';
import { writeJson } from '@/lib/browserStore';
import { safeExternalHttpsUrl } from '@/lib/externalUrl';

type MarketItem = {
  id: string;
  title: string;
  address?: string;
  city?: string;
  neighborhood?: string;
  state?: string | null;
  source?: string;
  source_url?: string | null;
  thumbnail?: string | null;
  asking_price: number;
  estimated_market_price?: number;
  discount_percentage?: number;
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
  confirmed_transactions_count?: number | null;
  property_type?: 'apartment' | 'house' | 'commercial' | string;
  score?: number;
};

type MarketContext = {
  selected: MarketItem;
  comparables: MarketItem[];
  collectedAt: string;
  propertyTypeLabel: string;
  diagnostics?: Array<{ source: string; found?: number; status?: number; message?: string }>;
};

type ProviderStatus = {
  key: string;
  label: string;
  status: 'connected' | 'unconfigured' | 'no_dataset' | 'error';
  message: string;
  count: number;
};

type ConfirmedTransaction = {
  source: string;
  title?: string;
  address?: string | null;
  street_name?: string | null;
  street_number?: string | null;
  property_sql?: string | null;
  registry_office?: string | null;
  neighborhood?: string | null;
  city?: string | null;
  transaction_date?: string | null;
  price: number;
  area_m2?: number | null;
  price_m2?: number | null;
  similarity_percentage?: number | null;
  confidence_percentage?: number | null;
  source_url?: string | null;
};

type LocationPlace = {
  name: string;
  type?: string;
  distance_m?: number | null;
  rating?: number | null;
};

type LocationGroup = {
  key: string;
  label: string;
  count: number;
  places: LocationPlace[];
};

type LocationContext = {
  key: string;
  source: string;
  status: 'connected' | 'unavailable';
  message: string;
  google_status?: { status: string; message: string } | null;
  from_cache?: boolean;
  query?: string | null;
  radius_m: number;
  lat?: number | null;
  lng?: number | null;
  groups: LocationGroup[];
};

type EconomicIndicator = {
  key: string;
  label: string;
  unit: string;
  value: number | null;
  date?: string | null;
  origin?: string | null;
};

type EconomicIndicators = {
  status: string;
  source: string;
  message: string;
  items: EconomicIndicator[];
};

const money = (value: number) => value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const percent = (value: number) => `${value.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`;

function marketAnalysisSubject(item: MarketItem) {
  return {
    title: item.title,
    address: item.address,
    neighborhood: item.neighborhood,
    city: item.city,
    state: item.state,
    area_m2: item.area_m2,
    bedrooms: item.bedrooms,
    bathrooms: item.bathrooms,
    parking_spaces: item.parking_spaces,
    asking_price: item.asking_price,
    property_type: item.property_type,
  };
}

export default function AnaliseMercadoOportunidade() {
  const { id } = useParams();
  const navigate = useNavigate();
  const location = useLocation();
  const context = React.useMemo(() => {
    if (id && isMarketContext(location.state, id)) {
      writeJson(`market-analysis:${id}`, location.state);
      return location.state as MarketContext;
    }
    return readContext(id);
  }, [id, location.state]);
  const selectedItem = context?.selected ?? null;
  const [externalMarket, setExternalMarket] = React.useState<{ providers: ProviderStatus[]; transactions: ConfirmedTransaction[]; economic_indicators?: EconomicIndicators | null } | null>(null);
  const [locationContext, setLocationContext] = React.useState<LocationContext | null | undefined>(undefined);
  const [externalLoading, setExternalLoading] = React.useState(Boolean(selectedItem));

  React.useEffect(() => {
    if (!selectedItem) {
      setExternalMarket(null);
      setExternalLoading(false);
      return;
    }

    let active = true;
    setExternalLoading(true);
    api.post('/property-market-analysis', { selected: marketAnalysisSubject(selectedItem) })
      .then(response => {
        if (active) setExternalMarket({
          providers: response.data.providers ?? [],
          transactions: response.data.transactions ?? [],
          economic_indicators: response.data.economic_indicators ?? null,
        });
      })
      .catch(() => {
        if (active) setExternalMarket({ providers: [], transactions: [], economic_indicators: null });
      })
      .finally(() => {
        if (active) setExternalLoading(false);
      });

    return () => { active = false; };
  }, [selectedItem?.id]);

  React.useEffect(() => {
    if (!selectedItem) {
      setLocationContext(undefined);
      return;
    }

    let active = true;
    setLocationContext(undefined);
    api.post('/neighborhood-context', { selected: selectedItem })
      .then(response => {
        if (active) setLocationContext(response.data ?? null);
      })
      .catch(() => {
        if (active) setLocationContext(null);
      });

    return () => { active = false; };
  }, [selectedItem?.id]);

  if (!context) {
    return <div className="bg-white dark:bg-slate-900 border rounded-3xl p-10 text-center">
      <ShieldAlert className="w-12 h-12 mx-auto text-amber-500" />
      <h1 className="text-2xl font-black mt-4">Análise não encontrada</h1>
      <p className="text-gray-500 dark:text-slate-400 mt-2">Volte para a IA Inteligente e abra novamente a oportunidade. O radar e esta análise ficam salvos neste navegador.</p>
      <Link to="/ia-inteligente" className="inline-flex mt-5 bg-primary-600 text-white px-5 py-3 rounded-xl font-bold">Voltar para IA Inteligente</Link>
    </div>;
  }

  const item = context.selected;
  const sourceUrl = safeExternalHttpsUrl(item.source_url);

  const listedComparables = context.comparables
    .filter(comparable => comparable.id !== item.id && Number(comparable.asking_price || 0) > 0)
    .slice(0, 14);
  const confirmedTransactions = externalMarket?.transactions ?? [];
  const priceM2Values = listedComparables
    .map(comparable => comparablePricePerM2(comparable))
    .filter(isValidPricePerM2)
    .sort((a, b) => a - b);
  const medianComparablePriceM2 = median(priceM2Values);
  const storedReferencePriceM2 = Number(item.market_reference_price_per_m2 || 0);
  const transactionPriceM2Values = confirmedTransactions
    .map(transaction => Number(transaction.price_m2 || 0))
    .filter(isValidPricePerM2)
    .sort((a, b) => a - b);
  const transactionMedianPriceM2 = median(transactionPriceM2Values);
  const referencePriceM2 = transactionMedianPriceM2 || referencePriceFrom(storedReferencePriceM2, priceM2Values, medianComparablePriceM2);
  const recalculatedEstimate = referencePriceM2 > 0 && Number(item.area_m2 || 0) > 0 ? referencePriceM2 * Number(item.area_m2 || 0) : 0;
  const estimatedValue = recalculatedEstimate || Number(item.estimated_market_price || 0);
  const confidence = Number(item.confidence_percentage || confidenceFrom(item, listedComparables.length, confirmedTransactions.length));
  const rangeFactor = confidence >= 85 ? 0.06 : confidence >= 70 ? 0.09 : 0.13;
  const rangeMin = estimatedValue > 0 ? estimatedValue * (1 - rangeFactor) : 0;
  const rangeMax = estimatedValue > 0 ? estimatedValue * (1 + rangeFactor) : 0;
  const estimatedDiscount = estimatedValue > 0 ? Math.max(0, (estimatedValue - Number(item.asking_price || 0)) / estimatedValue * 100) : 0;
  const safeScore = Math.min(Number(item.score || 0), confidence < 50 ? 74 : 100);
  const connectedSources = Array.from(new Set(listedComparables.map(comparable => comparable.source).filter(Boolean))) as string[];
  const providerStatuses = externalMarket?.providers ?? [];
  const saoPauloCatalogWasConsulted = isSaoPauloItem(item) && providerStatuses.some(provider => provider.status === 'no_dataset' && ['itbi', 'prefeitura'].includes(provider.key));
  const marketValueLabel = confirmedTransactions.length ? 'Valor de mercado estimado' : 'Mercado comparável estimado';
  const marketDataType = confirmedTransactions.length ? 'Vendas + anúncios' : saoPauloCatalogWasConsulted ? 'Anúncios + catálogo SP' : 'Estimativa';
  const buildingTransactions = confirmedTransactions
    .filter(transaction => sameBuilding(transaction, item))
    .slice(0, 5);
  const regionTransactions = confirmedTransactions
    .filter(transaction => sameNeighborhood(transaction, item) && !sameBuilding(transaction, item))
    .slice(0, 5);
  const usedCalculationSteps = [
    confirmedTransactions.length > 0 ? { title: 'Vendas reais ITBI/cartório', value: `${confirmedTransactions.length} venda(s) encontradas` } : null,
    buildingTransactions.length > 0 ? { title: 'Mesmo prédio/condomínio', value: `${buildingTransactions.length} venda(s)` } : null,
    listedComparables.length > 0 ? { title: 'Anúncios ativos semelhantes', value: `${listedComparables.length} anúncio(s) usados como apoio` } : null,
    referencePriceM2 > 0 ? { title: 'Ajuste por m²', value: `${money(referencePriceM2)}/m² aplicado à área do imóvel` } : null,
    item.parking_spaces != null ? { title: 'Ajuste por vagas', value: `${item.parking_spaces} vaga(s) considerada(s)` } : null,
    item.bedrooms != null ? { title: 'Ajuste por quartos', value: `${item.bedrooms} quarto(s) considerado(s)` } : null,
    confirmedTransactions.length + listedComparables.length > 0 ? { title: 'Liquidez do bairro', value: `Base atual: ${confirmedTransactions.length + listedComparables.length} referência(s)` } : null,
    estimatedValue > 0 ? { title: 'Valor estimado', value: money(estimatedValue) } : null,
    estimatedValue > 0 ? { title: 'Intervalo de confiança', value: `${money(rangeMin)} até ${money(rangeMax)}` } : null,
    { title: 'Transparência', value: confirmedTransactions.length ? 'Estimativa baseada em vendas reais + apoio de anúncios' : 'Estimativa baseada em anúncios ativos' },
  ].filter(Boolean) as Array<{ title: string; value: string }>;

  return <div className="space-y-5">
    <button type="button" onClick={() => navigate('/ia-inteligente')} className="text-primary-600 dark:text-primary-400 font-bold inline-flex items-center gap-2"><ArrowLeft className="w-4 h-4" />Voltar ao radar</button>

    <section className="bg-white dark:bg-slate-900 border rounded-3xl shadow-xs overflow-hidden">
      <div className="p-5 grid xl:grid-cols-[1fr_170px] gap-5 items-center">
        <div className="flex gap-5 min-w-0">
          <SafeImage src={item.thumbnail} alt={item.title} className="w-40 h-28 rounded-2xl object-cover bg-slate-100 dark:bg-slate-800" fallbackClassName="w-40 h-28 rounded-2xl" />
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="text-2xl font-black truncate">{item.title}</h1>
              <Badge>{item.source || 'Fonte não informada'}</Badge>
            </div>
            <p className="text-sm text-gray-500 dark:text-slate-400 mt-1">{[item.neighborhood, item.city, item.state].filter(Boolean).join(' · ')}</p>
            <p className="text-sm text-gray-500 dark:text-slate-400 mt-3">
              {item.area_m2 ?? '-'} m² · {item.bedrooms ?? '-'} quartos · {item.bathrooms ?? '-'} banheiros · {item.parking_spaces ?? '-'} vaga{item.parking_spaces === 1 ? '' : 's'}
            </p>
            <div className="flex flex-wrap gap-3 mt-3 text-xs text-gray-500 dark:text-slate-400">
              <span className="inline-flex items-center gap-1"><MapPin className="w-3 h-3" />{item.address || 'Endereço não informado'}</span>
              <span>Coleta: {new Date(context.collectedAt).toLocaleString('pt-BR')}</span>
              {sourceUrl && <a href={sourceUrl} target="_blank" rel="noopener noreferrer" className="text-primary-600 dark:text-primary-400 font-bold inline-flex items-center gap-1">Abrir anúncio <ExternalLink className="w-3 h-3" /></a>}
            </div>
          </div>
        </div>
        <div className="bg-slate-950 text-white rounded-2xl p-5 text-center">
          <div className="text-xs uppercase tracking-widest text-violet-200">Opportunity Score</div>
          <div className="text-4xl font-black mt-2">{safeScore}</div>
          <div className="text-sm text-emerald-300 mt-1">{safeScore >= 80 ? 'Bom potencial' : 'Requer validação'}</div>
        </div>
      </div>

      <div className="border-t grid md:grid-cols-4 xl:grid-cols-7 divide-y md:divide-y-0 md:divide-x">
        <Metric label="Preço anunciado" value={money(Number(item.asking_price || 0))} />
        <Metric label={marketValueLabel} value={money(estimatedValue)} />
        <Metric label="Faixa provável" value={`${money(rangeMin)} - ${money(rangeMax)}`} />
        <Metric label="Preço médio por m²" value={referencePriceM2 ? `${money(referencePriceM2)}/m²` : 'Sem base'} />
        <Metric label="Desconto estimado" value={percent(estimatedDiscount)} accent="text-emerald-600 dark:text-emerald-400" />
        <Metric label="Confiança" value={`${confidence}%`} accent={confidence >= 70 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'} />
        <Metric label="Tipo de dado" value={marketDataType} />
      </div>
    </section>

    {!externalLoading && <MarketDataNotice
      item={item}
      providers={providerStatuses}
      confirmedTransactions={confirmedTransactions.length}
      listedComparables={listedComparables.length}
    />}

    <NeighborhoodContextSection context={locationContext} />

    <EconomicIndicatorsSection indicators={externalMarket?.economic_indicators} />

    <section id="section-3d" className="bg-white dark:bg-slate-900 border rounded-3xl p-5 shadow-xs overflow-hidden">
      <div className="flex flex-col lg:flex-row lg:items-end justify-between gap-3">
        <div>
          <div className="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-primary-600 dark:text-primary-400"><Box className="w-4 h-4" /> Modelo 3D deste imóvel</div>
          <h2 className="font-black text-lg mt-1">{item.title}</h2>
          <p className="text-sm text-gray-500 dark:text-slate-400 mt-1">Maquete deste anúncio, com fachada e ambientes nomeados. Não é fotogrametria nem tour virtual. O selo no visualizador diz se o volume é paramétrico ou só ilustrativo.</p>
        </div>
        <div className="text-xs text-gray-500 dark:text-slate-400">{item.area_m2 ?? '—'} m² · {item.bedrooms ?? '—'} quarto(s) · {item.bathrooms ?? '—'} banheiro(s)</div>
      </div>
      <ListingLayoutViewer
        title={item.title}
        areaM2={item.area_m2}
        bedrooms={item.bedrooms}
        bathrooms={item.bathrooms}
        parkingSpaces={item.parking_spaces}
        propertyType={item.property_type}
        notes={item.title}
        className="mt-4 w-full h-[520px] rounded-2xl bg-slate-200"
      />
    </section>

    <nav className="bg-white dark:bg-slate-900 border rounded-2xl p-2 flex gap-2 overflow-x-auto text-sm font-bold">
      {[
        ['Visão geral', 'section-0'],
        ['Modelo 3D', 'section-3d'],
        ['Últimas vendas', 'section-1'],
        ['Análise de preço/m²', 'section-3'],
        ['Fontes e metodologia', 'section-4'],
      ].map(([tab, section], index) => <a key={tab} href={`#${section}`} className={`px-4 py-2 rounded-xl whitespace-nowrap ${index === 0 ? 'bg-primary-600 text-white' : 'text-slate-600 dark:text-slate-300'}`}>{tab}</a>)}
    </nav>

    <section id="section-0" className="bg-white dark:bg-slate-900 border rounded-3xl p-5 shadow-xs">
      <h2 className="font-black text-lg">Como chegamos ao valor de mercado</h2>
      <div className="grid lg:grid-cols-5 gap-3 mt-4">
        <PipelineStep icon={Tag} title="Preço anunciado" value={money(Number(item.asking_price || 0))} source={item.source || 'Portal'} />
        <PipelineStep icon={Sparkles} title="Modelo de avaliação" value="InvestFlip AVM" source="Estimativa do modelo" />
        <PipelineStep icon={Landmark} title="Vendas reais" value={externalLoading ? '...' : `${confirmedTransactions.length}`} source={confirmedTransactions.length ? 'ITBI/Cartório/Prefeitura' : 'Sem transação retornada'} />
        <PipelineStep icon={Building2} title="Anúncios comparáveis" value={`${listedComparables.length}`} source={sourceList(item)} />
        <PipelineStep icon={Star} title={marketValueLabel} value={money(estimatedValue)} source={`${confidence}% confiança`} />
      </div>
      {confidence < 60 && <div className="mt-4 bg-amber-50 dark:bg-amber-950 border border-amber-200 text-amber-800 dark:text-amber-300 rounded-2xl p-4 text-sm">
        Alto desconto aparente, mas com confiança limitada nos dados. A análise usa anúncios comparáveis; confirme vendas reais, condomínio e endereço antes de decidir.
      </div>}
    </section>

    <section id="section-1" className="grid xl:grid-cols-2 gap-5">
      <TransactionSection
        title="Últimas vendas no prédio ou condomínio"
        locationLabel={[item.neighborhood, item.city].filter(Boolean).join(' · ')}
        transactions={buildingTransactions}
        loading={externalLoading}
        providers={externalMarket?.providers ?? []}
        message="Ainda não encontramos venda pública com o mesmo endereço/prédio deste anúncio. A busca regional usa ITBI em dados abertos, planilha importada e, se houver, endpoint municipal."
      />
      <TransactionSection
        title="Últimas vendas na região"
        locationLabel={[item.neighborhood, item.city].filter(Boolean).join(' · ')}
        transactions={regionTransactions}
        loading={externalLoading}
        providers={externalMarket?.providers ?? []}
        message="Nenhuma venda pública no bairro/cidade nesta coleta. Foram consultados ITBI em dados abertos (CKAN), planilha importada e BCB. Os anúncios comparáveis do radar aparecem abaixo como ofertas, não como escritura."
      />
    </section>

    {listedComparables.length > 0 && <section className="bg-white dark:bg-slate-900 border rounded-3xl p-5 shadow-xs">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h2 className="font-black text-lg">Ofertas recentes na região</h2>
          <p className="text-sm text-gray-500 dark:text-slate-400 mt-1">Anúncios do radar, não transações de cartório. Usados como apoio de preço/m².</p>
        </div>
        <Badge>{listedComparables.length} oferta(s)</Badge>
      </div>
      <div className="mt-4 grid md:grid-cols-2 xl:grid-cols-3 gap-3">
        {listedComparables.slice(0, 6).map(comparable => <div key={comparable.id} className="border rounded-2xl p-4 bg-slate-50 dark:bg-slate-950">
          <div className="font-bold truncate">{comparable.title}</div>
          <div className="text-xs text-gray-500 dark:text-slate-400 mt-1">{[comparable.neighborhood, comparable.city].filter(Boolean).join(' · ')}</div>
          <div className="mt-3 flex justify-between text-sm">
            <strong>{money(Number(comparable.asking_price || 0))}</strong>
            <span>{comparable.area_m2 ? `${comparable.area_m2} m²` : '—'}</span>
          </div>
        </div>)}
      </div>
    </section>}

    <section id="section-3" className="grid xl:grid-cols-[1fr_320px] gap-5">
      <div className="bg-white dark:bg-slate-900 border rounded-3xl p-5 shadow-xs">
        <h2 className="font-black text-lg">Análise de preço por m²</h2>
        <div className="grid md:grid-cols-4 gap-3 mt-4">
          <MetricCard label="Preço/m² do anúncio" value={comparablePricePerM2(item) ? `${money(comparablePricePerM2(item))}/m²` : 'Não informado'} />
          <MetricCard label="Preço/m² comparável" value={referencePriceM2 ? `${money(referencePriceM2)}/m²` : 'Sem base'} />
          <MetricCard label="Menor comparável" value={priceM2Values[0] ? `${money(priceM2Values[0])}/m²` : 'Sem base'} />
          <MetricCard label="Maior comparável" value={priceM2Values.length ? `${money(priceM2Values[priceM2Values.length - 1])}/m²` : 'Sem base'} />
        </div>
        <PriceM2Distribution values={priceM2Values} reference={referencePriceM2} />
      </div>
      <div className="bg-white dark:bg-slate-900 border rounded-3xl p-5 shadow-xs">
        <h2 className="font-black text-lg">Resumo da análise</h2>
        <SummaryRow label="Vendas reais utilizadas" value={`${confirmedTransactions.length}`} />
        <SummaryRow label="Anúncios comparáveis" value={`${listedComparables.length}`} />
        <SummaryRow label="Fonte principal" value={sourceList(item)} />
        <SummaryRow label="Confiança da análise" value={`${confidence}%`} />
        <SummaryRow label="Algoritmo" value="InvestFlip AVM v0.1" />
      </div>
    </section>

    <section id="section-4" className="bg-white dark:bg-slate-900 border rounded-3xl p-5 shadow-xs">
      <h2 className="font-black text-lg">Fontes e metodologia</h2>
      <div className="grid lg:grid-cols-3 gap-5 mt-4 text-sm">
        <div>
          <h3 className="font-black mb-2">Fontes de dados</h3>
          <ul className="space-y-2 text-gray-600 dark:text-slate-300">
            <li>• Portais imobiliários coletados no radar: {connectedSources.length ? connectedSources.join(', ') : sourceList(item)}</li>
            <li>• Vendas reais: {providerSummary(externalMarket?.providers ?? [])}</li>
            <li>• Modelo AVM: InvestFlip</li>
          </ul>
        </div>
        <div>
          <h3 className="font-black mb-2">Critérios de comparação</h3>
          <ul className="space-y-2 text-gray-600 dark:text-slate-300">
            <li>• Mesmo tipo de imóvel: {context.propertyTypeLabel}</li>
            <li>• Mesma cidade/bairro quando disponível</li>
            <li>• Faixa de quartos semelhante</li>
            <li>• Remoção de outliers por mediana/IQR</li>
          </ul>
        </div>
        <div className="bg-violet-50 dark:bg-violet-950 border border-violet-100 rounded-2xl p-4">
          <div className="font-black text-primary-700 dark:text-primary-300 flex items-center gap-2"><Info className="w-4 h-4" />Transparência</div>
          <p className="text-gray-600 dark:text-slate-300 mt-2">Esta análise não substitui avaliação profissional. Quando não houver transações reais retornadas pelos providers, os valores continuam como estimativas baseadas em anúncios comparáveis e devem ser validados antes da compra.</p>
        </div>
      </div>
    </section>

    {!externalLoading && usedCalculationSteps.length > 0 && <section className="bg-white dark:bg-slate-900 border rounded-3xl p-5 shadow-xs">
      <h2 className="font-black text-lg">Explicação completa do cálculo</h2>
      <p className="text-sm text-gray-500 dark:text-slate-400 mt-1">O InvestFlip segue uma hierarquia: vendas reais primeiro; anúncios ativos entram apenas como apoio quando a base transacional ainda é limitada.</p>
      <div className="grid md:grid-cols-2 xl:grid-cols-3 gap-3 mt-4">
        {usedCalculationSteps.map((step, index) => <CalculationStep key={step.title} index={index + 1} title={step.title} value={step.value} />)}
      </div>
    </section>}

  </div>;
}

function readContext(id?: string): MarketContext | null {
  if (!id) return null;
  const key = `market-analysis:${id}`;

  for (const storage of [sessionStorage, localStorage]) {
    try {
      const raw = storage.getItem(key);
      if (!raw) continue;
      const parsed: unknown = JSON.parse(raw);
      if (isMarketContext(parsed, id)) return parsed;
    } catch {
      // A corrupt or unavailable storage must not hide a valid fallback copy.
    }
  }

  return null;
}

function isMarketContext(value: unknown, expectedId: string): value is MarketContext {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return false;
  const context = value as Partial<MarketContext>;
  if (!isMarketItem(context.selected) || context.selected.id !== expectedId) return false;
  if (!Array.isArray(context.comparables) || context.comparables.length > 250 || !context.comparables.every(isMarketItem)) return false;
  if (typeof context.collectedAt !== 'string' || context.collectedAt.length > 40) return false;
  if (typeof context.propertyTypeLabel !== 'string' || context.propertyTypeLabel.length > 80) return false;
  if (context.diagnostics !== undefined && (!Array.isArray(context.diagnostics) || context.diagnostics.length > 25)) return false;
  if (context.diagnostics?.some(item => !item || typeof item !== 'object' || typeof item.source !== 'string' || item.source.length > 120)) return false;

  return true;
}

function isMarketItem(value: unknown): value is MarketItem {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return false;
  const item = value as Partial<MarketItem>;

  return typeof item.id === 'string' && item.id.length > 0 && item.id.length <= 500
    && typeof item.title === 'string' && item.title.length <= 500
    && typeof item.asking_price === 'number' && Number.isFinite(item.asking_price);
}

function Metric({ label, value, accent = '' }: { label: string; value: string; accent?: string }) {
  return <div className="p-4"><span className="text-xs text-gray-500 dark:text-slate-400">{label}</span><strong className={`block mt-1 ${accent}`}>{value}</strong></div>;
}

function MetricCard({ label, value }: { label: string; value: string }) {
  return <div className="bg-slate-50 dark:bg-slate-950 rounded-2xl p-4"><span className="text-xs text-gray-500 dark:text-slate-400">{label}</span><strong className="block mt-1">{value}</strong></div>;
}

function Badge({ children }: { children: React.ReactNode }) {
  return <span className="bg-violet-50 dark:bg-violet-950 text-primary-700 dark:text-primary-300 px-2 py-1 rounded-full text-xs font-bold">{children}</span>;
}

function PipelineStep({ icon: Icon, title, value, source }: { icon: any; title: string; value: string; source: string }) {
  return <div className="border rounded-2xl p-4 bg-slate-50 dark:bg-slate-950">
    <Icon className="w-5 h-5 text-primary-600 dark:text-primary-400" />
    <span className="text-xs text-gray-500 dark:text-slate-400 block mt-3">{title}</span>
    <strong className="block mt-1">{value}</strong>
    <span className="text-[11px] text-gray-400 block mt-1">{source}</span>
  </div>;
}

function EconomicIndicatorsSection({ indicators }: { indicators?: EconomicIndicators | null }) {
  if (!indicators?.items?.length) return null;

  return <section className="bg-white dark:bg-slate-900 border rounded-3xl p-5 shadow-xs">
    <div className="flex items-center justify-between gap-3">
      <div>
        <h2 className="font-black text-lg">Indicadores públicos</h2>
        <p className="text-sm text-gray-500 dark:text-slate-400 mt-1">{indicators.message}</p>
      </div>
      <Badge>{indicators.source}</Badge>
    </div>
    <div className="grid sm:grid-cols-2 lg:grid-cols-5 gap-3 mt-4">
      {indicators.items.map(item => <div key={item.key} className="rounded-2xl border bg-slate-50 dark:bg-slate-950 p-4">
        <div className="text-xs font-bold uppercase tracking-wide text-slate-500">{item.label}</div>
        <div className="text-xl font-black mt-1">{item.value == null ? '—' : item.value.toLocaleString('pt-BR', { maximumFractionDigits: 2 })}</div>
        <div className="text-xs text-gray-500 dark:text-slate-400 mt-1">{item.unit}{item.date ? ` · ${item.date}` : ''}</div>
      </div>)}
    </div>
  </section>;
}

function NeighborhoodContextSection({ context }: { context?: LocationContext | null }) {
  if (!context) {
    return <section className="bg-white dark:bg-slate-900 border rounded-3xl p-5 shadow-xs">
      <h2 className="font-black text-lg">Entorno do bairro</h2>
      <p className="text-sm text-gray-500 dark:text-slate-400 mt-1">Consultando dados de localização...</p>
    </section>;
  }

  if (context.status !== 'connected') {
    return <section className="bg-white dark:bg-slate-900 border rounded-3xl p-5 shadow-xs">
      <div className="flex items-start gap-3">
        <div className="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400 grid place-items-center"><MapPin className="w-5 h-5" /></div>
        <div>
          <h2 className="font-black text-lg">Entorno do bairro</h2>
          <p className="text-sm text-gray-500 dark:text-slate-400 mt-1">{context.message}</p>
          <p className="text-xs text-gray-400 mt-2">O InvestFlip tenta Google Maps quando disponível e OpenStreetMap como fallback. Se a consulta oscilar, tente recarregar ou buscar apenas por bairro e cidade.</p>
        </div>
      </div>
    </section>;
  }

  const groups = context.groups ?? [];
  const total = groups.reduce((sum, group) => sum + Number(group.count || 0), 0);

  return <section className="bg-white dark:bg-slate-900 border rounded-3xl p-5 shadow-xs">
    <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
      <div>
        <h2 className="font-black text-lg">Entorno do bairro</h2>
        <p className="text-sm text-gray-500 dark:text-slate-400 mt-1">
          {total} ponto(s) próximos encontrados em até {Math.round(context.radius_m / 1000 * 10) / 10} km. Fonte: {context.source}{context.from_cache ? ' · última coleta válida' : ''}.
        </p>
      </div>
      <Badge>{context.from_cache ? 'Cache do bairro' : context.lat && context.lng ? `${context.lat.toFixed(4)}, ${context.lng.toFixed(4)}` : 'Coordenada localizada'}</Badge>
    </div>
    <div className="grid md:grid-cols-2 xl:grid-cols-5 gap-4 mt-4">
      {groups.map(group => <div key={group.key} className="border rounded-2xl p-4 bg-slate-50 dark:bg-slate-950">
        <div className="flex items-center justify-between gap-3">
          <div>
            <div className="font-black">{group.label}</div>
            <div className="text-xs text-gray-500 dark:text-slate-400">{group.count} encontrados</div>
          </div>
          <div className="w-10 h-10 rounded-xl bg-violet-100 dark:bg-violet-900 text-primary-700 dark:text-primary-300 grid place-items-center font-black">{group.count}</div>
        </div>
        <div className="mt-3 space-y-2">
          {(group.places || []).slice(0, 3).map(place => <div key={`${group.key}-${place.name}-${place.distance_m}`} className="text-xs border-t pt-2">
            <div className="font-bold line-clamp-1">{place.name}</div>
            <div className="text-gray-500 dark:text-slate-400">{place.type || 'ponto'} · {place.distance_m != null ? `${place.distance_m} m` : 'distância n/d'}{place.rating ? ` · nota ${place.rating}` : ''}</div>
          </div>)}
          {(!group.places || group.places.length === 0) && <div className="text-xs text-gray-400 border-t pt-2">Nenhum ponto detalhado retornado.</div>}
        </div>
      </div>)}
    </div>
    <p className="text-xs text-gray-400 mt-4">Esses dados ajudam a avaliar liquidez e atratividade da região, mas não substituem vendas reais de cartório/ITBI.</p>
    {context.from_cache && <p className="text-xs text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-950 border border-blue-100 rounded-xl p-3 mt-3">
      O OpenStreetMap/Overpass oscilou agora, então usamos a última coleta válida salva para este bairro.
    </p>}
  </section>;
}

function TransactionSection({ title, locationLabel, message, transactions, loading, providers }: { title: string; locationLabel: string; message: string; transactions: ConfirmedTransaction[]; loading: boolean; providers: ProviderStatus[] }) {
  return <div className="bg-white dark:bg-slate-900 border rounded-3xl p-5 shadow-xs">
    <div className="flex items-center justify-between gap-3">
      <h2 className="font-black text-lg">{title}</h2>
      <Badge>{loading ? 'Consultando...' : `${transactions.length} vendas`}</Badge>
    </div>
    {transactions.length > 0 ? <div className="mt-4 overflow-hidden border rounded-2xl">
      <table className="w-full text-sm">
        <thead className="bg-slate-50 dark:bg-slate-950 text-xs text-gray-500 dark:text-slate-400 uppercase">
          <tr>
            <th className="text-left p-3">Endereço/Fonte</th>
            <th className="text-left p-3">Data</th>
            <th className="text-left p-3">Valor vendido</th>
            <th className="text-left p-3">Área</th>
            <th className="text-left p-3">Valor/m²</th>
            <th className="text-left p-3">Confiança</th>
          </tr>
        </thead>
        <tbody className="divide-y">
          {transactions.map((transaction, index) => <tr key={`${transaction.source}-${transaction.address}-${index}`}>
            <td className="p-3">
              <div className="font-bold">{transaction.address || transaction.title || 'Endereço anonimizado'}</div>
              <div className="text-xs text-gray-500 dark:text-slate-400">
                {transaction.source} · Venda confirmada
                {transaction.registry_office ? ` · ${transaction.registry_office}` : ''}
                {transaction.property_sql ? ` · SQL ${transaction.property_sql}` : ''}
              </div>
            </td>
            <td className="p-3">{transaction.transaction_date ? new Date(transaction.transaction_date).toLocaleDateString('pt-BR') : 'Não informada'}</td>
            <td className="p-3 font-bold">{money(Number(transaction.price || 0))}</td>
            <td className="p-3">{transaction.area_m2 ? `${transaction.area_m2} m²` : '—'}</td>
            <td className="p-3">{transaction.price_m2 ? `${money(Number(transaction.price_m2))}/m²` : '—'}</td>
            <td className="p-3">{transaction.confidence_percentage ?? 80}%</td>
          </tr>)}
        </tbody>
      </table>
    </div> : null}
    {transactions.length === 0 ? <>
    <div className="mt-4 bg-amber-50 dark:bg-amber-950 border border-amber-200 rounded-2xl p-5">
      <div className="flex items-start gap-3">
        <div className="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300 grid place-items-center shrink-0"><PlugZap className="w-5 h-5" /></div>
        <div>
          <div className="font-black text-amber-900 dark:text-amber-200">{loading ? 'Consultando fontes transacionais...' : 'Nenhuma transação real retornada'}</div>
          <p className="text-sm text-amber-800 dark:text-amber-300 mt-1">{loading ? 'Consultando ITBI em dados abertos, planilha importada, BCB e catálogos municipais públicos.' : message}</p>
          <div className="mt-3 grid sm:grid-cols-3 gap-2 text-xs">
            <StatusPill label="Região analisada" value={locationLabel || 'Não informada'} />
            <StatusPill label="Vendas confirmadas" value="0 nesta coleta" />
            <StatusPill label="Tipo de dado" value="Não simulado" />
          </div>
          {!loading && providers.length > 0 && <div className="mt-3 text-xs text-amber-800 dark:text-amber-300">
            {providers.map(provider => <div key={provider.key}>• {provider.label}: {provider.message}</div>)}
          </div>}
        </div>
      </div>
    </div>
    </> : null}
  </div>;
}

function SafeImage({ src, alt, className, fallbackClassName }: { src?: string | null; alt: string; className: string; fallbackClassName: string }) {
  const [failed, setFailed] = React.useState(false);
  const safeSrc = safeExternalHttpsUrl(src);
  React.useEffect(() => setFailed(false), [safeSrc]);

  if (!safeSrc || failed) {
    return <div className={`${fallbackClassName} bg-linear-to-br from-slate-100 to-slate-200 grid place-items-center text-slate-400`}>
      <Building2 className="w-8 h-8" />
    </div>;
  }

  return <img src={safeSrc} alt={alt} className={className} onError={() => setFailed(true)} referrerPolicy="no-referrer" />;
}

function StatusPill({ label, value }: { label: string; value: string }) {
  return <div className="bg-white/70 dark:bg-slate-900/70 border border-amber-100 rounded-xl p-3">
    <span className="text-amber-700 dark:text-amber-300 block">{label}</span>
    <strong className="text-amber-950">{value}</strong>
  </div>;
}

function MarketDataNotice({ item, providers, confirmedTransactions, listedComparables }: { item: MarketItem; providers: ProviderStatus[]; confirmedTransactions: number; listedComparables: number }) {
  const noDatasetProviders = providers.filter(provider => provider.status === 'no_dataset');
  const unconfiguredProviders = providers.filter(provider => provider.status === 'unconfigured');
  const isSp = isSaoPauloItem(item);

  if (confirmedTransactions > 0) {
    return <section className="bg-emerald-50 dark:bg-emerald-950 border border-emerald-200 rounded-3xl p-5">
      <div className="font-black text-emerald-900 dark:text-emerald-200">Transações reais encontradas</div>
      <p className="text-sm text-emerald-800 dark:text-emerald-300 mt-1">A análise está usando {confirmedTransactions} venda(s) confirmada(s), além de {listedComparables} anúncio(s) comparável(is).</p>
    </section>;
  }

  return <section className="bg-blue-50 dark:bg-blue-950 border border-blue-200 rounded-3xl p-5">
    <div className="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
      <div>
        <div className="font-black text-blue-950">
          {isSp ? 'Integração São Paulo ativa, mas sem transações públicas retornadas' : 'Sem transações reais retornadas'}
        </div>
        <p className="text-sm text-blue-900 dark:text-blue-200 mt-1">
          O valor exibido nesta análise está vindo dos {listedComparables} anúncios comparáveis do radar. As fontes públicas (CKAN/ITBI, BCB SGS) foram consultadas e não devolveram escritura para este endereço nesta coleta.
        </p>
      </div>
      <Badge>{isSp ? 'CKAN SP + BCB' : 'Dados abertos + anúncios'}</Badge>
    </div>
    <div className="grid md:grid-cols-3 gap-3 mt-4 text-sm">
      <StatusBox title="Anúncios comparáveis" value={`${listedComparables}`} detail="Base usada para preço/m² e valor estimado agora." />
      <StatusBox title="Vendas reais" value="0" detail="Nenhuma venda pública retornou do ITBI aberto ou da planilha importada." />
      <StatusBox title="Fontes públicas" value="CKAN + BCB" detail="ITBI em dados abertos, SELIC/CDI/IPCA/IVG-R. DataZAP/FipeZAP são comerciais e não entram nesta análise." />
    </div>
    {(noDatasetProviders.length > 0 || unconfiguredProviders.length > 0) && <div className="mt-4 rounded-2xl bg-white/70 dark:bg-slate-900/70 border border-blue-100 p-4 text-sm text-blue-900 dark:text-blue-200">
      <strong className="block mb-2">Fontes consultadas/configuradas:</strong>
      {noDatasetProviders.map(provider => <div key={provider.key}>• {provider.label}: {provider.message}</div>)}
      {unconfiguredProviders.map(provider => <div key={provider.key}>• {provider.label}: {provider.message}</div>)}
    </div>}
  </section>;
}

function StatusBox({ title, value, detail }: { title: string; value: string; detail: string }) {
  return <div className="bg-white/80 dark:bg-slate-900/80 border border-blue-100 rounded-2xl p-4">
    <span className="text-blue-700 dark:text-blue-300 text-xs font-bold">{title}</span>
    <strong className="block text-xl mt-1">{value}</strong>
    <p className="text-xs text-blue-800 dark:text-blue-300 mt-1">{detail}</p>
  </div>;
}

function SummaryRow({ label, value }: { label: string; value: string }) {
  return <div className="flex justify-between gap-3 py-3 border-b last:border-b-0 text-sm"><span className="text-gray-500 dark:text-slate-400">{label}</span><strong className="text-right">{value}</strong></div>;
}

function CalculationStep({ index, title, value }: { index: number; title: string; value: string }) {
  return <div className="border rounded-2xl p-4 bg-emerald-50 dark:bg-emerald-950 border-emerald-100">
    <div className="flex items-center gap-3">
      <span className="w-8 h-8 rounded-full grid place-items-center text-xs font-black bg-emerald-600 text-white">{index}</span>
      <div className="font-black text-sm">{title}</div>
    </div>
    <p className="text-xs text-gray-500 dark:text-slate-400 mt-3">{value}</p>
  </div>;
}

function median(values: number[]) {
  if (!values.length) return 0;
  const middle = Math.floor(values.length / 2);
  return values.length % 2 ? values[middle] : (values[middle - 1] + values[middle]) / 2;
}

function confidenceFrom(item: MarketItem, listedCount: number, transactionCount: number) {
  let confidence = 30;
  confidence += Math.min(30, listedCount * 3);
  confidence += Math.min(30, transactionCount * 10);
  if (item.neighborhood) confidence += 5;
  if (item.area_m2) confidence += 5;
  return Math.max(20, Math.min(95, confidence));
}

function sourceList(item: MarketItem) {
  return item.market_reference_sources?.length ? item.market_reference_sources.join(', ') : (item.source || 'Fonte não informada');
}

function sameNeighborhood(transaction: ConfirmedTransaction, item: MarketItem) {
  const transactionNeighborhood = normalizeText(transaction.neighborhood);
  const itemNeighborhood = normalizeText(item.neighborhood);

  if (transactionNeighborhood && itemNeighborhood) {
    return transactionNeighborhood.includes(itemNeighborhood) || itemNeighborhood.includes(transactionNeighborhood);
  }

  const transactionCity = normalizeText(transaction.city);
  const itemCity = normalizeText(item.city);
  if (transactionCity && itemCity) {
    return transactionCity.includes(itemCity) || itemCity.includes(transactionCity);
  }

  return false;
}

function sameBuilding(transaction: ConfirmedTransaction, item: MarketItem) {
  const subjectAddress = normalizeAddressText(item.address || item.title);
  const transactionAddress = normalizeAddressText(transaction.address || transaction.title);
  const transactionStreet = normalizeAddressText(transaction.street_name);
  const transactionNumber = normalizeAddressText(transaction.street_number);

  if (!subjectAddress || (!transactionAddress && !transactionStreet)) return false;

  if (transactionStreet && subjectAddress.includes(transactionStreet)) {
    return !transactionNumber || subjectAddress.includes(transactionNumber);
  }

  if (!transactionAddress) return false;

  const subjectTokens = subjectAddress.split(' ').filter(token => token.length > 2);
  const addressTokens = transactionAddress.split(' ').filter(token => token.length > 2);
  const sharedTokens = subjectTokens.filter(token => addressTokens.includes(token));
  const hasUsefulOverlap = sharedTokens.length >= Math.min(3, Math.max(1, Math.floor(subjectTokens.length * 0.45)));

  return hasUsefulOverlap && (!transactionNumber || subjectAddress.includes(transactionNumber));
}

function normalizeText(value?: string | null) {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();
}

function normalizeAddressText(value?: string | null) {
  return normalizeText(value)
    .replace(/\b(apartamento|apto|casa|sobrado|studio|imovel|imoveis|imóvel|imóveis|a venda|à venda|venda|em|com|dormitorios|dormitórios|quartos|quarto|suite|suíte)\b/g, ' ')
    .replace(/[^\w\s]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function providerSummary(providers: ProviderStatus[]) {
  if (!providers.length) return 'consultando providers configurados';
  const connected = providers.filter(provider => provider.status === 'connected');
  if (connected.length) return connected.map(provider => `${provider.label} (${provider.count})`).join(', ');
  const consulted = providers.filter(provider => provider.status === 'no_dataset');
  if (consulted.length) return `${consulted.map(provider => provider.label).join(', ')} consultado(s), sem base transacional pública retornada`;
  return 'providers sem credencial/configuração nesta instalação';
}

function isSaoPauloItem(item: MarketItem) {
  const city = String(item.city || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  const state = String(item.state || '').toUpperCase();

  return state === 'SP' && city.includes('sao paulo');
}

function comparablePricePerM2(item: Pick<MarketItem, 'asking_price' | 'area_m2' | 'price_per_m2'>) {
  const askingPrice = Number(item.asking_price || 0);
  const area = Number(item.area_m2 || 0);
  const calculated = askingPrice > 0 && area >= 10 ? askingPrice / area : 0;
  const provided = Number(item.price_per_m2 || 0);

  if (isValidPricePerM2(calculated)) return calculated;
  if (isValidPricePerM2(provided)) return provided;

  return 0;
}

function isValidPricePerM2(value: number) {
  return Number.isFinite(value) && value >= 1000 && value <= 100000;
}

function referencePriceFrom(storedReference: number, values: number[], fallbackMedian: number) {
  if (!isValidPricePerM2(storedReference)) return fallbackMedian;
  if (!values.length) return storedReference;

  const min = Math.min(...values);
  const max = Math.max(...values);
  const tolerance = Math.max(1500, (max - min) * 0.25);

  if (storedReference >= min - tolerance && storedReference <= max + tolerance) {
    return storedReference;
  }

  return fallbackMedian;
}

function PriceM2Distribution({ values, reference }: { values: number[]; reference: number }) {
  const buckets = buildBuckets(values);

  if (!values.length) {
    return <div className="mt-5 border rounded-2xl p-5 bg-slate-50 dark:bg-slate-950 text-sm text-gray-500 dark:text-slate-400">
      Não há comparáveis com preço/m² válido para montar a distribuição.
    </div>;
  }

  const maxCount = Math.max(...buckets.map(bucket => bucket.count), 1);

  return <div className="mt-5 border rounded-2xl p-5 bg-slate-50 dark:bg-slate-950">
    <div className="flex items-center justify-between gap-3 mb-4">
      <div>
        <h3 className="font-black text-sm">Distribuição dos comparáveis válidos</h3>
        <p className="text-xs text-gray-500 dark:text-slate-400">Valores fora da faixa realista foram ignorados.</p>
      </div>
      <Badge>{values.length} base(s)</Badge>
    </div>
    <div className="space-y-3">
      {buckets.map(bucket => <div key={bucket.label} className="grid grid-cols-[90px_1fr_42px] gap-3 items-center text-xs">
        <span className="font-bold text-gray-600 dark:text-slate-300">{bucket.label}</span>
        <div className="h-3 rounded-full bg-white dark:bg-slate-900 border overflow-hidden">
          <div className="h-full rounded-full bg-primary-500" style={{ width: `${Math.max(bucket.count ? 8 : 0, (bucket.count / maxCount) * 100)}%` }} />
        </div>
        <span className="text-right text-gray-500 dark:text-slate-400">{bucket.count}</span>
      </div>)}
    </div>
    <div className="mt-4 text-xs text-gray-500 dark:text-slate-400">
      Referência usada: <strong className="text-gray-900 dark:text-slate-100">{reference ? `${money(reference)}/m²` : 'sem referência'}</strong>
    </div>
  </div>;
}

function buildBuckets(values: number[]) {
  if (!values.length) return [];
  const min = Math.min(...values);
  const max = Math.max(...values);
  if (min === max) {
    return [{ label: `${money(min)}`, count: values.length }];
  }

  const size = Math.max(1, (max - min) / 5);
  const counts = Array.from({ length: 6 }, (_, index) => ({
    label: `${Math.round((min + index * size) / 1000)}k-${Math.round((min + (index + 1) * size) / 1000)}k`,
    count: 0,
  }));

  values.forEach(value => {
    const index = Math.min(5, Math.floor((value - min) / size));
    counts[index].count += 1;
  });

  return counts;
}
