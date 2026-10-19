import { type FormEvent, useState } from 'react';
import { ArrowLeft, Mail } from 'lucide-react';
import { Link } from 'react-router-dom';
import api from '@/lib/api';
import ThemeSelector from '@/components/ThemeSelector';

export default function ForgotPassword() {
  const [email, setEmail] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [sent, setSent] = useState(false);

  async function submit(event: FormEvent) {
    event.preventDefault(); setBusy(true); setError('');
    try {
      await api.post('/auth/password/forgot', { email });
      setSent(true);
    } catch (exception: any) {
      setError(exception.response?.status === 429 ? 'Muitas solicitações. Aguarde antes de tentar novamente.' : exception.response?.data?.message || 'Não foi possível processar a solicitação agora.');
    } finally { setBusy(false); }
  }

  return <main className="min-h-screen bg-slate-50 dark:bg-slate-950 p-6 flex items-center justify-center">
    <div className="absolute right-4 top-4"><ThemeSelector /></div>
    <section className="w-full max-w-md rounded-3xl border bg-white dark:bg-slate-900 p-8 shadow-sm">
      <Mail aria-hidden="true" className="mx-auto size-12 text-primary-600 dark:text-primary-400" />
      <h1 className="mt-5 text-center text-2xl font-bold">Recuperar senha</h1>
      {sent ? <div className="mt-5 text-center"><p role="status" className="text-slate-600 dark:text-slate-300">Se a conta estiver apta, enviaremos as instruções de redefinição. Verifique também a caixa de spam.</p><button type="button" onClick={() => { setSent(false); setError(''); }} className="mt-5 font-bold text-primary-600 dark:text-primary-400">Solicitar novamente</button></div> : <form onSubmit={submit} className="mt-6 space-y-4"><p className="text-sm text-slate-500 dark:text-slate-400">Informe o e-mail da conta. A resposta é a mesma mesmo quando o endereço não está cadastrado.</p><label className="block text-sm font-medium" htmlFor="forgot-email">E-mail<input id="forgot-email" autoFocus autoComplete="email" type="email" required maxLength={150} value={email} onChange={event => setEmail(event.target.value)} className="input mt-1" /></label>{error && <p role="alert" className="rounded-xl bg-red-50 dark:bg-red-950 p-3 text-sm text-red-700 dark:text-red-300">{error}</p>}<button type="submit" disabled={busy} className="w-full rounded-xl bg-primary-600 py-3 font-bold text-white disabled:opacity-60">{busy ? 'Enviando...' : 'Enviar instruções'}</button></form>}
      <Link to="/login" className="mt-6 flex items-center justify-center gap-2 font-semibold text-primary-600 dark:text-primary-400"><ArrowLeft className="size-4" />Voltar ao login</Link>
    </section>
  </main>;
}
