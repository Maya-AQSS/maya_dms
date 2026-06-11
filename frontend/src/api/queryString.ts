/**
 * Builder único de query strings para listados (sustituye a los builders
 * duplicados de templates.ts y themes.ts; processes.ts mantiene el suyo).
 *
 * Comportamiento (idéntico al de los builders reemplazados):
 * - los valores falsy (`undefined`, `null`, `''`, `0`, `false`) se OMITEN;
 * - `true` se serializa como `'1'` (flags tipo `usable_for_documents`);
 * - el resto de valores se serializan con `String()` (números incluidos).
 *
 * @returns `''` si no hay parámetros, o `'?a=b&c=d'` en caso contrario.
 */
export function buildQueryString(params: Record<string, unknown>): string {
  const q = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (!value) continue;
    if (value === true) {
      q.set(key, '1');
      continue;
    }
    q.set(key, String(value));
  }
  const s = q.toString();
  return s ? `?${s}` : '';
}
