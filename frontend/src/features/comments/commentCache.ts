import type { QueryClient } from '@tanstack/react-query';
import { markCommentAsRead, markBlockCommentsAsRead } from '../../api/comments';
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
    (current) => ({ data: updater(current?.data ?? []), meta: current?.meta }),
  );
}

export function appendCommentToTemplateCache(
  queryClient: QueryClient,
  templateId: string,
  comment: BlockComment,
): void {
  patchTemplateCommentCache(queryClient, templateId, (comments) => [...comments, comment]);
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

function mergeBlockCommentsIntoList(
  comments: BlockComment[],
  blockComments: BlockComment[],
): BlockComment[] {
  const updatedById = new Map(blockComments.map((c) => [c.id, c]));
  return comments.map((c) => updatedById.get(c.id) ?? c);
}

export async function markBlockCommentsAsReadInTemplateCache(
  queryClient: QueryClient,
  templateId: string,
  blockableId: string,
): Promise<void> {
  const res = await markBlockCommentsAsRead(
    `templates/${templateId}/comments/mark-block-read`,
    blockableId,
  );
  patchTemplateCommentCache(queryClient, templateId, (comments) =>
    mergeBlockCommentsIntoList(comments, res.data),
  );
}

export async function markBlockCommentsAsReadInDocumentCache(
  queryClient: QueryClient,
  documentId: string,
  blockableId: string,
): Promise<void> {
  const res = await markBlockCommentsAsRead(
    `documents/${documentId}/comments/mark-block-read`,
    blockableId,
  );
  patchDocumentCommentCache(queryClient, documentId, (comments) =>
    mergeBlockCommentsIntoList(comments, res.data),
  );
}

/** Soft-delete en cache: alinea con listados API (`withTrashed` + `is_deleted`). */
export function applyCommentDeleted(
  comment: BlockComment,
  deletedByName?: string | null,
): BlockComment {
  return {
    ...comment,
    is_deleted: true,
    deleted_at: new Date().toISOString(),
    deleted_by_name: deletedByName ?? comment.deleted_by_name ?? null,
  };
}

export function markCommentDeletedInList(
  comments: BlockComment[],
  commentId: string,
  deletedByName?: string | null,
): BlockComment[] {
  return comments.map((c) =>
    c.id === commentId ? applyCommentDeleted(c, deletedByName) : c,
  );
}

export function markCommentDeletedInDocumentCache(
  queryClient: QueryClient,
  documentId: string,
  commentId: string,
  deletedByName?: string | null,
): void {
  patchDocumentCommentCache(queryClient, documentId, (comments) =>
    markCommentDeletedInList(comments, commentId, deletedByName),
  );
}

export function markCommentDeletedInTemplateCache(
  queryClient: QueryClient,
  templateId: string,
  commentId: string,
  deletedByName?: string | null,
): void {
  patchTemplateCommentCache(queryClient, templateId, (comments) =>
    markCommentDeletedInList(comments, commentId, deletedByName),
  );
}
