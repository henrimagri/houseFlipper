import { Link } from 'react-router-dom';
import PublicSeo from '@/components/public/PublicSeo';
import FAQAccordion from '@/components/public/FAQAccordion';

export default function AjudaPublica() {
  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <PublicSeo title="Ajuda" description="Perguntas frequentes e canais de contato da FlipControl." path="/ajuda" />
      <h1 className="text-3xl font-bold">Ajuda</h1>
      <p className="mt-3 text-slate-600">Contas autenticadas também encontram ajuda operacional em /app/ajuda.</p>
      <div className="mt-8"><FAQAccordion /></div>
      <p className="mt-6 text-sm"><Link to="/contato" className="font-semibold text-primary-700">Falar com a equipe</Link></p>
    </div>
  );
}
