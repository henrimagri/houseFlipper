import { ShieldCheck } from 'lucide-react';
import PublicSeo from '@/components/public/PublicSeo';

export default function Privacidade() {
  return (
    <div className="mx-auto max-w-3xl px-4 py-12 space-y-4">
      <PublicSeo title="Política de privacidade" description="Como a FlipControl trata dados de contas e de solicitações comerciais." path="/privacidade" />
      <h1 className="text-3xl font-bold"><ShieldCheck className="fc-public-heading-icon" aria-hidden="true" />Política de privacidade</h1>
      <p className="text-slate-600">Coletamos nome, e-mail e, quando informados, telefone e mensagem para criar a conta ou atender solicitações comerciais. Finalidade: cadastro, autenticação, teste de produto e contato comercial.</p>
      <p className="text-slate-600">Leads do site institucional ficam na fila interna da plataforma, separados do CRM de compradores de imóveis. Endereço IP é armazenado apenas como hash. Senhas são armazenadas com hash; não entram em logs.</p>
      <p className="text-slate-600">Retenção: solicitações comerciais permanecem enquanto o atendimento estiver aberto e pelo prazo necessário às obrigações legais. Titulares podem pedir acesso ou exclusão pelo formulário de contato.</p>
    </div>
  );
}
