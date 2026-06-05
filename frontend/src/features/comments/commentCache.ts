import type { QueryClient } from '@tanstack/react-query';
import { markCommentAsRead } from '../../api/comments';
import type { BlockComment } from '../templates/components/BlockCommentsCard';
import {
  templateCommentsKey,
  type TemplateCommentsResponse,
} from '../templates/hooks/useTemplateComments';

export const documentCommentsKey = (documentId: string) =>
  ['documents', documentId, 'comments'] as const;

export function patchDocumentCommentCache(
  queryClient: QueryClient,
  documentId: string,
  updater: (comments: BlockComment[]) => BlockComment[],
): void {
  queryClient.setQueryData<{ data: BlockComment[] }>(
    documentCommentsKey(documentId),
    (current) => ({ data: updater(current?.data ?? []) }),
  );
}

export function patchTemplateCommentCache(
  queryClient: QueryClient,
  templateId: string,
  updater: (comments: BlockComment[]) => BlockComment[],
): void {
  queryClient.setQueryData<TemplateCommentsResponse>(
    templateCommentsKey(templateId),
    (current) => {
      if (!current) return current;
      return { ...current, data: updater(current.data) };
    },
  );
}

export async function markCommentAsReadInDocumentCache(
  queryClient: QueryClient,
  documentId: string,
  commentId: string,
): Promise<void> {
  const updated = await markCommentAsRead(commentId);
  patchDocumentCommentCache(queryClient, documentId, (comments) =>
    comments.map((c) => (c.id === commentId ? updated : c)),
  );
}

export async function markCommentAsReadInTemplateCache(
  queryClient: QueryClient,
  templateId: string,
  commentId: string,
): Promise<void> {
  const updated = await markCommentAsRead(commentId);
  patchTemplateCommentCache(queryClient, templateId, (comments) =>
    comments.map((c) => (c.id === commentId ? updated : c)),
  );
}
