import { useState } from 'react';
import DialogShell from '@/ui/DialogShell';
import LeadForm from './LeadForm';
import type { ProductSlug } from '@/lib/products';

export default function CommercialInterestButton({ product = '', label = 'Solicitar demonstração', className = 'premium-button-secondary' }: { product?: ProductSlug | ''; label?: string; className?: string }) {
  const [open, setOpen] = useState(false);
  return <><button type="button" onClick={() => setOpen(true)} className={className}>{label}</button>{open && <DialogShell title="Falar com a equipe comercial" description="Registre seu interesse. O envio não cria uma conta nem simula uma compra." onClose={() => setOpen(false)}><LeadForm intent="demo" product={product} sourcePath={location.pathname} submitLabel="Registrar solicitação" /></DialogShell>}</>;
}
