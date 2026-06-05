import { apiFetchJson } from './http';
import type { BlockComment } from '../features/templates/components/BlockCommentsCard';

export async function markCommentAsRead(commentId: string): Promise<BlockComment> {
  const res = await apiFetchJson<{ data: BlockComment }>(`comments/${commentId}/read`, {
    method: 'POST',
  });
  return res.data;
}
