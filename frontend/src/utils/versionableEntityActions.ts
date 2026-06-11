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

export type NewVersionEntityMeta = {
  latest_published_version_id?: string | null;
  working_revision_in_progress?: boolean;
  working_revision_editor_name?: string | null;
  working_revision_started_at?: string | null;
  can_create_new_version?: boolean;
  can_view_history?: boolean;
};

/**
 * Visibilidad del botón «Historial» (acuerdo de equipo).
 * - Si puede crear nueva versión → mostrar (aunque solo haya v1 publicada).
 * - Si no, solo con `can_view_history` y al menos dos versiones publicadas.
 */
export function canShowVersionHistoryButton(
  entity: NewVersionEntityMeta | null | undefined,
  publishedVersionCount: number | null,
  canCreateNewVersion: boolean,
): boolean {
  if (canCreateNewVersion) {
    return true;
  }

  return (
    entity?.can_view_history === true
    && publishedVersionCount !== null
    && publishedVersionCount > 1
  );
}
