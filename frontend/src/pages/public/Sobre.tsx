import PublicSeo from '@/components/public/PublicSeo';

export default function Sobre() {
  return (
    <div className="mx-auto max-w-3xl px-4 py-12 space-y-6">
      <PublicSeo title="Sobre" description="FlipControl é a plataforma com três produtos independentes para o mercado imobiliário." path="/sobre" />
      <h1 className="text-3xl font-bold">Sobre a plataforma</h1>
      <p className="text-slate-600">FlipControl organiza o trabalho de corretores, imobiliárias e investidores em produtos separados, com a mesma conta. Não mesclamos assinaturas: cada produto entra só quando contratado ou em teste válido.</p>
      <p className="text-slate-600">Endereço, CNPJ, telefone e certificações oficiais serão publicados quando a empresa cadastrar esses dados. Este site não inventa informações legais.</p>
    </div>
  );
}
