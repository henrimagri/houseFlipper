import net from 'node:net';
import dns from 'node:dns/promises';

const blockedHostnames = new Set([
  'localhost',
  'metadata.google.internal',
  'metadata.aws.internal',
]);

export function isPublicHttpUrl(value) {
  let url;
  try {
    url = new URL(value);
  } catch {
    return false;
  }

  if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password) return false;

  const hostname = url.hostname.replace(/^\[|\]$/g, '').toLowerCase();
  if (!hostname || blockedHostnames.has(hostname) || hostname.endsWith('.localhost') || hostname.endsWith('.local')) return false;

  const ipVersion = net.isIP(hostname);
  if (ipVersion === 4) return isPublicIpv4(hostname);
  if (ipVersion === 6) return isPublicIpv6(hostname);

  return true;
}

export function isAllowedHttpsUrl(value, allowedHosts) {
  if (!isPublicHttpUrl(value) || !Array.isArray(allowedHosts) || allowedHosts.length === 0) return false;

  const parsed = new URL(value);
  if (parsed.protocol !== 'https:') return false;
  const hostname = parsed.hostname.replace(/^\[|\]$/g, '').toLowerCase();

  return allowedHosts.some((allowedHost) => {
    const allowed = String(allowedHost || '').trim().toLowerCase().replace(/^\.+|\.+$/g, '');
    return allowed !== '' && (hostname === allowed || hostname.endsWith(`.${allowed}`));
  });
}

export async function isPublicNetworkTarget(value, resolver = dns.lookup) {
  if (!isPublicHttpUrl(value)) return false;

  const hostname = new URL(value).hostname.replace(/^\[|\]$/g, '').toLowerCase();
  if (net.isIP(hostname)) return true;

  try {
    const addresses = await resolver(hostname, { all: true, verbatim: true });
    return Array.isArray(addresses)
      && addresses.length > 0
      && addresses.every(({ address }) => isPublicIp(address));
  } catch {
    return false;
  }
}

export function isPublicIp(address) {
  const version = net.isIP(address);
  if (version === 4) return isPublicIpv4(address);
  if (version === 6) return isPublicIpv6(address);
  return false;
}

function isPublicIpv4(hostname) {
  const [a, b] = hostname.split('.').map(Number);

  if (a === 0 || a === 10 || a === 127) return false;
  if (a === 100 && b >= 64 && b <= 127) return false;
  if (a === 169 && b === 254) return false;
  if (a === 172 && b >= 16 && b <= 31) return false;
  if (a === 192 && b === 0) return false;
  if (a === 192 && b === 168) return false;
  if (a === 198 && (b === 18 || b === 19)) return false;
  if (a >= 224) return false;

  return true;
}

function isPublicIpv6(hostname) {
  const normalized = hostname.toLowerCase();

  if (normalized === '::' || normalized === '::1') return false;
  if (normalized.startsWith('fc') || normalized.startsWith('fd')) return false;
  if (/^fe[89ab]/.test(normalized)) return false;
  if (normalized.startsWith('::ffff:')) return false;

  const mapped = normalized.match(/^(?:0*:){5}ffff:(\d+\.\d+\.\d+\.\d+)$/);
  if (mapped) return isPublicIpv4(mapped[1]);

  return true;
}
