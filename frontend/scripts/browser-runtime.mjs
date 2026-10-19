import { existsSync } from 'node:fs';

export function browserRuntime(env = process.env, platform = process.platform, exists = existsSync) {
  const candidates = platform === 'win32'
    ? [env.PROGRAMFILES && `${env.PROGRAMFILES}/Google/Chrome/Application/chrome.exe`,
      env['PROGRAMFILES(X86)'] && `${env['PROGRAMFILES(X86)']}/Microsoft/Edge/Application/msedge.exe`,
      env.LOCALAPPDATA && `${env.LOCALAPPDATA}/Google/Chrome/Application/chrome.exe`]
    : platform === 'darwin'
      ? ['/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge']
      : ['/usr/bin/google-chrome', '/usr/bin/chromium', '/usr/bin/chromium-browser'];
  const executablePath = env.CHROME_PATH || candidates.filter(Boolean).find(exists);
  if (!executablePath || !exists(executablePath)) {
    throw new Error('Browser unavailable. Configure CHROME_PATH.');
  }
  return {
    executablePath,
    args: env.SCRAPER_DISABLE_SANDBOX === 'true' ? ['--no-sandbox', '--disable-setuid-sandbox'] : [],
  };
}
