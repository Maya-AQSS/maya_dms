/**
 * Algoritmo de árbol de comentarios por bloque — genérico, SIN acoplar al tipo
 * `BlockComment` de DMS (DMS-PLT-01). Cualquier comentario con esta forma mínima
 * sirve; el tipo concreto se preserva en el retorno (genéricos), así que los
 * llamadores que pasan `BlockComment[]` reciben `BlockComment[]`.
 *
 * Implementación canónica única — reemplazó 4 duplicados inline (DocumentPreviewPage,
 * TemplatePreviewPage, TemplateReviewView, WizardStep2Blocks; algunos `any`, otros
 * de 1 nivel). Listo para extraer a un paquete compartido genericizando sobre
 * {@link CommentTreeNode}.
 */
export interface CommentTreeNode {
  id: string;
  blockable_id: string | null;
  parent_id?: string | null;
}

export interface CommentReadState {
  is_deleted?: boolean;
  is_read_by_me?: boolean;
}

/**
 * Returns root comments anchored to a block plus all recursive replies.
 * Used by review/preview UIs to count and render the full thread tree.
 */
export function getCommentsForBlock<T extends CommentTreeNode>(
  blockId: string | null,
  allComments: T[],
): T[] {
  if (!blockId) return [];

  const collectReplies = (parentId: string): T[] => {
    const direct = allComments.filter((c) => c.parent_id === parentId);
    return [...direct, ...direct.flatMap((r) => collectReplies(r.id))];
  };

  const roots = allComments.filter((c) => c.blockable_id === blockId && !c.parent_id);

  const allForBlock = [...roots, ...roots.flatMap((r) => collectReplies(r.id))];

  const seen = new Set<string>();
  return allForBlock.filter((c) => {
    if (seen.has(c.id)) return false;
    seen.add(c.id);
    return true;
  });
}

/** blockable_id del bloque activo o, en respuestas, el del comentario padre. */
export function resolveCommentBlockableId<T extends CommentTreeNode>(
  parentId: string | null,
  allComments: T[],
  activeBlockId: string | null,
): string | null {
  if (parentId) {
    return allComments.find((c) => c.id === parentId)?.blockable_id ?? null;
  }
  return activeBlockId;
}

/** Active comments in a block thread that the current user has not read yet. */
export function countUnreadCommentsForBlock<T extends CommentTreeNode & CommentReadState>(
  blockId: string | null,
  allComments: T[],
): number {
  return getCommentsForBlock(blockId, allComments).filter(
    (c) => !c.is_deleted && c.is_read_by_me !== true,
  ).length;
}
