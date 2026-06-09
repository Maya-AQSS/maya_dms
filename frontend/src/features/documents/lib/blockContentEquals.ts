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

  // Bloques estructurales (portada/índice/blanco) no se rellenan con texto tiptap:
  // el chequeo de "placeholder" no aplica. La portada se considera resuelta cuando
  // tiene datos de relleno guardados (objeto cover-fill); índice y blanco siempre.
  const blockType = block.block_type ?? 'content';
  if (blockType === 'index' || blockType === 'blank') {
    return false;
  }
  if (blockType === 'cover') {
    return !(block.content !== null && typeof block.content === 'object');
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

export type DocumentBlockSavePlan =
  | { action: 'skip' }
  | { action: 'persist'; payload: unknown };

/**
 * Decide si el autoguardado debe llamar al API y con qué payload.
 * - Sin cambio vs último guardado en sesión → skip.
 * - Igual a plantilla y BD ya alineada → skip (evita PUT fantasma).
 * - Igual a plantilla pero BD con edición previa en editable → PUT null (volver al guía).
 */
export function planDocumentBlockSave(
  local: unknown,
  lastSavedInSession: unknown,
  persistedInDb: unknown,
  defaultContent: unknown,
  blockState: DocumentDisplayBlock['block_state'],
): DocumentBlockSavePlan {
  if (documentBlockContentUnchanged(local, lastSavedInSession)) {
    return { action: 'skip' };
  }

  const matchesDefault = documentBlockContentUnchanged(local, defaultContent);

  if (matchesDefault) {
    const dbAlreadyAtDefault =
      blockState === 'editable'
        ? isEditableBlockStillPlaceholder(persistedInDb ?? null, defaultContent)
        : documentBlockContentUnchanged(local, persistedInDb ?? null);

    if (dbAlreadyAtDefault) {
      return { action: 'skip' };
    }

    if (blockState === 'editable') {
      return { action: 'persist', payload: null };
    }

    return { action: 'persist', payload: local };
  }

  return { action: 'persist', payload: local };
}
