import { type FormEvent, useState } from 'react';
import { CheckCircle2, KeyRound } from 'lucide-react';
import { Link } from 'react-router-dom';
import api from '@/lib/api';
import { clearAuthToken } from '@/lib/authToken';
import { parseResetFragment } from '@/lib/passwordReset';
import ThemeSelector from '@/components/ThemeSelector';

export default function ResetPassword() {
  const [credentials] = useState(() => parseResetFragment(window.location.hash));
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [complete, setComplete] = useState(false);

  async function submit(event: FormEvent) {
    event.preventDefault(); setError('');
    if (!credentials) { setError('O link é inválido. Solicite uma nova redefinição.'); return; }
    if (password !== confirmation) { setError('As senhas não coincidem.'); return; }
    setBusy(true);
    try {
      await api.post('/auth/password/reset', { email: credentials.email, token: credentials.token, password, password_confirmation: confirmation });
      clearAuthToken();
      window.history.replaceState(null, '', window.location.pathname);
      setComplete(true); setPassword(''); setConfirmation('');
    } catch (exception: any) {
      setError(exception.response?.status === 429 ? 'Muitas tentativas. Aguarde antes de tentar novamente.' : Object.values(exception.response?.data?.errors ?? {}).flat().join(' ') || exception.response?.data?.message || 'Não foi possível redefinir a senha.');
    } finally { setBusy(false); }
  }

  return <main className="min-h-screen bg-slate-50 dark:bg-slate-950 p-6 flex items-center justify-center">
    <div className="absolute right-4 top-4"><ThemeSelector /></div>
    <section className="w-full max-w-md rounded-3xl border bg-white dark:bg-slate-900 p-8 shadow-sm text-center">
      {complete ? <CheckCircle2 aria-hidden="true" className="mx-auto size-12 text-emerald-600 dark:text-emerald-400" /> : <KeyRound aria-hidden="true" className="mx-auto size-12 text-primary-600 dark:text-primary-400" />}
      <h1 className="mt-5 text-2xl font-bold">{complete ? 'Senha redefinida' : 'Crie uma nova senha'}</h1>
      {complete ? <><p role="status" className="mt-4 text-slate-600 dark:text-slate-300">Todas as sessões anteriores foram encerradas. Entre novamente com a nova senha.</p><Link to="/login" className="mt-6 block rounded-xl bg-primary-600 py-3 font-bold text-white">Entrar</Link></> : credentials ? <form onSubmit={submit} className="mt-6 space-y-4 text-left"><p className="text-sm text-slate-500 dark:text-slate-400">Conta: <strong>{credentials.email}</strong>. Use entre 12 caracteres e 72 bytes.</p><label className="block text-sm font-medium" htmlFor="new-password">Nova senha<input id="new-password" autoFocus autoComplete="new-password" type="password" required minLength={12} maxLength={72} value={password} onChange={event => setPassword(event.target.value)} className="input mt-1" /></label><label className="block text-sm font-medium" htmlFor="new-password-confirmation">Confirme a nova senha<input id="new-password-confirmation" autoComplete="new-password" type="password" required minLength={12} maxLength={72} value={confirmation} onChange={event => setConfirmation(event.target.value)} className="input mt-1" /></label>{error && <p role="alert" className="rounded-xl bg-red-50 dark:bg-red-950 p-3 text-sm text-red-700 dark:text-red-300">{error}</p>}<button type="submit" disabled={busy} className="w-full rounded-xl bg-primary-600 py-3 font-bold text-white disabled:opacity-60">{busy ? 'Redefinindo...' : 'Redefinir senha'}</button></form> : <><p role="alert" className="mt-4 rounded-xl bg-red-50 dark:bg-red-950 p-3 text-sm text-red-700 dark:text-red-300">O link está incompleto ou inválido.</p><Link to="/esqueci-senha" className="mt-6 block rounded-xl bg-primary-600 py-3 font-bold text-white">Solicitar novo link</Link></>}
      {!complete && <Link to="/login" className="mt-5 block font-semibold text-primary-600 dark:text-primary-400">Voltar ao login</Link>}
    </section>
  </main>;
}
