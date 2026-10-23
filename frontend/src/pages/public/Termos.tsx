import { FileCheck } from 'lucide-react';
import PublicSeo from '@/components/public/PublicSeo';

export default function Termos() {
  return (
    <div className="mx-auto max-w-3xl px-4 py-12 space-y-4">
      <PublicSeo title="Termos de uso" description="Termos de uso da plataforma FlipControl." path="/termos" />
      <h1 className="text-3xl font-bold"><FileCheck className="fc-public-heading-icon" aria-hidden="true" />Termos de uso</h1>
      <p className="text-slate-600">Documento em elaboração. A conta dá acesso apenas aos produtos contratados ou em teste válido. Republicação de anúncios de terceiros exige autorização. Pagamentos recorrentes só valem quando o checkout estiver ligado e confirmado.</p>
      <p className="text-slate-600">Razão social, foro e cláusulas comerciais definitivas serão publicadas quando a empresa cadastrar esses dados.</p>
    </div>
  );
}
