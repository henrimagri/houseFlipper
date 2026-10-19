export function deviceName(userAgent = navigator.userAgent, platform = navigator.platform) {
  const browser = /Edg\//.test(userAgent) ? 'Edge'
    : /Firefox\//.test(userAgent) ? 'Firefox'
      : /Chrome\//.test(userAgent) ? 'Chrome'
        : /Safari\//.test(userAgent) ? 'Safari'
          : 'Navegador';
  const system = /Windows/i.test(userAgent) ? 'Windows'
    : /Android/i.test(userAgent) ? 'Android'
      : /iPhone|iPad|iPod/i.test(userAgent) ? 'iOS'
        : /Mac/i.test(userAgent) || /Mac/i.test(platform) ? 'macOS'
          : /Linux/i.test(userAgent) ? 'Linux'
            : String(platform || '').trim() || 'Dispositivo';

  return `${browser} · ${system}`.slice(0, 100);
}
