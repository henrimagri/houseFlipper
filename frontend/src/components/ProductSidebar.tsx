import { Link, useLocation } from 'react-router-dom';
import { ChevronsLeft, ChevronsRight } from 'lucide-react';
import { PRODUCT_META, ProductSlug } from '@/lib/products';
import { useSession } from '@/lib/session';
import { navIcon } from '@/lib/navIcons';

const ACCOUNT_NAV = [
  { label: 'Início', path: '/app/inicio', group: 'Conta' },
  { label: 'Produtos', path: '/app/produtos', group: 'Conta' },
  { label: 'Assinaturas', path: '/app/assinaturas', group: 'Conta' },
  { label: 'Configurações', path: '/app/configuracoes', group: 'Conta' },
  { label: 'Ajuda', path: '/app/ajuda', group: 'Conta' },
];

type NavItem = { label: string; path: string; feature?: string | null; group?: string };

export default function ProductSidebar({
  product,
  collapsed,
  onToggle,
  onNavigate,
}: {
  product: ProductSlug | 'account';
  collapsed: boolean;
  onToggle: () => void;
  onNavigate: () => void;
}) {
  const { pathname } = useLocation();
  const { catalog, name, navigation } = useSession();
  const items: NavItem[] = product === 'account' ? ACCOUNT_NAV : (navigation[product] ?? []);
  const groups = items.reduce<Record<string, NavItem[]>>((acc, item) => {
    const key = item.group || 'Módulos';
    acc[key] = acc[key] ? [...acc[key], item] : [item];
    return acc;
  }, {});
  const meta = product === 'account' ? { label: 'Conta' } : PRODUCT_META[product];
  const sub = product === 'account' ? null : catalog.find((item) => item.slug === product)?.subscription;

  return (
    <aside className={`flex h-dvh max-w-[calc(100vw-1rem)] flex-col overflow-hidden bg-[var(--app-sidebar)] text-slate-200 shadow-2xl shadow-slate-950/20 transition-[width] duration-300 ${collapsed ? 'w-[76px]' : 'w-72'}`}>
      <div className="flex items-center gap-3 px-4 h-16 border-b border-white/10">
        <div className="h-9 w-9 rounded-xl bg-gradient-to-br from-primary-500 to-cyan-400 text-white grid place-items-center font-black shrink-0 shadow-lg shadow-primary-950/30">HF</div>
        {!collapsed && (
          <div className="min-w-0">
            <p className="font-semibold text-white truncate">{meta.label}</p>
            <p className="text-[11px] text-slate-400 truncate">{name}</p>
          </div>
        )}
      </div>
      <nav aria-label={`Navegação ${meta.label}`} className="min-h-0 flex-1 space-y-6 overflow-y-auto overscroll-contain px-2.5 py-4 [scrollbar-gutter:stable]">
        {Object.entries(groups).map(([label, groupItems]) => (
          <div key={label}>
            {!collapsed && <p className="px-3 mb-1.5 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">{label}</p>}
            <div className="space-y-0.5">
              {groupItems.map((item) => {
                const active = pathname === item.path || pathname.startsWith(item.path + '/');
                const Icon = navIcon(item.path);
                return (
                  <Link
                    key={item.path}
                    to={item.path}
                    data-tour={item.path}
                    title={collapsed ? item.label : undefined}
                    onClick={onNavigate}
                    className={`relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition ${
                      active ? 'bg-gradient-to-r from-primary-600 to-primary-500 text-white shadow-lg shadow-primary-950/25' : 'text-slate-300 hover:bg-white/8 hover:text-white'
                    }`}
                  >
                    <Icon className="h-4 w-4 shrink-0" strokeWidth={1.75} />
                    {!collapsed && <span className="truncate">{item.label}</span>}
                  </Link>
                );
              })}
            </div>
          </div>
        ))}
      </nav>
      <div className="shrink-0 space-y-2 border-t border-white/10 bg-[var(--app-sidebar)] p-3">
        {!collapsed && sub && (
          <p className="text-[11px] text-slate-400 px-1">
            {sub.status === 'trial' || sub.status === 'trialing' ? 'Assinatura em teste' : sub.active ? 'Assinatura ativa' : `Status: ${sub.status}`}
          </p>
        )}
        <button type="button" onClick={onToggle} className="hidden lg:flex w-full items-center gap-2 rounded-lg px-3 py-2 text-xs text-slate-400 hover:bg-white/10">
          {collapsed ? <ChevronsRight className="h-4 w-4" /> : <ChevronsLeft className="h-4 w-4" />}
          {!collapsed && 'Recolher'}
        </button>
      </div>
    </aside>
  );
}
