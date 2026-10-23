import { FormEvent, useEffect, useMemo, useState, useRef } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { Building2, Eye, EyeOff, Home, LineChart } from 'lucide-react';
import api from '@/lib/api';
import { setAuthToken, getAuthToken } from '@/lib/authToken';
import { deviceName } from '@/lib/deviceName';
import { ProductSlug } from '@/lib/products';
import PublicSeo from '@/components/public/PublicSeo';
import { digits, formatCep, formatPhone } from '@/lib/brFields';
import StatefulButton from '@/ui/StatefulButton';
import { readAnalyticsSession } from '@/components/public/AnalyticsConsent';
import { passwordPairError, missingRegistrationField, registrationErrorStep } from '@/lib/registrationValidation';
import AuthShell from '@/components/public/AuthShell';

function RequiredMark() { return <><span className="fc-required" aria-hidden="true"> *</span><span className="sr-only"> (obrigatório)</span></>; }

const products: { slug: ProductSlug; label: string; hint: string }[] = [
  { slug: 'corretor', label: 'Corretor', hint: 'Captação, carteira e site próprio.' },
  { slug: 'imobiliaria', label: 'Imobiliária', hint: 'CRM, equipe, filiais e operação.' },
  { slug: 'house_flipper', label: 'House Flipper', hint: 'Análise, obras e simulador.' },
];

