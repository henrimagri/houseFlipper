import { Component, useEffect, useRef, useState, type ErrorInfo, type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { Globe2, MapPin } from 'lucide-react';
import { Globe3D as AceternityGlobe, type GlobeConnection, type GlobeMarker } from '@/components/ui/3d-globe';

const markers: GlobeMarker[] = [
  { label: 'São Paulo', lat: -23.5505, lng: -46.6333, detail: 'Pesquisa urbana e análise de oportunidades' },
  ...[
    ['Rio de Janeiro',-22.9068,-43.1729],['Belo Horizonte',-19.9167,-43.9345],['Brasília',-15.7939,-47.8828],
    ['Goiânia',-16.6869,-49.2648],['Curitiba',-25.4284,-49.2733],['Porto Alegre',-30.0346,-51.2177],
    ['Florianópolis',-27.5954,-48.548],['Salvador',-12.9777,-38.5016],['Recife',-8.0476,-34.877],
    ['Fortaleza',-3.7319,-38.5267],['Manaus',-3.119,-60.0217],['Belém',-1.4558,-48.4902],
    ['Cuiabá',-15.601,-56.0974],['Campo Grande',-20.4697,-54.6201],['Vitória',-20.3155,-40.3128],
    ['Natal',-5.7793,-35.2009],['João Pessoa',-7.1195,-34.845],['Maceió',-9.6498,-35.7089],
    ['Aracaju',-10.9472,-37.0731],['São Luís',-2.5307,-44.3068],['Teresina',-5.0919,-42.8034],
    ['Palmas',-10.2491,-48.3243],['Porto Velho',-8.7608,-63.8999],['Rio Branco',-9.9754,-67.8249],
    ['Boa Vista',2.8235,-60.6758],['Macapá',.0355,-51.0705],
  ].map(([label,lat,lng]) => ({ label: String(label), lat: Number(lat), lng: Number(lng), detail: 'Conexão nacional demonstrativa entre capitais brasileiras' })),
];

const connections: GlobeConnection[] = [
  ['Brasília','Manaus'],['Brasília','Belém'],['Brasília','Fortaleza'],['Brasília','Salvador'],['Brasília','Belo Horizonte'],
  ['Brasília','São Paulo'],['Brasília','Cuiabá'],['São Paulo','Rio de Janeiro'],['São Paulo','Curitiba'],
  ['Curitiba','Florianópolis'],['Florianópolis','Porto Alegre'],['Salvador','Recife'],['Recife','Fortaleza'],
].map(([from,to]) => ({ from, to }));

function StaticFallback() {
  return <div className="grid h-full min-h-[330px] place-items-center bg-[radial-gradient(circle_at_38%_32%,#244c83,#071126_68%)] text-center text-white"><div><Globe2 className="mx-auto size-28 text-sky-300/80" /><p className="mt-3 text-sm font-bold">Visualização terrestre</p><p className="mt-1 text-xs text-slate-400">WebGL indisponível neste dispositivo.</p></div></div>;
}

class GlobeBoundary extends Component<{ children: ReactNode }, { failed: boolean }> {
  state = { failed: false };
  static getDerivedStateFromError() { return { failed: true }; }
  componentDidCatch(_error: Error, _info: ErrorInfo) { /* fallback local; nenhum dado sensível é registrado */ }
  render() { return this.state.failed ? <StaticFallback /> : this.props.children; }
}

export default function Globe3DDemo() {
  const wrapper = useRef<HTMLDivElement>(null);
  const [active, setActive] = useState(markers[0]);
  const [visible, setVisible] = useState(true);
  const [tabVisible, setTabVisible] = useState(!document.hidden);

  useEffect(() => {
    if (!wrapper.current) return;
    const observer = new IntersectionObserver(([entry]) => setVisible(entry.isIntersecting), { rootMargin: '120px' });
    observer.observe(wrapper.current);
    const visibility = () => setTabVisible(!document.hidden);
    document.addEventListener('visibilitychange', visibility);
    return () => { observer.disconnect(); document.removeEventListener('visibilitychange', visibility); };
  }, []);

  return <div ref={wrapper} data-component="aceternity-3d-globe-demo" className="grid overflow-hidden rounded-[2rem] border border-slate-200/70 bg-white shadow-2xl shadow-primary-950/10 dark:border-white/10 dark:bg-[#050914] dark:shadow-primary-950/30 sm:grid-cols-[minmax(0,1fr)_12.5rem] lg:grid-cols-1">
    <div className="relative h-[340px] min-w-0 bg-[#030817] bg-[radial-gradient(circle_at_50%_42%,rgba(21,94,117,.22),transparent_56%)] sm:h-[390px] lg:h-[410px]">
      <GlobeBoundary>
        <AceternityGlobe
          className="h-full"
          markers={markers}
          connections={connections}
          paused={!visible || !tabVisible}
          config={{
            radius: 2,
            textureUrl: '/assets/globe/earth-blue-marble.jpg',
            bumpMapUrl: '/assets/globe/earth-topology.png',
            showAtmosphere: true,
            atmosphereColor: '#38bdf8',
            atmosphereIntensity: .28,
            atmosphereBlur: 2.15,
            bumpScale: 2.4,
            autoRotateSpeed: 0,
            enableZoom: false,
            enableRotate: true,
            minDistance: 5.7,
            maxDistance: 9.5,
            initialRotation: { x: -.24, y: -.67 },
            ambientIntensity: .72,
            pointLightIntensity: 2.1,
            showShield: true,
            shieldColor: '#22d3ee',
            shieldOpacity: .035,
          }}
          onMarkerClick={setActive}
        />
      </GlobeBoundary>
      <span className="pointer-events-none absolute left-4 top-4 rounded-full border border-white/15 bg-slate-950/80 px-3 py-1 text-[10px] font-bold text-slate-200 shadow-sm">Brasil em foco · arraste para girar</span>
    </div>
    <aside aria-live="polite" className="flex min-w-0 flex-col justify-center border-t border-slate-200/70 bg-slate-50/80 p-5 dark:border-white/10 dark:bg-slate-950/70 sm:border-l sm:border-t-0 lg:border-l-0 lg:border-t">
      <span className="grid size-10 place-items-center rounded-2xl bg-gradient-to-br from-cyan-400 to-blue-600 text-white shadow-lg shadow-cyan-500/20"><MapPin className="size-5" /></span>
      <p className="mt-4 text-[10px] font-black uppercase tracking-[.18em] text-cyan-600 dark:text-cyan-300">Capital em destaque</p>
      <h2 className="mt-1 text-lg font-black text-slate-950 dark:text-white">{active.label}</h2>
      <p className="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">{active.detail}. Os pontos não representam clientes ou presença comercial.</p>
      <Link to="/solucoes/house-flipper" className="mt-4 inline-flex items-center text-xs font-bold text-primary-600 dark:text-primary-300">Conhecer análise de oportunidades →</Link>
    </aside>
  </div>;
}
