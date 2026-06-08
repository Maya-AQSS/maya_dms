import { z } from 'zod';

/**
 * Validación de boundary del payload del paso de migración del wizard
 * (`GET /documents/{id}/migration-payload`).
 */
export const migrationBlockSchema = z.object({
  template_block_id: z.string(),
  title: z.string().nullable().optional(),
  type: z.string().optional(),
  sort_order: z.number(),
  block_state: z.string().nullable(),
  old_block_state: z.string().nullable(),
  new_block: z.boolean(),
  removed_block: z.boolean(),
  changed_block_state: z.boolean(),
  locked: z.boolean(),
  new_default_content: z.unknown().nullable(),
  old_content: z.unknown().nullable(),
  old_default_content: z.unknown().nullable(),
});

export const migrationPayloadSchema = z.object({
  source_document_id: z.string(),
  source_template_version_id: z.string(),
  source_version_number: z.number(),
  target_template_version_id: z.string(),
  target_version_number: z.number(),
  blocks: z.array(migrationBlockSchema),
});

export type DocumentMigrationBlock = z.infer<typeof migrationBlockSchema>;
export type DocumentMigrationPayload = z.infer<typeof migrationPayloadSchema>;

/** Elección del usuario para precargar el contenido antiguo en el bloque nuevo. */
export type MigrationChoice = 'replace' | 'append';

/** Elección para un bloque eliminado en la versión nueva (solo upgrade in-situ). */
export type RemovedBlockChoice = 'delete' | 'keep';
