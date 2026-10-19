import { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { CheckCircle2, Mail } from 'lucide-react';
import api from '@/lib/api';
import { getAuthToken } from '@/lib/authToken';
import ThemeSelector from '@/components/ThemeSelector';

export default function VerifyEmail() {
  const location = useLocation();
  const verification = new URLSearchParams(location.search).get('verification');
  const delivery = location.state?.delivery;
  const [message, setMessage] = useState(delivery === 'local' ? 'A conta foi criada. O envio externo ainda não está configurado neste ambiente.' : delivery === 'failed' ? 'A conta foi criada, mas o envio falhou. Você pode solicitar outra confirmação.' : 'Use o link recebido por e-mail para confirmar sua conta.');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [verified, setVerified] = useState(false);
  const [retryAt, setRetryAt] = useState(0);
  const authenticated = !!getAuthToken();

  async function confirm() {
    if (!verification || !/^\/api\/auth\/email\/verify\/\d+\/[a-f0-9]{40}\?/.test(verification)) { setError('Link de confirmação inválido. Solicite um novo e-mail.'); return; }
    setBusy(true); setError('');
    try {
      const { data } = await api.get(verification.slice(4));
      setVerified(true); setMessage(data.message);
    } catch (exception: any) {
      setError(exception.response?.status === 403 ? 'Este link é inválido ou expirou. Solicite uma nova confirmação.' : 'Não foi possível confirmar agora. Tente novamente.');
    } finally { setBusy(false); }
  }

  async function resend() {
    if (Date.now() < retryAt) { setError('Aguarde um minuto antes de solicitar outro envio.'); return; }
    setBusy(true); setError('');
    try {
      const { data } = await api.post('/auth/email/resend');
      setMessage(data.message); setVerified(data.delivery === 'verified'); setRetryAt(Date.now() + 60000);
    } catch (exception: any) {
      setError(exception.response?.status === 429 ? 'Limite de envios atingido. Aguarde antes de tentar novamente.' : exception.response?.data?.message || 'Não foi possível enviar agora. Tente novamente.');
    } finally { setBusy(false); }
  }

  return <main className="min-h-screen bg-slate-50 dark:bg-slate-950 p-6 flex items-center justify-center">
    <div className="absolute right-4 top-4"><ThemeSelector /></div>
    <section className="w-full max-w-lg rounded-3xl border bg-white dark:bg-slate-900 p-8 text-center shadow-sm">
      {verified ? <CheckCircle2 aria-hidden="true" className="mx-auto size-12 text-emerald-600 dark:text-emerald-400" /> : <Mail aria-hidden="true" className="mx-auto size-12 text-primary-600 dark:text-primary-400" />}
      <h1 className="mt-5 text-2xl font-bold">{verified ? 'E-mail confirmado' : 'Confirme seu e-mail'}</h1>
      <p role="status" className="mt-4 text-slate-600 dark:text-slate-300">{message}</p>
      {error && <p role="alert" className="mt-4 rounded-xl bg-red-50 dark:bg-red-950 p-3 text-sm text-red-700 dark:text-red-300">{error}</p>}
      <div className="mt-6 flex flex-col gap-3">
        {verification && !verified && <button type="button" onClick={confirm} disabled={busy} className="rounded-xl bg-primary-600 py-3 font-bold text-white disabled:opacity-60">{busy ? 'Verificando...' : 'Confirmar meu e-mail'}</button>}
        {authenticated && !verified && <button type="button" onClick={resend} disabled={busy} className="rounded-xl border px-4 py-3 font-semibold disabled:opacity-60">{busy ? 'Aguarde...' : 'Reenviar confirmação'}</button>}
        {verified && authenticated ? <Link to="/" className="rounded-xl bg-primary-600 py-3 font-bold text-white">Acessar painel</Link> : <Link to="/login" className="font-semibold text-primary-600 dark:text-primary-400">Entrar na conta</Link>}
      </div>
    </section>
  </main>;
}
