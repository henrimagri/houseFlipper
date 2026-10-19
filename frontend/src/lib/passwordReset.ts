export type ResetCredentials = { token: string; email: string };

export function parseResetFragment(fragment: string): ResetCredentials | null {
  const params = new URLSearchParams(fragment.replace(/^#/, ''));
  const token = params.get('token')?.trim() ?? '';
  const email = params.get('email')?.trim().toLocaleLowerCase('pt-BR') ?? '';
  if (!token || token.length > 255 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return null;
  return { token, email };
}
