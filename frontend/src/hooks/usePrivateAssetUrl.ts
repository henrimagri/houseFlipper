import { useEffect, useState } from 'react';
import api from '@/lib/api';

export function usePrivateAssetUrl(source: string | null | undefined) {
  const [url, setUrl] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    let objectUrl: string | null = null;
    setUrl(null);
    if (!source) return () => { active = false; };
    const endpoint = source.startsWith('/api/') ? source.slice(4) : source;
    void api.get(endpoint, { responseType: 'blob' }).then(response => {
      if (!active) return;
      objectUrl = URL.createObjectURL(response.data);
      setUrl(objectUrl);
    }).catch((error) => {
      console.warn('Falha ao carregar ativo privado', error?.response?.status ?? 'network');
      if (active) setUrl('');
    });
    return () => {
      active = false;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [source]);

  return url;
}
