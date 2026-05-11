/**
 * Fecha de calendario según el locale del navegador (orden y separadores locales).
 * Día y mes siempre con dos dígitos (`2-digit`).
 * Acepta ISO completo o prefijo `Y-m-d`; usa componentes locales para evitar desfases por zona horaria.
 */
export function formatCalendarDateForBrowser(
  iso: string | null | undefined,
  locales: Intl.LocalesArgument =
    typeof navigator !== 'undefined' && navigator.language ? navigator.language : 'es',
): string {
  if (!iso) return '—';
  const m = String(iso).trim().match(/^(\d{4})-(\d{2})-(\d{2})/);
  let date: Date;
  if (m) {
    const y = Number(m[1]);
    const month = Number(m[2]);
    const day = Number(m[3]);
    date = new Date(y, month - 1, day);
  } else {
    date = new Date(iso);
  }
  if (Number.isNaN(date.getTime())) return '—';
  const opts: Intl.DateTimeFormatOptions = {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  };
  try {
    return new Intl.DateTimeFormat(locales, opts).format(date);
  } catch {
    return new Intl.DateTimeFormat('es', opts).format(date);
  }
}
