import { Link } from 'react-router-dom';
import { AlertTriangle, ArrowLeft, Home, RotateCcw } from 'lucide-react';

export default function ErrorPage({ unexpected = false }: { unexpected?: boolean }) {
  return <main className="fc-error min-h-screen bg-slate-50 dark:bg-slate-950 flex items-center justify-center p-6">
    <section className="w-full max-w-xl rounded-3xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-8 sm:p-12 text-center shadow-sm" aria-labelledby="error-title">
      <div className="mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400"><AlertTriangle aria-hidden="true" className="size-8" /></div>
      <p className="text-sm font-bold text-primary-600 dark:text-primary-400">{unexpected ? 'Algo deu errado' : 'Erro 404'}</p>
      <h1 id="error-title" className="mt-3 text-3xl font-black text-slate-900 dark:text-slate-100">{unexpected ? 'Não foi possível exibir esta tela' : 'Página não encontrada'}</h1>
      <p className="mt-4 text-slate-600 dark:text-slate-300">{unexpected ? 'Recarregue a página para tentar novamente. Se a falha continuar, volte ao início.' : 'O endereço pode ter mudado ou estar incompleto. Confira o link ou volte para uma página conhecida.'}</p>
      <div className="mt-8 flex flex-wrap justify-center gap-3">
        {unexpected ? <button type="button" onClick={() => window.location.reload()} className="inline-flex items-center gap-2 rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-3 font-semibold text-slate-700 dark:text-slate-200"><RotateCcw aria-hidden="true" className="size-4" />Tentar novamente</button>
          : <button type="button" onClick={() => window.history.length > 1 ? window.history.back() : window.location.assign('/')} className="inline-flex items-center gap-2 rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-3 font-semibold text-slate-700 dark:text-slate-200"><ArrowLeft aria-hidden="true" className="size-4" />Voltar</button>}
        <Link to="/" reloadDocument={unexpected} className="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-3 font-semibold text-white hover:bg-primary-700"><Home aria-hidden="true" className="size-4" />Ir para o início</Link>
      </div>
    </section>
  </main>;
}
