import { useState } from 'react';
import { CheckCircle2, Mail } from 'lucide-react';
import { Link, useLocation } from 'react-router-dom';
import api from '@/lib/api';
import { getAuthToken } from '@/lib/authToken';
import AuthShell from '@/components/public/AuthShell';

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
    try { const { data } = await api.get(verification.slice(4)); setVerified(true); setMessage(data.message); }
    catch (exception: any) { setError(exception.response?.status === 403 ? 'Este link é inválido ou expirou. Solicite uma nova confirmação.' : 'Não foi possível confirmar agora. Tente novamente.'); }
    finally { setBusy(false); }
  }

  async function resend() {
    if (Date.now() < retryAt) { setError('Aguarde um minuto antes de solicitar outro envio.'); return; }
    setBusy(true); setError('');
    try { const { data } = await api.post('/auth/email/resend'); setMessage(data.message); setVerified(data.delivery === 'verified'); setRetryAt(Date.now() + 60000); }
    catch (exception: any) { setError(exception.response?.status === 429 ? 'Limite de envios atingido. Aguarde antes de tentar novamente.' : exception.response?.data?.message || 'Não foi possível enviar agora. Tente novamente.'); }
    finally { setBusy(false); }
  }

  return <AuthShell eyebrow="Confirmação de identidade" title="Só falta validar seu e-mail." description="A confirmação protege sua conta e libera apenas os produtos vinculados à sua assinatura.">
    <section className="rounded-[2rem] border border-white/10 bg-white/[.075] p-6 text-center shadow-2xl shadow-black/30 backdrop-blur-xl sm:p-9">
      <span className={`mx-auto grid size-14 place-items-center rounded-2xl ${verified ? 'bg-emerald-400/10' : 'bg-cyan-400/10'}`}>{verified ? <CheckCircle2 className="size-7 text-emerald-300" /> : <Mail className="size-7 text-cyan-300" />}</span>
      <h1 className="mt-5 text-2xl font-black">{verified ? 'E-mail confirmado' : 'Confirme seu e-mail'}</h1><p role="status" className="mt-3 text-sm leading-6 text-slate-400">{message}</p>
      {error && <p role="alert" className="mt-5 rounded-xl border border-red-400/20 bg-red-500/10 p-3 text-sm text-red-200">{error}</p>}
      <div className="mt-7 flex flex-col gap-3">{verification && !verified && <button type="button" onClick={confirm} disabled={busy} className="rounded-xl bg-gradient-to-r from-violet-600 to-blue-600 py-3.5 font-black text-white disabled:opacity-60">{busy ? 'Verificando…' : 'Confirmar meu e-mail'}</button>}{authenticated && !verified && <button type="button" onClick={resend} disabled={busy} className="rounded-xl border border-white/10 bg-white/5 px-4 py-3 font-bold text-slate-200 disabled:opacity-60">{busy ? 'Aguarde…' : 'Reenviar confirmação'}</button>}{verified && authenticated ? <a href="/app/produtos" className="rounded-xl bg-gradient-to-r from-violet-600 to-blue-600 py-3.5 text-center font-black text-white">Acessar produtos</a> : <Link to="/login" className="font-bold text-cyan-300">Entrar na conta</Link>}</div>
    </section>
  </AuthShell>;
}
