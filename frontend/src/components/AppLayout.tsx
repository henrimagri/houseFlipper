import { Outlet } from 'react-router-dom';
import { useState } from 'react';
import Sidebar from './Sidebar';
import Topbar from './Topbar';

export default function AppLayout() {
  const [menuOpen, setMenuOpen] = useState(false);
  return (
    <div className="flex min-h-screen w-full overflow-x-hidden bg-[#f6f7fb] dark:bg-slate-950">
      {menuOpen && <button aria-label="Fechar menu" onClick={() => setMenuOpen(false)} className="fixed inset-0 bg-black/40 z-40 lg:hidden" />}
      <div className={`${menuOpen ? 'block' : 'hidden'} fixed left-0 top-0 z-50 lg:block lg:static lg:z-auto`}>
        <Sidebar onNavigate={() => setMenuOpen(false)} />
      </div>
      <div className="flex-1 min-w-0 flex flex-col">
        <button aria-expanded={menuOpen} aria-label="Abrir menu de navegação" onClick={() => setMenuOpen(true)} className="lg:hidden text-left px-4 py-3 font-semibold bg-white dark:bg-slate-900 border-b">☰ Menu</button>
        <Topbar />
        <main className="flex-1 min-w-0 p-4 sm:p-6 overflow-x-hidden">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
