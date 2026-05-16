import type { BlockComment } from '../features/templates/components/BlockCommentsCard';

/**
 * Returns root comments anchored to a block plus all recursive replies.
 * Used by review/preview UIs to count and render the full thread tree.
 *
 * Single canonical implementation — replaces 4 inline duplicates that lived
 * in DocumentPreviewPage, TemplatePreviewPage, TemplateReviewView and
 * WizardStep2Blocks (some `any`-typed, some only 1-level deep). All callers
 * now share the recursive variant.
 */
export function getCommentsForBlock(
  blockId: string | null,
  allComments: BlockComment[],
): BlockComment[] {
  if (!blockId) return [];

  const collectReplies = (parentId: string): BlockComment[] => {
    const direct = allComments.filter((c) => c.parent_id === parentId);
    return [...direct, ...direct.flatMap((r) => collectReplies(r.id))];
  };

  const roots = allComments.filter(
    (c) => c.blockable_id === blockId && !c.parent_id,
  );

  const allForBlock = [...roots, ...roots.flatMap((r) => collectReplies(r.id))];

  const seen = new Set<string>();
  return allForBlock.filter((c) => {
    if (seen.has(c.id)) return false;
    seen.add(c.id);
    return true;
  });
}
