const publicAuthPaths = new Set([
  '/auth/login',
  '/auth/register',
  '/auth/password/forgot',
  '/auth/password/reset',
]);

export function isPublicAuthRequest(url?: string): boolean {
  if (!url) return false;
  const path = url.split(/[?#]/, 1)[0];

  return publicAuthPaths.has(path)
    || path.startsWith('/auth/postal-code/')
    || path.startsWith('/auth/email/verify/');
}

export function shouldClearSessionAfterUnauthorized(url: string | undefined, authorization: unknown): boolean {
  return !isPublicAuthRequest(url) && /^Bearer\s+\S+$/i.test(String(authorization ?? ''));
}
