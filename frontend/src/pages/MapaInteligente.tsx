import { useEffect, useMemo, useState } from 'react';
import api from '@/lib/api';
import MapCanvas, { MapPoint } from '@/maps/MapCanvas';
import { productFromPath } from '@/lib/products';
import { useSession } from '@/lib/session';

const ALL_MODES = [
  { id: 'properties', label: 'Imóveis' },
  { id: 'lead_heat', label: 'Calor de leads' },
  { id: 'price_m2', label: 'Preço m²' },
  { id: 'opportunities', label: 'Oportunidades' },
  { id: 'liquidity', label: 'Liquidez' },
  { id: 'auctions', label: 'Leilões' },
  { id: 'flipper', label: 'House Flipper' },
  { id: 'capture_potential', label: 'Captação' },
];

export default function MapaInteligente() {
  const { catalog } = useSession();
  const product = productFromPath(window.location.pathname);
  const allowed = catalog.find((item) => item.slug === product);
  const [mode, setMode] = useState('properties');
  const [config, setConfig] = useState<any>(null);
  const [result, setResult] = useState<any>(null);
  const [query, setQuery] = useState('');
  const [hits, setHits] = useState<any[]>([]);
  const [pitch3d, setPitch3d] = useState(true);
  const [drawing, setDrawing] = useState(false);
  const [maxPrice, setMaxPrice] = useState('');
  const [radiusKm, setRadiusKm] = useState('');
  const [selected, setSelected] = useState<MapPoint | null>(null);
  const [flyTo, setFlyTo] = useState<{ lat: number; lng: number; zoom?: number } | null>(null);
  const [error, setError] = useState('');
  const [buildings3d, setBuildings3d] = useState<boolean | null>(null);

  async function search(geometry?: any) {
    setError('');
    const { data } = await api.post('/maps/listings', {
      mode,
      product,
      filters: { max_price: maxPrice ? Number(maxPrice) : undefined },
      geometry,
    });
    setResult(data);
  }

  useEffect(() => {
    api.get('/maps/config').then(({ data }) => setConfig(data));
  }, []);
  useEffect(() => { search().catch((exception: any) => setError(exception.response?.data?.message ?? 'Mapa indisponível.')); }, [mode]);

  async function geocode() {
    const { data } = await api.get('/maps/geocode', { params: { q: query } });
    setHits(data.data ?? []);
  }

  async function applyRadius(hit: any) {
    const lat = Number(hit.lat);
    const lng = Number(hit.lng ?? hit.lon);
    setFlyTo({ lat, lng, zoom: 14 });
    const km = Number(radiusKm);
    if (km > 0) {
      await search({ type: 'radius', lat, lng, radius_km: km });
    }
  }

  const points: MapPoint[] = result?.points ?? [];
  const styleUrl = config?.style_url || 'https://tiles.openfreemap.org/styles/liberty';
  const modes = useMemo(() => ALL_MODES, []);

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap gap-2 items-center">
        {modes.map((item) => (
          <button key={item.id} type="button" onClick={() => setMode(item.id)} className={`px-3 py-1.5 rounded-full text-xs ${mode === item.id ? 'bg-sky-600 text-white' : 'border'}`}>{item.label}</button>
        ))}
        <button type="button" onClick={() => setPitch3d((v) => !v)} className="px-3 py-1.5 rounded-full text-xs border">{pitch3d ? '3D on' : '2D'}</button>
        <button type="button" onClick={() => setDrawing((v) => !v)} className="px-3 py-1.5 rounded-full text-xs border">{drawing ? 'Fechar polígono' : 'Desenhar área'}</button>
      </div>
      <div className="flex flex-wrap gap-2">
        <input className="input max-w-xs" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Cidade, bairro ou endereço" />
        <button type="button" onClick={geocode} className="px-3 py-2 rounded-lg border text-sm">Buscar</button>
        <input className="input max-w-[8rem]" value={radiusKm} onChange={(e) => setRadiusKm(e.target.value)} placeholder="Raio km" />
        <input className="input max-w-[10rem]" value={maxPrice} onChange={(e) => setMaxPrice(e.target.value)} placeholder="Preço máx." />
        <button type="button" onClick={() => search()} className="px-3 py-2 rounded-lg bg-sky-600 text-white text-sm">Filtrar</button>
      </div>
      {hits.length > 0 && (
        <ul className="text-sm bg-white dark:bg-slate-900 border rounded-xl divide-y">
          {hits.map((hit) => (
            <li key={hit.label}>
              <button type="button" className="w-full text-left px-3 py-2 hover:bg-slate-50" onClick={() => void applyRadius(hit)}>
                {hit.label}
              </button>
            </li>
          ))}
        </ul>
      )}
      {error && <p className="text-red-700 text-sm">{error}</p>}
      {result && !result.available && <p className="text-amber-700 text-sm">{result.reason}</p>}
      <p className="text-xs text-gray-500">Provedor: {config?.provider || 'openfreemap'}. Edifícios 3D: {buildings3d === null ? 'verificando camada…' : buildings3d ? 'disponíveis neste tileset' : 'camada indisponível; usando mapa 2D'}. Google Photorealistic 3D não está ligado. Clique no resultado da busca para voar até o ponto; informe raio em km para filtrar.</p>
      <div className="grid xl:grid-cols-3 gap-4">
        <div className="xl:col-span-2">
          <MapCanvas
            styleUrl={styleUrl}
            points={points}
            clusters={result?.clusters ?? []}
            pitch3d={pitch3d}
            drawing={drawing}
            flyTo={flyTo}
            onPolygon={(ring) => search({ type: 'polygon', rings: ring })}
            onSelect={(point) => { setSelected(point); setFlyTo({ lat: point.lat, lng: point.lng, zoom: 15 }); }}
            on3dAvailability={setBuildings3d}
          />
        </div>
        <ul className="bg-white dark:bg-slate-900 border rounded-xl divide-y max-h-[70vh] overflow-y-auto">
          {points.map((point) => (
            <li key={`${point.kind}-${point.id}`}>
              <button type="button" className={`w-full text-left px-4 py-2 text-sm ${selected?.id === point.id ? 'bg-sky-50' : ''}`} onClick={() => { setSelected(point); setFlyTo({ lat: point.lat, lng: point.lng, zoom: 15 }); }}>
                {point.title} · {point.kind} · {point.price ? point.price.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : '—'}
              </button>
            </li>
          ))}
          {points.length === 0 && <li className="px-4 py-3 text-sm text-gray-500">Nenhum ponto na conta para este modo/filtro.</li>}
        </ul>
      </div>
      {selected && <p className="text-sm">Selecionado: {selected.title}</p>}
      {allowed && <p className="text-[11px] text-gray-400">Modos fora do produto ativo voltam available:false no backend. Leilões continuam sem conector.</p>}
    </div>
  );
}
