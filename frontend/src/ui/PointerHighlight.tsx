import type { ReactNode } from 'react';

export default function PointerHighlight({ children }: { children: ReactNode }) {
  return <span className="pointer-highlight relative inline-block px-1"><span className="relative z-10">{children}</span><span aria-hidden="true" className="absolute inset-x-0 bottom-0 h-[42%] -skew-x-6 rounded-sm bg-primary-200/80 dark:bg-primary-700/45" /></span>;
}
