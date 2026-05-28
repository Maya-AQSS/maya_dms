/**
 * Reglas compartidas plantilla/documento:
 * - Eliminar: entidad nueva (nunca publicada; alta o clon sin publicar).
 * - Descartar versión: working draft sobre una versión ya publicada.
 */

export function canDeleteUnpublishedEntity(
  latestPublishedVersionId: string | null | undefined,
  isAuthorized: boolean,
): boolean {
  return isAuthorized && !latestPublishedVersionId;
}

export function isDiscardWorkingVersionAllowed(
  latestPublishedVersionId: string | null | undefined,
  workingVersionId: string | null | undefined,
  status: string | null | undefined,
  allowedStatuses: readonly string[],
): boolean {
  if (!latestPublishedVersionId || !workingVersionId) {
    return false;
  }
  if (!status || !allowedStatuses.includes(status)) {
    return false;
  }

  return true;
}
