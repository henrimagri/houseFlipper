let memoryToken: string | null = null;

export function getAuthToken(): string | null {
  try {
    const stored = localStorage.getItem('token');
    if (stored) memoryToken = stored;
  } catch {
    // An in-memory token keeps the current SPA session usable when storage is blocked.
  }

  return memoryToken;
}

export function setAuthToken(token: string): boolean {
  memoryToken = token;

  try {
    localStorage.setItem('token', token);
    return true;
  } catch {
    return false;
  }
}

export function clearAuthToken(): void {
  memoryToken = null;

  try {
    localStorage.removeItem('token');
  } catch {
    // The in-memory session has already been cleared.
  }
}
