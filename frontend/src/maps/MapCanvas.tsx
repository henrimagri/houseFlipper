import { useEffect, useRef, useState } from 'react';
import * as maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';

export type MapPoint = { id: number; kind: string; title?: string; lat: number; lng: number; price?: number | null };

type Props = {
  styleUrl: string;
  points: MapPoint[];
  clusters?: { lat: number; lng: number; count?: number; median_price_m2?: number; samples?: number }[];
  pitch3d: boolean;
  drawing: boolean;
  heightClass?: string;
  flyTo?: { lat: number; lng: number; zoom?: number } | null;
  onPolygon?: (ring: { lat: number; lng: number }[]) => void;
  onSelect?: (point: MapPoint) => void;
  on3dAvailability?: (available: boolean) => void;
};

export default function MapCanvas({ styleUrl, points, clusters = [], pitch3d, drawing, heightClass, flyTo, onPolygon, onSelect, on3dAvailability }: Props) {
  const root = useRef<HTMLDivElement>(null);
  const mapRef = useRef<maplibregl.Map | null>(null);
  const markers = useRef<maplibregl.Marker[]>([]);
  const ring = useRef<{ lat: number; lng: number }[]>([]);
  const drawingRef = useRef(drawing);
  const onPolygonRef = useRef(onPolygon);
  const [mapError, setMapError] = useState('');
  drawingRef.current = drawing;
  onPolygonRef.current = onPolygon;

  useEffect(() => {
    if (!root.current || mapRef.current) return;
    const map = new maplibregl.Map({
      container: root.current,
      style: styleUrl,
      center: [-46.6333, -23.5505],
      zoom: 11,
      pitch: pitch3d ? 45 : 0,
    });
    map.addControl(new maplibregl.NavigationControl(), 'top-right');
    const resize = () => map.resize();
    map.on('load', () => {
      resize();
      const layers = map.getStyle().layers ?? [];
      const buildingLayer = layers.find((layer) => {
        const sourceLayer = 'source-layer' in layer ? layer['source-layer'] : undefined;
        return layer.id.includes('building') || sourceLayer === 'building';
      });
      const buildingSource = buildingLayer && 'source' in buildingLayer && typeof buildingLayer.source === 'string' ? buildingLayer.source : null;
      const buildingSourceLayer = buildingLayer && 'source-layer' in buildingLayer ? buildingLayer['source-layer'] : undefined;
      on3dAvailability?.(Boolean(buildingSource && buildingSourceLayer));
      if (buildingSource && buildingSourceLayer && !map.getLayer('buildings-3d')) {
        try {
          map.addLayer({
            id: 'buildings-3d',
            source: buildingSource,
            'source-layer': buildingSourceLayer,
            type: 'fill-extrusion',
            minzoom: 14,
            paint: {
              'fill-extrusion-color': '#9ca3af',
              'fill-extrusion-height': ['coalesce', ['get', 'render_height'], ['get', 'height'], 8],
              'fill-extrusion-base': ['coalesce', ['get', 'render_min_height'], 0],
              'fill-extrusion-opacity': 0.65,
            },
          });
        } catch {
          /* tileset without building source */
        }
      }
    });
    map.on('error', (event) => {
      const message = event.error?.message || 'Falha ao carregar o mapa.';
      if (/Failed to fetch|AJAXError|style/i.test(message)) setMapError('Não foi possível carregar os tiles. Verifique a conexão e tente novamente.');
    });
    map.on('click', (event) => {
      if (!drawingRef.current) return;
      ring.current = [...ring.current, { lat: event.lngLat.lat, lng: event.lngLat.lng }];
      if (ring.current.length >= 3) onPolygonRef.current?.(ring.current);
    });
    const observer = new ResizeObserver(resize);
    observer.observe(root.current);
    mapRef.current = map;
    return () => {
      observer.disconnect();
      map.remove();
      mapRef.current = null;
    };
  }, [styleUrl, on3dAvailability]);

  useEffect(() => {
    mapRef.current?.easeTo({ pitch: pitch3d ? 60 : 0, duration: 400 });
  }, [pitch3d]);

  useEffect(() => {
    if (!flyTo || !mapRef.current) return;
    mapRef.current.flyTo({ center: [flyTo.lng, flyTo.lat], zoom: flyTo.zoom ?? 14, essential: true });
  }, [flyTo]);

  useEffect(() => {
    const map = mapRef.current;
    if (!map) return;
    markers.current.forEach((marker) => marker.remove());
    markers.current = [];
    const source = points.length ? points : clusters.map((cluster, index) => ({
      id: index, kind: 'cluster', lat: cluster.lat, lng: cluster.lng, title: `${cluster.count ?? cluster.samples ?? 0}`,
    }));
    source.forEach((point) => {
      const el = document.createElement('button');
      el.className = 'w-3 h-3 rounded-full border border-white shadow';
      el.style.background = point.kind === 'wallet' ? '#059669' : point.kind === 'cluster' ? '#dc2626' : '#2563eb';
      el.title = point.title || '';
      const marker = new maplibregl.Marker({ element: el }).setLngLat([point.lng, point.lat]).addTo(map);
      el.addEventListener('click', (event) => {
        event.stopPropagation();
        if ('kind' in point) onSelect?.(point as MapPoint);
      });
      markers.current.push(marker);
    });
    if (source.length && !flyTo) {
      const bounds = new maplibregl.LngLatBounds();
      source.forEach((point) => bounds.extend([point.lng, point.lat]));
      map.fitBounds(bounds, { padding: 48, maxZoom: 14 });
    }
  }, [points, clusters, flyTo]);

  return <div className="relative"><div ref={root} className={heightClass || 'w-full h-[70vh] min-h-[24rem] rounded-xl overflow-hidden border'} />{mapError && <div role="alert" className="absolute inset-x-3 top-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 shadow">{mapError}</div>}</div>;
}
