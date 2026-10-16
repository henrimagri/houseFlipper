import { Database, ExternalLink, Globe2, Landmark, MapPinned, Settings2, TrendingUp } from 'lucide-react';
import { Link } from 'react-router-dom';

type SourceStatus = 'connected' | 'pending' | 'available' | 'error';

type SourceItem = {
  name: string;
  description: string;
  status: SourceStatus;
};

const sourceGroups: Array<{
  title: string;
  icon: typeof Database;
  items: SourceItem[];
}> = [
  {
    title: 'Mercado',
    icon: Globe2,
    items: [
      { name: 'Portais do radar', description: 'OLX, ZAP Imóveis, VivaReal e outros portais coletados pelo radar.', status: 'connected' },
      { name: 'DataZAP AVM', description: 'Pendente de API/contrato para avaliação automática oficial.', status: 'pending' },
      { name: 'FipeZAP', description: 'Pendente de integração para índice e referência de mercado.', status: 'pending' },
    ],
  },
  {
    title: 'Geolocalização',
    icon: MapPinned,
    items: [
      { name: 'Google Maps/Places', description: 'Use GOOGLE_MAPS_API_KEY e billing ativo para dados do Google.', status: 'pending' },
      { name: 'OpenStreetMap', description: 'Fallback gratuito para entorno do bairro e pontos próximos.', status: 'available' },
    ],
  },
  {
    title: 'Transações reais',
    icon: Landmark,
    items: [
      { name: 'ITBI importado', description: 'Planilhas públicas importadas alimentam vendas reais e preço/m².', status: 'connected' },
      { name: 'Cartórios', description: 'Defina CARTORIOS_API_URL e, se necessário, CARTORIOS_API_TOKEN.', status: 'pending' },
      { name: 'ITBI municipal via API', description: 'Defina SP_ITBI_API_URL ou ITBI_API_URL para consultar transações municipais.', status: 'pending' },
      { name: 'Prefeituras', description: 'Defina PREFEITURA_TRANSACOES_API_URL para consultar bases municipais.', status: 'pending' },
    ],
  },
  {
    title: 'Leilões',
    icon: Database,
    items: [
      { name: 'Caixa', description: 'Pendente de conector.', status: 'pending' },
      { name: 'Mega Leilões', description: 'Pendente de conector.', status: 'pending' },
      { name: 'Superbid', description: 'Pendente de conector.', status: 'pending' },
    ],
  },
  {
    title: 'Dados econômicos',
    icon: TrendingUp,
    items: [
      { name: 'SELIC/CDI', description: 'Pode usar BCB SGS.', status: 'available' },
      { name: 'INCC/IPCA', description: 'Pode usar BCB/IBGE.', status: 'available' },
    ],
  },
];

export default function Configuracoes() {
  return <div className="space-y-6">
    <section className="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
      <div>
        <p className="text-primary-600 uppercase tracking-widest text-sm font-black">Configurações</p>
        <h1 className="text-3xl font-black mt-2">Fontes de dados</h1>
        <p className="text-gray-500 mt-2">Controle o que está conectado, o que depende de credencial e quais bases alimentam a IA Inteligente.</p>
      </div>
      <div className="flex flex-wrap gap-3">
        <Link to="/importar-itbi" className="inline-flex items-center gap-2 bg-primary-600 text-white px-5 py-3 rounded-xl font-bold shadow-sm">
          <Database className="w-4 h-4" />
          Importar ITBI
        </Link>
        <a href="https://console.cloud.google.com/google/maps-apis" target="_blank" rel="noreferrer" className="inline-flex items-center gap-2 bg-white border px-5 py-3 rounded-xl font-bold text-slate-700">
          Google APIs
          <ExternalLink className="w-4 h-4" />
        </a>
      </div>
    </section>

    <section className="bg-white border rounded-3xl p-5 shadow-sm">
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        <div>
          <h2 className="font-black text-lg">Central de fontes da análise</h2>
          <p className="text-sm text-gray-500">Aqui fica claro o que está conectado, o que falhou e o que ainda precisa de credencial/API.</p>
        </div>
        <span className="bg-violet-50 text-primary-700 px-3 py-1 rounded-full text-xs font-black">Sem dados fictícios</span>
      </div>

      <div className="grid md:grid-cols-2 xl:grid-cols-5 gap-4 mt-4">
        {sourceGroups.map(group => <SourceGroup key={group.title} {...group} />)}
      </div>
    </section>

    <section className="grid lg:grid-cols-3 gap-4">
      <InfoCard
        title="Onde configurar credenciais"
        description="As chaves ficam no arquivo .env do backend. Depois de alterar, reinicie o servidor para o Laravel recarregar as variáveis."
      />
      <InfoCard
        title="Base real já ativa"
        description="A planilha ITBI importada alimenta a tabela de transações reais e já aparece nas análises por bairro/cidade."
      />
      <InfoCard
        title="Próximo nível"
        description="Para vendas no prédio com maior precisão, conecte provider com matrícula/endereço ou mantenha planilhas ITBI recorrentes."
      />
    </section>
  </div>;
}

function SourceGroup({ title, icon: Icon, items }: { title: string; icon: typeof Database; items: SourceItem[] }) {
  return <div className="border rounded-2xl p-4 bg-slate-50">
    <div className="flex items-center gap-2">
      <span className="w-9 h-9 rounded-xl bg-white border grid place-items-center text-primary-600">
        <Icon className="w-4 h-4" />
      </span>
      <h3 className="font-black">{title}</h3>
    </div>
    <div className="space-y-3 mt-4">
      {items.map(item => <div key={item.name}>
        <div className="flex items-center justify-between gap-2">
          <span className="font-bold text-sm">{item.name}</span>
          <StatusDot status={item.status} />
        </div>
        <p className="text-xs text-gray-500 mt-0.5">{item.description}</p>
      </div>)}
    </div>
  </div>;
}

function StatusDot({ status }: { status: SourceStatus }) {
  const colors: Record<SourceStatus, string> = {
    connected: 'bg-emerald-500',
    available: 'bg-blue-500',
    pending: 'bg-amber-500',
    error: 'bg-red-500',
  };

  return <span className={`w-2 h-2 rounded-full ${colors[status]}`} title={status} />;
}

function InfoCard({ title, description }: { title: string; description: string }) {
  return <div className="bg-white border rounded-2xl p-5 shadow-sm">
    <div className="w-10 h-10 rounded-xl bg-violet-50 text-primary-600 grid place-items-center">
      <Settings2 className="w-5 h-5" />
    </div>
    <h3 className="font-black mt-4">{title}</h3>
    <p className="text-sm text-gray-500 mt-2">{description}</p>
  </div>;
}
