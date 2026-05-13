/**
 * Texto comparable en búsquedas: minúsculas y sin marcas diacríticas
 * (p. ej. "José" y "jose" coinciden).
 */
export function normalizeForSearch(value: string): string {
  return value
    .normalize('NFD')
    .replace(/\p{M}/gu, '')
    .toLowerCase()
}
