import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';

export type ThemeMode = 'light' | 'dark' | 'system';
const key = 'flipcontrol:theme';
const isMode = (value: string | null): value is ThemeMode => value === 'light' || value === 'dark' || value === 'system';
function savedMode(): ThemeMode {
  try { const saved = localStorage.getItem(key); return isMode(saved) ? saved : 'system'; } catch { return 'system'; }
}
function apply(mode: ThemeMode, systemDark: boolean) {
  const dark = mode === 'dark' || (mode === 'system' && systemDark);
  document.documentElement.classList.toggle('dark', dark);
  document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
}
export function applyInitialTheme() { apply(savedMode(), window.matchMedia('(prefers-color-scheme: dark)').matches); }

const ThemeContext = createContext<{ mode: ThemeMode; setMode: (mode: ThemeMode) => void }>({ mode: 'system', setMode: () => {} });

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [mode, setMode] = useState<ThemeMode>(savedMode);
  useEffect(() => {
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const update = () => apply(mode, media.matches);
    update();
    media.addEventListener('change', update);
    try { localStorage.setItem(key, mode); } catch { /* The current tab still applies the selection. */ }
    return () => media.removeEventListener('change', update);
  }, [mode]);
  useEffect(() => {
    const update = (event: StorageEvent) => { if (event.key === key) setMode(isMode(event.newValue) ? event.newValue : 'system'); };
    window.addEventListener('storage', update);
    return () => window.removeEventListener('storage', update);
  }, []);
  return <ThemeContext.Provider value={{ mode, setMode }}>{children}</ThemeContext.Provider>;
}

export const useTheme = () => useContext(ThemeContext);
