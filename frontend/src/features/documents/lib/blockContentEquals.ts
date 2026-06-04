import {
  isSemanticallyEmptyTiptapContent,
  tiptapContentEquals,
} from '@ceedcv-maya/shared-editor-react';
import type { DocumentDisplayBlock } from '../../../types/documents';

/** True when block editor payload has no meaningful diff vs last persisted content. */
export function documentBlockContentUnchanged(
  local: unknown,
  persisted: unknown,
): boolean {
  return tiptapContentEquals(local, persisted);
}

/**
 * Editable sin trabajo del usuario: vacío o igual al default_content de plantilla.
 * No usa is_filled (puede quedar true por autoguardado aunque el texto sea el guía).
 */
export function isEditableBlockStillPlaceholder(
  content: unknown,
  defaultContent: unknown,
): boolean {
  return (
    isSemanticallyEmptyTiptapContent(content) ||
    tiptapContentEquals(content, defaultContent)
  );
}

export function isUnresolvedEditableBlock(block: DocumentDisplayBlock): boolean {
  if (block.block_state !== 'editable' || block.is_deleted) {
    return false;
  }

  return isEditableBlockStillPlaceholder(
    block.content ?? null,
    block.default_content ?? null,
  );
}

/** Bloques editables que aún deben completarse antes de pasar al resumen o enviar. */
export function listUnresolvedEditableBlockTitles(
  blocks: DocumentDisplayBlock[],
): string[] {
  return blocks
    .filter(isUnresolvedEditableBlock)
    .map((b) => b.title ?? 'Sin título');
}

/** Mismo criterio que la validación del wizard (para diff / «Ver cambios»). */
export function documentBlockContentDiffersFromTemplateDefault(
  content: unknown,
  defaultContent: unknown,
): boolean {
  return !isEditableBlockStillPlaceholder(content, defaultContent);
}
