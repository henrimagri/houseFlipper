import { useEffect, useMemo, useState } from 'react';
import api from '@/lib/api';

type Point = { kind: string; id: number; title: string; status: string; lat: number; lng: number; price?: number | null; city?: string; neighborhood?: string };

export default function MapaImoveis() {
  const [points, setPoints] = useState<Point[]>([]);
  const [available, setAvailable] = useState(true);
  const [reason, setReason] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    api.get('/maps/listings', { params: { mode: 'properties' } }).then(({ data }) => {
      setAvailable(Boolean(data.available));
      setReason(data.reason ?? '');
      setPoints(data.points ?? []);
    }).catch((exception: any) => setError(exception.response?.data?.message ?? 'Mapa indisponível.'));
  }, []);

  const bbox = useMemo(() => {
    if (!points.length) return null;
    const lats = points.map((p) => p.lat);
    const lngs = points.map((p) => p.lng);
    const minLat = Math.min(...lats) - 0.02;
    const maxLat = Math.max(...lats) + 0.02;
    const minLng = Math.min(...lngs) - 0.02;
    const maxLng = Math.max(...lngs) + 0.02;
    return `${minLng},${minLat},${maxLng},${maxLat}`;
  }, [points]);

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-bold">Mapa de imóveis</h1>
        <p className="text-sm text-gray-500">Modo 1: carteira e leads com coordenada. Demais modos só aparecem com dados verificáveis.</p>
      </div>
      {error && <div role="alert" className="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">{error}</div>}
      {!available && <p className="text-sm text-amber-700">{reason}</p>}
      {bbox ? (
        <iframe title="Mapa OSM" className="w-full h-80 rounded-xl border" src={`https://www.openstreetmap.org/export/embed.html?bbox=${encodeURIComponent(bbox)}&layer=mapnik`} />
      ) : <p className="text-gray-400">Nenhum ponto com latitude/longitude na sua conta.</p>}
      <ul className="bg-white dark:bg-slate-900 border rounded-xl divide-y">
        {points.map((point) => (
          <li key={`${point.kind}-${point.id}`} className="px-4 py-3 text-sm">
            <strong>{point.title}</strong> · {point.kind === 'wallet' ? 'carteira' : 'descoberto'} · {point.city || '—'}
          </li>
        ))}
      </ul>
    </div>
  );
}
