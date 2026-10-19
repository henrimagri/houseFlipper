export function safeExternalHttpsUrl(value: unknown): string | null {
  if (typeof value !== 'string' || !value.trim()) return null;

  try {
    const url = new URL(value);
    if (url.protocol !== 'https:' || url.username || url.password || (url.port && url.port !== '443')) return null;
    const host = url.hostname.toLowerCase().replace(/\.$/, '').replace(/^\[|\]$/g, '');
    if (!host || host === 'localhost' || host.endsWith('.localhost') || host.endsWith('.local')) return null;
    if (host.includes(':')) return null;
    const octets = host.split('.');
    if (octets.length === 4 && octets.every(octet => /^\d+$/.test(octet) && Number(octet) <= 255)) {
      const [a, b] = octets.map(Number);
      const nonPublic = a === 0 || a === 10 || a === 127 || a >= 224
        || (a === 100 && b >= 64 && b <= 127)
        || (a === 169 && b === 254)
        || (a === 172 && b >= 16 && b <= 31)
        || (a === 192 && b === 168)
        || (a === 198 && (b === 18 || b === 19));
      if (nonPublic) return null;
    }
    return url.href;
  } catch {
    return null;
  }
}
