const directives = [
  "default-src 'self'",
  "base-uri 'self'",
  "object-src 'none'",
  "frame-ancestors 'none'",
  "form-action 'self'",
  "script-src 'self'",
  "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
  "font-src 'self' https://fonts.gstatic.com data:",
  "img-src 'self' data: blob: https:",
  "connect-src 'self' blob: https://servicodados.ibge.gov.br https://tiles.openfreemap.org https://*.tiles.openfreemap.org https://nominatim.openstreetmap.org",
  "worker-src 'self' blob:",
  "media-src 'self' blob:",
];

export function contentSecurityPolicy(development = false): string {
  return directives
    .map((directive) => {
      if (development && directive.startsWith('script-src ')) {
        return `${directive} 'unsafe-inline'`;
      }

      if (development && directive.startsWith('connect-src ')) {
        return `${directive} ws: wss:`;
      }

      return directive;
    })
    .join('; ');
}

export function browserSecurityHeaders(development = false): Record<string, string> {
  return {
    'Content-Security-Policy': contentSecurityPolicy(development),
    'Cross-Origin-Opener-Policy': 'same-origin',
    'Permissions-Policy': 'camera=(), geolocation=(), microphone=(), payment=(), usb=()',
    'Referrer-Policy': 'no-referrer',
    'X-Content-Type-Options': 'nosniff',
    'X-Frame-Options': 'DENY',
    'X-Permitted-Cross-Domain-Policies': 'none',
  };
}
