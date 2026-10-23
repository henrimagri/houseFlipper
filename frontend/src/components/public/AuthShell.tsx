import type { ReactNode } from 'react';
import { Check, Hexagon } from 'lucide-react';
import { Link } from 'react-router-dom';
import ThemeSelector from '@/components/ThemeSelector';
import { useTheme } from '@/components/ThemeProvider';
import StippleOrb from './StippleOrb';

export default function AuthShell({ children, eyebrow, title, description, wide = false }: { children: ReactNode; eyebrow: string; title: string; description: string; wide?: boolean }) {
  const { resolvedTheme } = useTheme();
  return <main className={`fc-auth ${wide ? 'fc-auth-wide' : ''}`} data-theme={resolvedTheme}>
    <div className="fc-auth-theme"><ThemeSelector /></div>
    <div className="fc-auth-grid">
      <section className="fc-auth-story" aria-label="Conheça o FlipControl">
        <Link to="/" className="fc-brand"><Hexagon size={24} strokeWidth={1.6} />FlipControl</Link>
        <div className="fc-auth-story-copy"><p className="fc-eyebrow">{eyebrow}</p><h2>{title}</h2><p>{description}</p></div>
        <div className="fc-auth-art"><StippleOrb dark={resolvedTheme === 'dark'} /></div>
        <div className="fc-auth-principles">{['Conta única', 'Produtos independentes', 'Permissões por assinatura'].map(item => <span key={item}><Check size={13} />{item}</span>)}</div>
      </section>
      <section className="fc-auth-form-area"><div className="fc-auth-panel"><Link to="/" className="fc-brand fc-auth-mobile-brand"><Hexagon size={23} strokeWidth={1.6} />FlipControl</Link>{children}</div></section>
    </div>
  </main>;
}
