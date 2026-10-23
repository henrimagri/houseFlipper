import { Check, Loader2 } from 'lucide-react';
import type { ButtonHTMLAttributes, ReactNode } from 'react';

type Props = ButtonHTMLAttributes<HTMLButtonElement> & { state?: 'idle' | 'loading' | 'success'; loadingLabel?: string; successLabel?: string; children: ReactNode };

export default function StatefulButton({ state = 'idle', loadingLabel = 'Processando…', successLabel = 'Concluído', children, disabled, className = '', ...props }: Props) {
  return <button {...props} disabled={disabled || state === 'loading'} aria-busy={state === 'loading'} className={`fc-button inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-60 ${className}`}>
    {state === 'loading' && <Loader2 className="size-4 animate-spin" aria-hidden="true" />}{state === 'success' && <Check className="size-4" aria-hidden="true" />}{state === 'loading' ? loadingLabel : state === 'success' ? successLabel : children}
  </button>;
}
