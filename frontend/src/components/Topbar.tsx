import { Bell, HelpCircle, Plus } from 'lucide-react';

interface TopbarProps {
  userName?: string;
  greeting?: string;
  subtitle?: string;
}

export default function Topbar({
  userName = 'Matheus Silva',
  greeting = 'Olá, Matheus! 👋',
  subtitle = 'Bem-vindo ao seu painel de controle',
}: TopbarProps) {
  return (
    <header className="flex items-center justify-between px-8 py-5 bg-white border-b border-gray-200">
      <div>
        <h1 className="text-xl font-semibold text-gray-900">{greeting}</h1>
        <p className="text-sm text-gray-500 mt-0.5">{subtitle}</p>
      </div>

      <div className="flex items-center gap-4">
        <button className="bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 shadow-sm">
          <Plus className="w-4 h-4" />
          Novo Projeto
        </button>

        <button className="relative w-9 h-9 rounded-lg bg-gray-50 hover:bg-gray-100 flex items-center justify-center text-gray-600">
          <Bell className="w-[18px] h-[18px]" />
          <span className="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center">
            3
          </span>
        </button>

        <button className="w-9 h-9 rounded-lg bg-gray-50 hover:bg-gray-100 flex items-center justify-center text-gray-600">
          <HelpCircle className="w-[18px] h-[18px]" />
        </button>

        <div className="flex items-center gap-3 pl-3 border-l border-gray-200">
          <div className="text-right">
            <div className="text-sm font-semibold text-gray-900">{userName}</div>
            <div className="text-xs text-gray-500">Investidor</div>
          </div>
          <div className="w-9 h-9 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-semibold text-sm">
            {userName.split(' ').map((n) => n[0]).slice(0, 2).join('')}
          </div>
        </div>
      </div>
    </header>
  );
}
