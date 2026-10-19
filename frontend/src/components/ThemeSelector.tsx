import { useId } from 'react';
import { Monitor, Moon, Sun } from 'lucide-react';
import { useTheme, type ThemeMode } from './ThemeProvider';

export default function ThemeSelector() {
  const { mode, setMode } = useTheme();
  const id = useId();
  const Icon = mode === 'dark' ? Moon : mode === 'light' ? Sun : Monitor;
  return <div className="flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 text-slate-700 dark:text-slate-200">
    <Icon aria-hidden="true" className="size-4" />
    <label htmlFor={id} className="sr-only">Tema visual</label>
    <select id={id} value={mode} onChange={event => setMode(event.target.value as ThemeMode)} className="max-w-32 rounded-md bg-transparent py-2 text-sm focus-visible:outline-2 focus-visible:outline-primary-500">
      <option value="system">Sistema</option><option value="light">Claro</option><option value="dark">Escuro</option>
    </select>
  </div>;
}
