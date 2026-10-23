import { MessageCircle } from 'lucide-react';
import PublicSeo from '@/components/public/PublicSeo';
import LeadForm from '@/components/public/LeadForm';

export default function Contato() {
  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <PublicSeo title="Contato" description="Fale com a equipe comercial da FlipControl." path="/contato" />
      <h1 className="text-3xl font-bold"><MessageCircle className="fc-public-heading-icon" aria-hidden="true" />Fale com vendas</h1>
      <p className="mt-3 text-slate-600">Use o formulário. Não publicamos telefone ou endereço enquanto esses dados não estiverem cadastrados.</p>
      <div className="mt-8"><LeadForm intent="contact" sourcePath="/contato" submitLabel="Enviar mensagem" /></div>
    </div>
  );
}
