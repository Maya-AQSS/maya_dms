import type { BlockState } from '../../../types/blocks';
import type { DocumentDisplayBlock } from '../../../types/documents';
import type { TemplateVersionSnapshotBlock } from '../../../api/templates';
import { normalizeBlockContentForEditor } from './normalizeBlockContent';
import type { ComparableBlock } from './versionBlockCompare';

/**
 * Convierte el array `snapshot_data.blocks` de una versión publicada de
 * documento en bloques de visualización. Tolerante a snapshots antiguos:
 * acepta `template_block_id` o `id`, y rellena valores por defecto.
 */
export function mapSnapshotDocumentBlocks(raw: unknown): DocumentDisplayBlock[] {
  if (!Array.isArray(raw)) return [];
  const out: DocumentDisplayBlock[] = [];
  for (let idx = 0; idx < raw.length; idx++) {
    const item = raw[idx];
    if (!item || typeof item !== 'object') continue;
    const o = item as Record<string, unknown>;
    const blockState = (typeof o.block_state === 'string' ? o.block_state : 'locked') as BlockState;
    out.push({
      document_block_id: typeof o.document_block_id === 'string' ? o.document_block_id : null,
      template_block_id: String(o.template_block_id ?? o.id ?? ''),
      type: typeof o.type === 'string' ? o.type : 'text',
      title: o.title != null ? String(o.title) : null,
      description: o.description,
      default_content: o.default_content ?? null,
      block_type: typeof o.block_type === 'string'
        ? (o.block_type as DocumentDisplayBlock['block_type'])
        : undefined,
      block_state: blockState,
      mandatory: Boolean(o.mandatory),
      sort_order: typeof o.sort_order === 'number' ? o.sort_order : idx,
      content: o.content ?? null,
      is_filled: Boolean(o.is_filled),
      is_deleted: Boolean(o.is_deleted),
    });
  }
  return out;
}

/**
 * Contenido Tiptap efectivo del bloque: el rellenado si tiene nodos, si no el
 * de plantilla. Refleja a propósito la MISMA semántica de render que
 * `blockContentForPreview`/PDF (un bloque sin rellenar muestra la guía de la
 * plantilla), de modo que comparar versiones compara lo que realmente se ve:
 * un bloque que pasa de relleno a vacío produce diff (texto → guía), no se oculta.
 */
export function effectiveDocumentBlockContent(block: DocumentDisplayBlock): unknown {
  const filled = normalizeBlockContentForEditor(block.content);
  return filled.length > 0 ? block.content : block.default_content;
}

/** Bloques de una versión de documento normalizados para comparar entre versiones. */
export function documentBlocksToComparable(blocks: DocumentDisplayBlock[]): ComparableBlock[] {
  return blocks.map((b) => ({
    key: b.template_block_id,
    title: b.title,
    content: effectiveDocumentBlockContent(b),
    sortOrder: b.sort_order,
  }));
}

/** Bloques de una versión de plantilla (`blocks_snapshot`) normalizados para comparar. */
export function templateSnapshotToComparable(
  blocks: TemplateVersionSnapshotBlock[],
): ComparableBlock[] {
  return blocks.map((b, idx) => ({
    key: b.id,
    title: b.title ?? null,
    content: b.default_content ?? null,
    sortOrder: typeof b.sort_order === 'number' ? b.sort_order : idx,
  }));
}
