export const digits = (value: string) => value.replace(/\D/g, '');

export function formatPhone(value: string) {
  const raw = digits(value).slice(0, 11);
  if (raw.length <= 2) return raw ? `(${raw}` : '';
  if (raw.length <= 6) return `(${raw.slice(0, 2)}) ${raw.slice(2)}`;
  if (raw.length <= 10) return `(${raw.slice(0, 2)}) ${raw.slice(2, 6)}-${raw.slice(6)}`;
  return `(${raw.slice(0, 2)}) ${raw.slice(2, 7)}-${raw.slice(7)}`;
}

export function formatCep(value: string) {
  const raw = digits(value).slice(0, 8);
  return raw.length > 5 ? `${raw.slice(0, 5)}-${raw.slice(5)}` : raw;
}

export function formatCpf(value: string) {
  const raw = digits(value).slice(0, 11);
  return raw.replace(/^(\d{3})(\d)/, '$1.$2').replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
}

export function normalizeDecimal(value: string) {
  const cleaned = value.replace(/[^\d,.-]/g, '').replace(/\.(?=.*\.)/g, '').replace(',', '.');
  const parsed = Number(cleaned);
  return Number.isFinite(parsed) ? parsed : null;
}

export function formatBrlInput(value: string) {
  const cents = digits(value).slice(0, 15);
  if (!cents) return '';
  return (Number(cents) / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}
