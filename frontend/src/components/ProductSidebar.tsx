import { Link, useLocation } from 'react-router-dom';
import { Hexagon } from 'lucide-react';
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
  onNavigate,
}: {
  product: ProductSlug | 'account';
  collapsed: boolean;
  onNavigate: () => void;
}) {
  const { pathname } = useLocation();
  const { name, navigation } = useSession();
  const items: NavItem[] = product === 'account' ? ACCOUNT_NAV : (navigation[product] ?? []);
  const groups = items.reduce<Record<string, NavItem[]>>((acc, item) => {
    const key = item.group || 'Módulos';
    acc[key] = acc[key] ? [...acc[key], item] : [item];
    return acc;
  }, {});
  const meta = product === 'account' ? { label: 'Conta' } : PRODUCT_META[product];

  return (
    <aside className={`fc-sidebar flex h-dvh max-w-[calc(100vw-1rem)] flex-col overflow-hidden bg-[var(--app-sidebar)] text-slate-200 transition-[width] duration-300 ${collapsed ? 'w-[76px]' : 'w-72'}`}>
      <div className="flex items-center gap-3 px-4 h-16 border-b border-white/10">
        <Link to="/app/inicio" className="fc-sidebar-brand" aria-label="FlipControl, início da conta"><Hexagon size={22} strokeWidth={1.6} /></Link>
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
                    aria-current={active ? 'page' : undefined}
                    data-tour={item.path}
                    title={collapsed ? item.label : undefined}
                    onClick={onNavigate}
                    className="fc-sidebar-link relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition"
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
    </aside>
  );
}
