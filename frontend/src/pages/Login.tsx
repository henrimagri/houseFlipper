import { FormEvent, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Hexagon } from 'lucide-react';
import api from '@/lib/api';

export default function Login() {
  const navigate = useNavigate();
  const [email, setEmail] = useState('admin@houseflipper.local');
  const [password, setPassword] = useState('password');
  const [loading, setLoading] = useState(false);
  const [erro, setErro] = useState<string | null>(null);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setLoading(true);
    setErro(null);
    try {
      const { data } = await api.post('/auth/login', { email, password, device_name: 'web' });
      localStorage.setItem('token', data.token);
      navigate('/');
    } catch (err: any) {
      setErro(err.response?.data?.message ?? 'Falha no login');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary-600 to-primary-900 p-4">
      <div className="w-full max-w-md bg-white rounded-2xl shadow-2xl p-8">
        <div className="flex items-center gap-2 justify-center mb-6">
          <div className="w-10 h-10 bg-primary-600 rounded-lg flex items-center justify-center">
            <Hexagon className="w-6 h-6 text-white" />
          </div>
          <div>
            <div className="font-bold text-gray-900 text-lg leading-tight">FlipControl</div>
            <div className="text-xs text-gray-500">House Flipper</div>
          </div>
        </div>

        <h1 className="text-xl font-semibold text-gray-900 text-center">Bem-vindo de volta</h1>
        <p className="text-sm text-gray-500 text-center mt-1">Acesse seu painel</p>

        {erro && (
          <div className="mt-4 bg-red-50 text-red-700 text-sm p-3 rounded-lg">{erro}</div>
        )}

        <form onSubmit={handleSubmit} className="mt-6 space-y-4">
          <div>
            <label className="text-xs font-medium text-gray-700">E-mail</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              className="mt-1 w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
          </div>
          <div>
            <label className="text-xs font-medium text-gray-700">Senha</label>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              className="mt-1 w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
          </div>
          <button
            type="submit"
            disabled={loading}
            className="w-full bg-primary-600 hover:bg-primary-700 disabled:opacity-60 text-white text-sm font-semibold py-2.5 rounded-lg shadow-sm"
          >
            {loading ? 'Entrando...' : 'Entrar'}
          </button>
        </form>
      </div>
    </div>
  );
}
