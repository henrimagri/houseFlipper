import axios from 'axios';
import { clearAuthToken, getAuthToken } from '@/lib/authToken';
import { isPublicAuthRequest, shouldClearSessionAfterUnauthorized } from '@/lib/authRequest';
import { productFromPath, readStoredProduct } from '@/lib/products';

const api = axios.create({
  baseURL: '/api',
  headers: { Accept: 'application/json' },
});

api.interceptors.request.use((config) => {
  const token = getAuthToken();
  if (token && !isPublicAuthRequest(config.url)) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  const product = typeof window !== 'undefined'
    ? (productFromPath(window.location.pathname) ?? readStoredProduct())
    : null;
  if (product) {
    config.headers['X-Product-Context'] = product;
  }
  return config;
});

api.interceptors.response.use(
  (r) => r,
  (error) => {
    if (error.response?.status === 401 && shouldClearSessionAfterUnauthorized(error.config?.url, error.config?.headers?.Authorization)) {
      clearAuthToken();
      const path = window.location.pathname;
      if (path.startsWith('/app') || path.startsWith('/platform')) {
        window.location.href = '/entrar';
      }
    }
    if (error.response?.status === 403 && error.response?.data?.code === 'email_verification_required' && window.location.pathname !== '/confirmar-email') {
      window.location.href = '/confirmar-email';
    }
    return Promise.reject(error);
  },
);

export default api;
