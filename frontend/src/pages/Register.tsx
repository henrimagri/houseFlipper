import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Hexagon, Loader2 } from 'lucide-react';
import api from '@/lib/api';
import { setAuthToken } from '@/lib/authToken';
import ThemeSelector from '@/components/ThemeSelector';
import { deviceName } from '@/lib/deviceName';

const states = 'AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO'.split(' ');
const initialAddress = { postal_code: '', street: '', number: '', complement: '', neighborhood: '', city: '', state: '' };
export default function Register() {
  const navigate = useNavigate();
  const [person, setPerson] = useState({ name: '', email: '', phone: '', tenant_nome: '', password: '', password_confirmation: '' });
  const [address, setAddress] = useState(initialAddress);
  const [error, setError] = useState('');
  const [postalMessage, setPostalMessage] = useState('');
  const [saving, setSaving] = useState(false);
  const [postalLoading, setPostalLoading] = useState(false);
  const postalRequest = useRef<AbortController | null>(null);
  useEffect(() => () => postalRequest.current?.abort(), []);

  async function lookupPostalCode() {
    if (address.postal_code.length !== 8) { setPostalMessage('Informe os oito números do CEP.'); return; }
    postalRequest.current?.abort();
    const controller = new AbortController(); postalRequest.current = controller;
    setPostalLoading(true); setPostalMessage('');
    try {
      const { data } = await api.get(`/auth/postal-code/${address.postal_code}`, { signal: controller.signal });
      if (!controller.signal.aborted) {
        setAddress(current => ({ ...current, street: data.street, neighborhood: data.neighborhood, city: data.city, state: data.state }));
        setPostalMessage('Endereço encontrado. Confira os dados e informe o número.');
      }
    } catch (exception: any) {
      if (!controller.signal.aborted) setPostalMessage(exception.response?.data?.message || 'Não foi possível consultar o CEP. Preencha o endereço manualmente.');
    } finally { if (!controller.signal.aborted) setPostalLoading(false); }
  }

  async function submit(event: FormEvent) {
    event.preventDefault(); setError('');
    if (person.password !== person.password_confirmation) { setError('As senhas não coincidem.'); return; }
    setSaving(true);
    try {
      const { data } = await api.post('/auth/register', { ...person, address, device_name: deviceName() });
      setAuthToken(data.token);
      navigate('/confirmar-email', { replace: true, state: { delivery: data.verification_delivery } });
    } catch (exception: any) {
      const errors = exception.response?.data?.errors;
      setError(errors ? Object.values(errors).flat().join(' ') : exception.response?.data?.message || 'Não foi possível criar sua conta. Tente novamente.');
    } finally { setSaving(false); }
  }
  const field = (key: keyof typeof person, label: string, type = 'text', autoComplete?: string) => <label className="block text-sm font-medium">{label}<input className="input mt-1" type={type} value={person[key]} onChange={event => setPerson(current => ({ ...current, [key]: event.target.value }))} autoComplete={autoComplete} required minLength={key.startsWith('password') ? 12 : undefined} maxLength={key.startsWith('password') ? 72 : key === 'phone' ? 20 : 150} /></label>;
  const addressField = (key: keyof typeof initialAddress, label: string, maxLength: number, required = true) => <label className="block text-sm font-medium">{label}<input className="input mt-1" value={address[key]} onChange={event => setAddress(current => ({ ...current, [key]: event.target.value }))} maxLength={maxLength} required={required} /></label>;
  return <main className="min-h-screen bg-slate-50 dark:bg-slate-950 px-4 py-8">
    <div className="mx-auto max-w-3xl"><div className="mb-5 flex justify-between items-center gap-3"><Link to="/login" className="flex items-center gap-2 font-bold"><Hexagon className="text-primary-600 size-7" aria-hidden="true" />FlipControl</Link><ThemeSelector /></div>
      <section className="rounded-3xl border bg-white dark:bg-slate-900 p-6 sm:p-8 shadow-sm"><h1 className="text-3xl font-bold">Crie sua conta</h1><p className="mt-2 text-slate-500 dark:text-slate-400">Cadastre seus dados e confirme seu e-mail para começar.</p>
        {error && <div role="alert" className="mt-5 rounded-xl bg-red-50 dark:bg-red-950 p-4 text-sm text-red-700 dark:text-red-300">{error}</div>}
        <form onSubmit={submit} className="mt-6 space-y-7">
          <fieldset disabled={saving} className="grid gap-4 sm:grid-cols-2"><legend className="mb-3 font-bold">Seus dados</legend>{field('name', 'Nome completo', 'text', 'name')}{field('tenant_nome', 'Nome da empresa ou operação', 'text', 'organization')}{field('email', 'E-mail', 'email', 'email')}{field('phone', 'Telefone com DDD', 'tel', 'tel')}{field('password', 'Senha', 'password', 'new-password')}{field('password_confirmation', 'Confirme a senha', 'password', 'new-password')}<p className="text-xs text-slate-500 dark:text-slate-400 sm:col-span-2">Use ao menos 12 caracteres. A senha aceita até 72 bytes.</p></fieldset>
          <fieldset disabled={saving} className="grid gap-4 sm:grid-cols-2"><legend className="mb-3 font-bold">Endereço</legend>
            <div><label className="block text-sm font-medium">CEP<input className="input mt-1" inputMode="numeric" autoComplete="postal-code" required maxLength={9} value={address.postal_code.replace(/^(\d{5})(\d)/, '$1-$2')} onChange={event => { postalRequest.current?.abort(); setPostalLoading(false); setPostalMessage(''); setAddress(current => ({ ...current, postal_code: event.target.value.replace(/\D/g, '').slice(0, 8) })); }} /></label><button type="button" onClick={lookupPostalCode} disabled={postalLoading} className="mt-2 inline-flex items-center gap-2 text-sm font-semibold text-primary-600 dark:text-primary-400">{postalLoading && <Loader2 aria-hidden="true" className="size-4 animate-spin" />}Consultar CEP</button></div>
            {addressField('street', 'Rua ou avenida', 180)}{addressField('number', 'Número (ou s/n)', 20)}{addressField('complement', 'Complemento (opcional)', 120, false)}{addressField('neighborhood', 'Bairro', 120)}{addressField('city', 'Cidade', 120)}<label className="block text-sm font-medium">Estado<select className="input mt-1" required value={address.state} onChange={event => setAddress(current => ({ ...current, state: event.target.value }))}><option value="">Selecione</option>{states.map(state => <option key={state}>{state}</option>)}</select></label>
            {postalMessage && <p role="status" className="text-sm text-slate-600 dark:text-slate-300 sm:col-span-2">{postalMessage}</p>}
          </fieldset>
          <button type="submit" disabled={saving} className="w-full rounded-xl bg-primary-600 px-4 py-3 font-bold text-white hover:bg-primary-700 disabled:opacity-60">{saving ? 'Criando conta...' : 'Criar conta e confirmar e-mail'}</button>
        </form><p className="mt-5 text-center text-sm text-slate-600 dark:text-slate-300">Já tem uma conta? <Link to="/login" className="font-bold text-primary-600 dark:text-primary-400">Entrar</Link></p>
      </section>
    </div>
  </main>;
}
