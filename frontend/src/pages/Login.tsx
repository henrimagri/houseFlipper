import { FormEvent, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Hexagon } from 'lucide-react';
import api from '@/lib/api';
import { setAuthToken } from '@/lib/authToken';
import ThemeSelector from '@/components/ThemeSelector';
import { deviceName } from '@/lib/deviceName';

export default function Login() {
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [erro, setErro] = useState<string | null>(null);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setLoading(true);
    setErro(null);
    try {
      const { data } = await api.post('/auth/login', { email, password, device_name: deviceName() });
      setAuthToken(data.token);
      navigate(data.requires_email_verification ? '/confirmar-email' : '/');
    } catch (err: any) {
      setErro(err.response?.data?.message ?? 'Falha no login');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-linear-to-br from-primary-600 to-primary-900 p-4">
      <div className="absolute top-4 right-4"><ThemeSelector /></div>
      <div className="w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-2xl p-8">
        <div className="flex items-center gap-2 justify-center mb-6">
          <div className="w-10 h-10 bg-primary-600 rounded-lg flex items-center justify-center">
            <Hexagon className="w-6 h-6 text-white" />
          </div>
          <div>
            <div className="font-bold text-gray-900 dark:text-slate-100 text-lg leading-tight">FlipControl</div>
            <div className="text-xs text-gray-500 dark:text-slate-400">House Flipper</div>
          </div>
        </div>

        <h1 className="text-xl font-semibold text-gray-900 dark:text-slate-100 text-center">Bem-vindo de volta</h1>
        <p className="text-sm text-gray-500 dark:text-slate-400 text-center mt-1">Acesse seu painel</p>

        {erro && (
          <div className="mt-4 bg-red-50 dark:bg-red-950 text-red-700 dark:text-red-300 text-sm p-3 rounded-lg">{erro}</div>
        )}

        <form onSubmit={handleSubmit} className="mt-6 space-y-4">
          <div>
            <label htmlFor="login-email" className="text-xs font-medium text-gray-700 dark:text-slate-200">E-mail</label>
            <input
              type="email"
              id="login-email"
              autoComplete="username"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              className="mt-1 w-full border border-gray-200 dark:border-slate-700 rounded-lg px-3 py-2.5 text-sm focus:outline-hidden focus:ring-2 focus:ring-primary-500"
            />
          </div>
          <div>
            <div className="flex items-center justify-between gap-3"><label htmlFor="login-password" className="text-xs font-medium text-gray-700 dark:text-slate-200">Senha</label><Link to="/esqueci-senha" className="text-xs font-bold text-primary-600 dark:text-primary-400">Esqueci minha senha</Link></div>
            <input
              type="password"
              id="login-password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              className="mt-1 w-full border border-gray-200 dark:border-slate-700 rounded-lg px-3 py-2.5 text-sm focus:outline-hidden focus:ring-2 focus:ring-primary-500"
            />
          </div>
          <button
            type="submit"
            disabled={loading}
            className="w-full bg-primary-600 hover:bg-primary-700 disabled:opacity-60 text-white text-sm font-semibold py-2.5 rounded-lg shadow-xs"
          >
            {loading ? 'Entrando...' : 'Entrar'}
          </button>
        </form>
        <p className="mt-5 text-center text-sm text-slate-600 dark:text-slate-300">Ainda não tem conta? <Link to="/cadastro" className="font-bold text-primary-600 dark:text-primary-400">Criar conta</Link></p>
      </div>
    </div>
  );
}
