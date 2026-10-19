const MAX_JSON_CHARS = 3_500_000;

export function readJson<T>(key: string): T | null {
  for (const storage of [sessionStorage, localStorage]) {
    try {
      const raw = storage.getItem(key);
      if (!raw) continue;
      return JSON.parse(raw) as T;
    } catch {
      // One store can be missing, quota-blocked or corrupt while the other still works.
    }
  }

  return null;
}

export function writeJson(key: string, value: unknown): boolean {
  const serialized = stringifyWithinBudget(value);
  if (!serialized) return false;

  let persisted = false;
  for (const storage of [sessionStorage, localStorage]) {
    try {
      storage.setItem(key, serialized);
      persisted = true;
    } catch {
      // Continue: sessionStorage and localStorage fail independently.
    }
  }

  return persisted;
}

function stringifyWithinBudget(value: unknown): string | null {
  try {
    const serialized = JSON.stringify(value);
    if (serialized.length <= MAX_JSON_CHARS) return serialized;
  } catch {
    return null;
  }

  if (!value || typeof value !== 'object') return null;

  try {
    const compact = JSON.stringify(stripThumbnails(value));
    return compact.length <= MAX_JSON_CHARS ? compact : null;
  } catch {
    return null;
  }
}

function stripThumbnails(value: unknown): unknown {
  if (Array.isArray(value)) return value.map(stripThumbnails);
  if (!value || typeof value !== 'object') return value;

  const next: Record<string, unknown> = {};
  for (const [key, nested] of Object.entries(value as Record<string, unknown>)) {
    if (key === 'thumbnail' || key === 'cover_image_url') continue;
    next[key] = stripThumbnails(nested);
  }

  return next;
}
