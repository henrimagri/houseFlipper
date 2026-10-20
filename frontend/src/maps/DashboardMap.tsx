import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '@/lib/api';
import MapCanvas, { MapPoint } from '@/maps/MapCanvas';
import { ProductSlug } from '@/lib/products';

export default function DashboardMap({ product, mode = 'properties', href }: { product: ProductSlug; mode?: string; href: string }) {
  const [config, setConfig] = useState<any>(null);
  const [result, setResult] = useState<any>(null);
  const [selected, setSelected] = useState<MapPoint | null>(null);

  useEffect(() => {
    api.get('/maps/config').then(({ data }) => setConfig(data)).catch(() => undefined);
    api.post('/maps/listings', { mode, product }).then(({ data }) => setResult(data)).catch(() => setResult({ available: false, reason: 'Mapa indisponível nesta conta.', points: [] }));
  }, [product, mode]);

  const points: MapPoint[] = result?.points ?? [];
  const styleUrl = config?.style_url || 'https://tiles.openfreemap.org/styles/liberty';

  return (
    <section className="bg-white dark:bg-slate-900 border rounded-2xl overflow-hidden shadow-sm">
      <div className="flex items-center justify-between px-4 py-3 border-b">
        <div>
          <h2 className="font-semibold">Mapa</h2>
          <p className="text-xs text-slate-500">{result?.available === false ? result.reason : `${points.length} pontos da conta`}</p>
        </div>
        <Link to={href} className="text-sm text-sky-700 font-medium">Abrir mapa</Link>
      </div>
      <MapCanvas
        styleUrl={styleUrl}
        points={points}
        clusters={result?.clusters ?? []}
        pitch3d={false}
        drawing={false}
        heightClass="w-full h-72 min-h-[18rem]"
        onSelect={setSelected}
      />
      {selected && <p className="px-4 py-2 text-sm border-t">Selecionado: {selected.title}</p>}
    </section>
  );
}
