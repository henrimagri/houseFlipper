import { type FormEvent, useMemo, useState } from 'react';
import { ArrowLeft, CheckCircle2, Eye, EyeOff, KeyRound } from 'lucide-react';
import { Link } from 'react-router-dom';
import api from '@/lib/api';
import { clearAuthToken } from '@/lib/authToken';
import { parseResetFragment } from '@/lib/passwordReset';
import AuthShell from '@/components/public/AuthShell';

export default function ResetPassword() {
  const [credentials] = useState(() => parseResetFragment(window.location.hash));
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [complete, setComplete] = useState(false);
  const passwordScore = useMemo(() => [password.length >= 12, /[A-Z]/.test(password), /[a-z]/.test(password), /\d/.test(password), /[^A-Za-z0-9]/.test(password)].filter(Boolean).length, [password]);

  async function submit(event: FormEvent) {
    event.preventDefault();
    if (busy) return;
    setError('');
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

  return <AuthShell eyebrow="Nova credencial" title="Crie uma senha forte e exclusiva." description="A alteração encerra as sessões anteriores e protege sua conta com uma nova credencial.">
    <section className="rounded-[2rem] border border-white/10 bg-white/[.075] p-6 shadow-2xl shadow-black/30 backdrop-blur-xl sm:p-9">
      {complete ? <div className="text-center"><span className="mx-auto grid size-14 place-items-center rounded-2xl bg-emerald-400/10"><CheckCircle2 className="size-7 text-emerald-300" /></span><h1 className="mt-5 text-2xl font-black">Senha redefinida</h1><p role="status" className="mt-3 text-sm leading-6 text-slate-400">Todas as sessões anteriores foram encerradas. Entre novamente com a nova senha.</p><Link to="/login" className="mt-7 block rounded-xl bg-gradient-to-r from-violet-600 to-blue-600 py-3.5 font-black text-white">Entrar com a nova senha</Link></div> : credentials ? <><span className="grid size-12 place-items-center rounded-2xl bg-cyan-400/10"><KeyRound className="size-6 text-cyan-300" /></span><h1 className="mt-5 text-3xl font-black">Redefinir senha</h1><p className="mt-2 text-sm leading-6 text-slate-400">Conta <strong className="text-slate-200">{credentials.email}</strong>. Use entre 12 caracteres e 72 bytes.</p><form onSubmit={submit} className="mt-7 space-y-4"><label className="block text-sm font-bold text-slate-200" htmlFor="new-password">Nova senha<span className="relative mt-2 block"><input id="new-password" autoFocus autoComplete="new-password" type={showPassword ? 'text' : 'password'} required minLength={12} maxLength={72} value={password} onChange={event => setPassword(event.target.value)} className="input pr-11" /><button type="button" onClick={() => setShowPassword(value => !value)} aria-label={showPassword ? 'Ocultar senha' : 'Mostrar senha'} className="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg p-2 text-slate-400 hover:text-white">{showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}</button></span></label><div><div className="flex gap-1" aria-label={`Força da senha: ${passwordScore} de 5`}>{Array.from({ length: 5 }).map((_, index) => <span key={index} className={`h-1.5 flex-1 rounded-full ${index < passwordScore ? (passwordScore >= 4 ? 'bg-emerald-400' : 'bg-amber-400') : 'bg-white/10'}`} />)}</div><p className="mt-2 text-xs text-slate-400">Use maiúsculas, minúsculas, número e símbolo.</p></div><label className="block text-sm font-bold text-slate-200" htmlFor="new-password-confirmation">Confirme a nova senha<input id="new-password-confirmation" autoComplete="new-password" type={showPassword ? 'text' : 'password'} required minLength={12} maxLength={72} value={confirmation} onChange={event => setConfirmation(event.target.value)} className="input mt-2" /></label>{error && <p role="alert" className="rounded-xl border border-red-400/20 bg-red-500/10 p-3 text-sm text-red-200">{error}</p>}<button type="submit" disabled={busy} className="w-full rounded-xl bg-gradient-to-r from-violet-600 to-blue-600 py-3.5 font-black text-white disabled:opacity-60">{busy ? 'Redefinindo…' : 'Redefinir senha'}</button></form><Link to="/login" className="mt-7 flex items-center justify-center gap-2 text-sm font-bold text-cyan-300"><ArrowLeft className="size-4" />Voltar ao login</Link></> : <div className="text-center"><span className="mx-auto grid size-14 place-items-center rounded-2xl bg-amber-400/10"><KeyRound className="size-7 text-amber-300" /></span><h1 className="mt-5 text-2xl font-black">Link inválido</h1><p role="alert" className="mt-3 text-sm leading-6 text-slate-400">O link está incompleto ou expirou. Solicite uma nova redefinição.</p><Link to="/esqueci-senha" className="mt-7 block rounded-xl bg-gradient-to-r from-violet-600 to-blue-600 py-3.5 font-black text-white">Solicitar novo link</Link></div>}
    </section>
  </AuthShell>;
}
