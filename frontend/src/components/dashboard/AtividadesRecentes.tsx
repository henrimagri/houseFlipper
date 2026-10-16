import { Wallet, ShoppingCart, CheckCircle2, FileText, Home } from 'lucide-react';

const atividades = [
  { Icon: Wallet, iconBg: 'bg-violet-100', iconColor: 'text-violet-600', titulo: 'Pagamento realizado', desc: 'Pagamento de R$ 4.850,00 para Eletricista', tempo: 'Há 2 horas' },
  { Icon: ShoppingCart, iconBg: 'bg-amber-100', iconColor: 'text-amber-600', titulo: 'Nova despesa adicionada', desc: 'Compra de materiais - R$ 2.350,00', tempo: 'Há 5 horas' },
  { Icon: CheckCircle2, iconBg: 'bg-emerald-100', iconColor: 'text-emerald-600', titulo: 'Etapa concluída', desc: 'Hidráulica concluída - Casa Jardim Paulista', tempo: 'Há 1 dia' },
  { Icon: FileText, iconBg: 'bg-orange-100', iconColor: 'text-orange-600', titulo: 'Novo documento enviado', desc: 'Contrato de compra - Projeto #2024-002', tempo: 'Há 1 dia' },
  { Icon: Home, iconBg: 'bg-blue-100', iconColor: 'text-blue-600', titulo: 'Imóvel vendido', desc: 'Casa Brooklin - Lucro: R$ 85.450,00', tempo: 'Há 2 dias' },
];

export default function AtividadesRecentes() {
  return (
    <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-sm h-full">
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-base font-semibold text-gray-900">Atividades Recentes</h3>
        <a href="#" className="text-xs font-medium text-primary-600 hover:underline">Ver todas</a>
      </div>
      <div className="space-y-4">
        {atividades.map((a, idx) => {
          const Icon = a.Icon;
          return (
            <div key={idx} className="flex items-start gap-3">
              <div className={`w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 ${a.iconBg}`}>
                <Icon className={`w-[18px] h-[18px] ${a.iconColor}`} />
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-start justify-between gap-2">
                  <div className="text-sm font-semibold text-gray-900">{a.titulo}</div>
                  <div className="text-[11px] text-gray-400 whitespace-nowrap">{a.tempo}</div>
                </div>
                <div className="text-xs text-gray-500 mt-0.5">{a.desc}</div>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