export default function Register() {
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const preselected = params.get('produto') as ProductSlug | null;
  const [step, setStep] = useState(preselected && products.some(item => item.slug === preselected) ? 1 : 0);
  const [product, setProduct] = useState<ProductSlug | ''>(preselected && products.some((item) => item.slug === preselected) ? preselected : '');
  const [person, setPerson] = useState({ name: '', email: '', phone: '', tenant_nome: '', password: '', password_confirmation: '' });
  const [extra, setExtra] = useState({ professional_name: '', creci: '', city: '', company_name: '', region_preference: '', usage_goal: '' });
  const [address, setAddress] = useState({ postal_code: '', street: '', number: '', complement: '', neighborhood: '', city: '', state: '' });
  const [lookingUpCep, setLookingUpCep] = useState(false);
  const [consent, setConsent] = useState(false);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const confirmationInput = useRef<HTMLInputElement>(null);
  const passwordError = passwordPairError(person.password, person.password_confirmation);
  useEffect(() => { confirmationInput.current?.setCustomValidity(passwordError); }, [passwordError, step]);
  function advance(from: number) {
    const message = missingRegistrationField(from, person, extra, address, product) || (from === 1 ? passwordError : '');
    setError(message);
    if (!message) setStep(from + 1);
  }
  const passwordScore = useMemo(() => [person.password.length >= 12, /[A-Z]/.test(person.password), /[a-z]/.test(person.password), /\d/.test(person.password), /[^A-Za-z0-9]/.test(person.password)].filter(Boolean).length, [person.password]);

  const extras = useMemo(() => {
    if (product === 'corretor') return [
      ['professional_name', 'Nome profissional'],
      ['creci', 'CRECI (opcional)'],
      ['city', 'Cidade'],
    ] as const;
    if (product === 'imobiliaria') return [
      ['company_name', 'Nome da empresa'],
      ['city', 'Cidade'],
    ] as const;
    return [
      ['region_preference', 'Região de interesse'],
      ['usage_goal', 'Objetivo principal'],
    ] as const;
  }, [product]);

  useEffect(() => {
    if (getAuthToken()) {
      navigate('/confirmar-email', { replace: true });
    }
  }, [navigate, product]);

  async function submit(event: FormEvent) {
    event.preventDefault();
    setError('');
    if (!product) { setStep(0); setError('Escolha um produto para continuar.'); return; }
    for (const stage of [1, 2, 3]) {
      const missing = missingRegistrationField(stage, person, extra, address, product);
      if (missing) { setStep(stage); setError(missing); return; }
    }
    if (passwordError) { setStep(1); setError(passwordError); return; }
    if (!consent) { setError('Aceite a política de privacidade.'); return; }
    setSaving(true);
    try {
      const { data } = await api.post('/auth/register', {
        ...person,
        tenant_nome: person.tenant_nome,
        phone: person.phone ? digits(person.phone) : null,
        intended_product: product,
        address: { ...address, postal_code: digits(address.postal_code), state: address.state.toUpperCase() },
        ...extra,
        consent: true,
        device_name: deviceName(),
        analytics_session: readAnalyticsSession(),
      });
      setAuthToken(data.token);
      navigate('/confirmar-email', { replace: true, state: { delivery: data.verification_delivery, product } });
    } catch (exception: any) {
      const errors = exception.response?.data?.errors;
      if (errors) setStep(registrationErrorStep(Object.keys(errors)));
      setError(errors ? Object.values(errors).flat().join(' ') : exception.response?.data?.message || 'Não foi possível criar sua conta.');
    } finally {
      setSaving(false);
    }
  }

  async function lookupCep() {
    const postalCode = digits(address.postal_code);
    if (postalCode.length !== 8) return;
    setLookingUpCep(true);
    setError('');
    try {
      const { data } = await api.get(`/auth/postal-code/${postalCode}`);
      const result = data.data ?? data;
      setAddress((current) => ({
        ...current,
        postal_code: formatCep(postalCode),
        street: result.street ?? result.logradouro ?? current.street,
        neighborhood: result.neighborhood ?? result.bairro ?? current.neighborhood,
        city: result.city ?? result.localidade ?? current.city,
        state: String(result.state ?? result.uf ?? current.state).toUpperCase().slice(0, 2),
      }));
    } catch (exception: any) {
      setError(exception.response?.data?.message ?? 'CEP não encontrado. Confira o número ou preencha o endereço manualmente.');
    } finally {
      setLookingUpCep(false);
    }
  }

  return (
    <AuthShell wide eyebrow="Comece com clareza" title="Sua operação imobiliária começa com o produto certo." description="Crie uma conta, escolha seu primeiro produto e mantenha cada operação separada por assinatura e permissão.">
      <PublicSeo title="Cadastro" description="Crie sua conta FlipControl e escolha o produto para testar." path="/cadastro" noindex />
        <section className="rounded-[2rem] border border-white/10 bg-white/[.075] p-6 shadow-2xl shadow-black/30 backdrop-blur-xl sm:p-9">
          <p className="eyebrow">Sua conta FlipControl</p><h1 className="mt-2 text-3xl font-black tracking-tight">Criar conta</h1>
          <p className="mt-2 text-slate-400">Etapa {step + 1} de 5. O produto escolhido não libera os demais.</p>
          <div className="mt-5 h-1.5 overflow-hidden rounded-full bg-white/8" role="progressbar" aria-valuemin={1} aria-valuemax={5} aria-valuenow={step + 1} aria-label={`Etapa ${step + 1} de 5`}><div className="h-full rounded-full bg-gradient-to-r from-violet-500 to-cyan-400 transition-all" style={{ width: `${((step + 1) / 5) * 100}%` }} /></div>
          {step > 0 && <p className="mt-4 text-xs text-slate-400">Campos com <RequiredMark /> são obrigatórios. O CRECI é opcional.</p>}
          {error && <div role="alert" className="mt-5 rounded-xl border border-red-400/20 bg-red-500/10 p-4 text-sm text-red-200">{error}</div>}
          {step === 0 && (
            <div className="mt-6 grid gap-3">
              {products.map((item, index) => { const Icon=[Home,Building2,LineChart][index]; return (
                <button key={item.slug} type="button" onClick={() => { setProduct(item.slug); setStep(1); }} className={`group rounded-2xl border p-5 text-left transition hover:-translate-y-0.5 hover:border-cyan-300/50 hover:bg-white/[.06] hover:shadow-lg ${product === item.slug ? 'border-cyan-400 bg-cyan-400/10' : 'border-white/10 bg-slate-950/45'}`}>
                  <span className="fc-register-product-icon"><Icon size={22} strokeWidth={1.8} aria-hidden="true"/></span><p className="font-semibold">{item.label}</p>
                  <p className="mt-1 text-sm text-slate-400">{item.hint}</p>
                </button>
              )})}
            </div>
          )}
          {step === 1 && (
            <form onSubmit={(event) => { event.preventDefault(); advance(1); }} className="mt-6 space-y-3">
              <label className="block text-sm font-medium">Nome<RequiredMark /><input required className="input mt-1" value={person.name} onChange={(event) => setPerson((current) => ({ ...current, name: event.target.value }))} /></label>
              <label className="block text-sm font-medium">E-mail<RequiredMark /><input required type="email" className="input mt-1" value={person.email} onChange={(event) => setPerson((current) => ({ ...current, email: event.target.value }))} /></label>
              <label className="block text-sm font-medium">Nome da operação<RequiredMark /><input required className="input mt-1" value={person.tenant_nome} onChange={(event) => setPerson((current) => ({ ...current, tenant_nome: event.target.value }))} placeholder="Como sua operação será identificada" /></label>
              <label className="block text-sm font-medium">Senha<RequiredMark /><span className="relative mt-1 block"><input required minLength={12} type={showPassword ? 'text' : 'password'} className="input pr-11" value={person.password} onChange={(event) => setPerson((current) => ({ ...current, password: event.target.value }))} /><button type="button" onClick={()=>setShowPassword(value=>!value)} aria-label={showPassword?'Ocultar senha':'Mostrar senha'} className="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg p-2 text-slate-400">{showPassword?<EyeOff className="size-4"/>:<Eye className="size-4"/>}</button></span></label>
              <label className="block text-sm font-medium">Confirme a senha<RequiredMark /><input ref={confirmationInput} aria-invalid={Boolean(passwordError)} aria-describedby={passwordError ? 'registration-password-error' : undefined} required minLength={12} type={showPassword ? 'text' : 'password'} className="input mt-1" value={person.password_confirmation} onChange={(event) => setPerson((current) => ({ ...current, password_confirmation: event.target.value }))} /></label>
              {passwordError && <p id="registration-password-error" role="alert" className="text-sm text-red-200">{passwordError}</p>}
              <div><div className="flex gap-1" aria-label={`Força da senha: ${passwordScore} de 5`}>{Array.from({length:5}).map((_,index)=><span key={index} className={`h-1.5 flex-1 rounded-full ${index<passwordScore?(passwordScore>=4?'bg-emerald-400':'bg-amber-400'):'bg-white/10'}`}/>)}</div><p className="mt-2 text-xs text-slate-400">Use 12 caracteres, maiúsculas, minúsculas, número e símbolo.</p></div>
              <div className="flex gap-2">
                <button type="button" onClick={() => setStep(0)} className="rounded-lg border px-4 py-2">Voltar</button>
                <button type="submit" className="rounded-lg bg-primary-600 px-4 py-2 text-white">Continuar</button>
              </div>
            </form>
          )}
          {step === 2 && (
            <form onSubmit={(event) => { event.preventDefault(); advance(2); }} className="mt-6 space-y-3">
              {extras.map(([key, label]) => (
                <label key={key} className="block text-sm font-medium">{label}{key !== 'creci' && <RequiredMark />}<input required={key !== 'creci'} className="input mt-1" value={extra[key]} onChange={(event) => setExtra((current) => ({ ...current, [key]: event.target.value }))} /></label>
              ))}
              <label className="block text-sm font-medium">Telefone<RequiredMark /><input required minLength={14} type="tel" inputMode="tel" autoComplete="tel" maxLength={15} className="input mt-1" placeholder="(11) 99999-9999" value={person.phone} onChange={(event) => setPerson((current) => ({ ...current, phone: formatPhone(event.target.value) }))} /></label>
              <div className="flex gap-2">
                <button type="button" onClick={() => setStep(1)} className="rounded-lg border px-4 py-2">Voltar</button>
                <button type="submit" className="rounded-lg bg-primary-600 px-4 py-2 text-white">Continuar</button>
              </div>
            </form>
          )}
          {step === 3 && (
            <form onSubmit={(event) => { event.preventDefault(); advance(3); }} className="mt-6 space-y-3">
              <div className="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                <label className="block text-sm font-medium">CEP<RequiredMark /><input required inputMode="numeric" autoComplete="postal-code" maxLength={9} className="input mt-1" value={address.postal_code} onChange={(event) => setAddress((current) => ({ ...current, postal_code: formatCep(event.target.value) }))} onBlur={lookupCep} placeholder="00000-000" /></label>
                <button type="button" onClick={lookupCep} disabled={lookingUpCep || digits(address.postal_code).length !== 8} className="rounded-lg border px-4 py-2 disabled:opacity-50">{lookingUpCep ? 'Buscando...' : 'Buscar CEP'}</button>
              </div>
              <label className="block text-sm font-medium">Rua<RequiredMark /><input required autoComplete="address-line1" className="input mt-1" value={address.street} onChange={(event) => setAddress((current) => ({ ...current, street: event.target.value }))} /></label>
              <div className="grid gap-3 sm:grid-cols-2">
                <label className="block text-sm font-medium">Número<RequiredMark /><input required autoComplete="address-line2" className="input mt-1" value={address.number} onChange={(event) => setAddress((current) => ({ ...current, number: event.target.value }))} /></label>
                <label className="block text-sm font-medium">Complemento<RequiredMark /><input required placeholder="Se não houver, informe Sem complemento" className="input mt-1" value={address.complement} onChange={(event) => setAddress((current) => ({ ...current, complement: event.target.value }))} /></label>
              </div>
              <label className="block text-sm font-medium">Bairro<RequiredMark /><input required className="input mt-1" value={address.neighborhood} onChange={(event) => setAddress((current) => ({ ...current, neighborhood: event.target.value }))} /></label>
              <div className="grid gap-3 sm:grid-cols-[1fr_7rem]">
                <label className="block text-sm font-medium">Cidade<RequiredMark /><input required autoComplete="address-level2" className="input mt-1" value={address.city} onChange={(event) => setAddress((current) => ({ ...current, city: event.target.value }))} /></label>
                <label className="block text-sm font-medium">UF<RequiredMark /><input required autoComplete="address-level1" maxLength={2} className="input mt-1 uppercase" value={address.state} onChange={(event) => setAddress((current) => ({ ...current, state: event.target.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 2) }))} /></label>
              </div>
              <div className="flex gap-2">
                <button type="button" onClick={() => setStep(2)} className="rounded-lg border px-4 py-2">Voltar</button>
                <button type="submit" className="rounded-lg bg-primary-600 px-4 py-2 text-white">Continuar</button>
              </div>
            </form>
          )}
          {step === 4 && (
            <form onSubmit={submit} className="mt-6 space-y-4">
              <p className="text-sm">Produto: <strong>{products.find((item) => item.slug === product)?.label}</strong></p>
              <p className="text-sm text-slate-400">Após confirmar o e-mail, você poderá iniciar o teste de 7 dias deste produto. Planos pagos continuam por proposta enquanto o checkout estiver desligado.</p>
              <label className="flex items-start gap-2 text-sm">
                <input type="checkbox" required checked={consent} onChange={(event) => setConsent(event.target.checked)} />
                <span>Li a <Link to="/privacidade" className="font-semibold text-cyan-300">política de privacidade</Link> e os <Link to="/termos" className="font-semibold text-cyan-300">termos</Link>.</span>
              </label>
              <div className="flex gap-2">
                <button type="button" onClick={() => setStep(3)} className="rounded-lg border px-4 py-2">Voltar</button>
                <StatefulButton type="submit" state={saving ? 'loading' : 'idle'} loadingLabel="Criando conta…">Criar conta</StatefulButton>
              </div>
            </form>
          )}
          <p className="mt-5 text-center text-sm text-slate-400">Já tem conta? <Link to="/entrar" className="font-bold text-cyan-300">Entrar</Link></p>
        </section>
    </AuthShell>
  );
}
