/** Texto visible del HTML del changelog (validación vacío / longitud). */
export function plainTextFromChangelogHtml(html: string): string {
  const trimmed = html.trim();
  if (trimmed === '') {
    return '';
  }

  if (typeof document === 'undefined') {
    return trimmed.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
  }

  const el = document.createElement('div');
  el.innerHTML = trimmed;

  return (el.textContent ?? '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();
}

export function normalizeChangelogHtml(html: string): string {
  return html.trim();
}
