import type {
  DocumentMigrationPayload,
  MigrationChoice,
  RemovedBlockChoice,
} from '../schemas/migrationPayload';

/**
 * Etiquetas de los bloques con elección pendiente en el paso de migración:
 * - accionables (no locked/new/removed con contenido antiguo) sin Reemplazar/Anexar.
 * - en upgrade, eliminados sin Eliminar/Mantener.
 *
 * Devuelve [] cuando no falta ninguna decisión (el gate deja continuar).
 */
export function pendingMigrationBlockLabels(
  payload: DocumentMigrationPayload | null,
  choices: Record<string, MigrationChoice>,
  removedChoices: Record<string, RemovedBlockChoice>,
  allowRemovedDecision: boolean,
): string[] {
  if (!payload) return [];
  const pending: string[] = [];
  for (const block of payload.blocks) {
    const label = block.title ?? block.template_block_id;
    const actionable =
      !block.locked && !block.new_block && !block.removed_block && block.old_content != null;
    if (actionable && !choices[block.template_block_id]) {
      pending.push(label);
    }
    if (allowRemovedDecision && block.removed_block && !removedChoices[block.template_block_id]) {
      pending.push(label);
    }
  }
  return pending;
}
