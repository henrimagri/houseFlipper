import { Presentation } from 'lucide-react';
import { useSearchParams } from 'react-router-dom';
import PublicSeo from '@/components/public/PublicSeo';
import LeadForm from '@/components/public/LeadForm';
import { ProductSlug } from '@/lib/products';

export default function SolicitarDemonstracao() {
  const [params] = useSearchParams();
  const product = (params.get('produto') || '') as ProductSlug | '';
  const intent = params.get('intent') === 'proposal' ? 'proposal' : 'demo';
  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <PublicSeo title="Solicitar demonstração" description="Registre uma demonstração ou proposta comercial da FlipControl." path="/solicitar-demonstracao" />
      <h1 className="text-3xl font-bold"><Presentation className="fc-public-heading-icon" aria-hidden="true" />{intent === 'proposal' ? 'Solicitar proposta empresarial' : 'Solicitar demonstração'}</h1>
      <p className="mt-3 text-slate-600">A solicitação entra na fila interna. Não inicia assinatura paga.</p>
      <div className="mt-8">
        <LeadForm intent={intent} product={product} sourcePath="/solicitar-demonstracao" submitLabel={intent === 'proposal' ? 'Solicitar proposta' : 'Solicitar demonstração'} />
      </div>
    </div>
  );
}
