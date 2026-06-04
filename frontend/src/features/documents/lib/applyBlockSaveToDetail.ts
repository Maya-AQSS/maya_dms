import type { DocumentDetail } from '../../../types/documents';

/** Campos relevantes de la respuesta PUT /documents/{id}/blocks/{block}. */
export type DocumentBlockSavePayload = {
  document_block_id?: unknown;
  is_filled?: unknown;
  content?: unknown;
};

/**
 * Fusiona el resultado del guardado de un bloque en el detalle del documento
 * sin refetch completo (mismo criterio que plantillas tras updateBlock).
 */
export function applyBlockSaveToDetail(
  detail: DocumentDetail,
  documentBlockId: string,
  payload: DocumentBlockSavePayload,
): DocumentDetail {
  return {
    ...detail,
    blocks: detail.blocks.map((block) => {
      if (block.document_block_id !== documentBlockId) {
        return block;
      }

      const next = { ...block };

      if (typeof payload.is_filled === 'boolean') {
        next.is_filled = payload.is_filled;
      }

      if (payload.content !== undefined) {
        next.content = payload.content;
      }

      return next;
    }),
  };
}
