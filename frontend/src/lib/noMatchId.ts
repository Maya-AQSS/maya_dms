/**
 * Sentinela UUID que no coincide con ningún id real.
 *
 * Se usa en los listados server-side para el filtro "solo favoritos" cuando el
 * usuario no tiene favoritos: enviar este id garantiza 0 resultados sin tener
 * que ramificar la query en el backend.
 */
export const NO_MATCH_ID = '00000000-0000-0000-0000-000000000000';
