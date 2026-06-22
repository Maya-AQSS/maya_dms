/** Texto visible del HTML del changelog (validación vacío / longitud). */
export function plainTextFromChangelogHtml(html: string): string {
  const trimmed = html.trim();
  if (trimmed === '') {
    return '';
  }

  if (typeof document === 'undefined') {
    return trimmed.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
  }

  // DOMParser produce un documento inerte: no ejecuta scripts ni dispara
  // `img onerror`, a diferencia de asignar innerHTML a un nodo creado.
  const parsed = new DOMParser().parseFromString(trimmed, 'text/html');

  return (parsed.body.textContent ?? '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();
}

export function normalizeChangelogHtml(html: string): string {
  return html.trim();
}
