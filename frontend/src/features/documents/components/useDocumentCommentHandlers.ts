import { useQueryClient } from '@tanstack/react-query';
import { useCallback, useState } from 'react';
import { apiFetchJson } from '../../../api/http';
import type { DocumentDisplayBlock } from '../../../types/documents';
import { resolveCommentBlockableId } from '../../../utils/blockComments';
import {
  addCommentToCache,
  documentCommentsKey,
  markBlockCommentsAsReadInDocumentCache,
  markCommentAsReadInDocumentCache,
  markCommentDeletedInDocumentCache,
  patchDocumentCommentCache,
} from '../../comments/commentCache';
import type { BlockComment } from '../../templates/components/BlockCommentsCard';
import { useDocumentCommentsQuery } from '../hooks/useDocumentComments';

interface UseDocumentCommentHandlersArgs {
  documentId?: string | null;
  hasDetail: boolean;
  activeBlockRef: { current: DocumentDisplayBlock | null };
  profileName?: string | null;
}

/**
 * Review-comment lifecycle for the document wizard: owns the comments query
 * (creator-edit mode), submit loading/error state, and the send/edit/delete/
 * mark-read handlers wired to the shared TanStack Query cache.
 */
export function useDocumentCommentHandlers({
  documentId,
  hasDetail,
  activeBlockRef,
  profileName,
}: UseDocumentCommentHandlersArgs) {
  const queryClient = useQueryClient();
  const [documentCommentLoading, setDocumentCommentLoading] = useState(false);
  const [documentCommentSubmitError, setDocumentCommentSubmitError] = useState<string | null>(null);

  const documentCommentsQuery = useDocumentCommentsQuery(documentId ?? '', {
    enabled: !!documentId && hasDetail,
  });
  const reviewComments: BlockComment[] = documentCommentsQuery.data?.data ?? [];

  const handleDocumentCommentSend = useCallback(
    async (parentId: string | null, body: string) => {
      if (!documentId) return;
      setDocumentCommentSubmitError(null);
      setDocumentCommentLoading(true);
      try {
        const blockableId = resolveCommentBlockableId(
          parentId,
          reviewComments,
          activeBlockRef.current?.document_block_id ?? null,
        );
        const res = await apiFetchJson<{ data: BlockComment }>(`documents/${documentId}/comments`, {
          method: 'POST',
          body: { body, parent_id: parentId, blockable_id: blockableId },
        });
        addCommentToCache(queryClient, documentCommentsKey(documentId), res.data);
      } catch {
        setDocumentCommentSubmitError('No se pudo guardar el comentario.');
        throw new Error('comment-send-failed');
      } finally {
        setDocumentCommentLoading(false);
      }
    },
    [documentId, reviewComments, queryClient, activeBlockRef],
  );

  const handleDocumentCommentEdit = useCallback(
    async (commentId: string, newBody: string) => {
      if (!documentId) return;
      const res = await apiFetchJson<{ data: BlockComment }>(`comments/${commentId}`, {
        method: 'PATCH',
        body: { body: newBody },
      });
      patchDocumentCommentCache(queryClient, documentId, (comments) =>
        comments.map((c) => (c.id === commentId ? res.data : c)),
      );
    },
    [documentId, queryClient],
  );

  const handleDocumentCommentDelete = useCallback(
    async (commentId: string) => {
      if (!documentId) return;
      await apiFetchJson(`comments/${commentId}`, { method: 'DELETE' });
      markCommentDeletedInDocumentCache(queryClient, documentId, commentId, profileName);
    },
    [documentId, queryClient, profileName],
  );

  const handleDocumentCommentMarkAsRead = useCallback(
    async (commentId: string) => {
      if (!documentId) return;
      await markCommentAsReadInDocumentCache(queryClient, documentId, commentId);
    },
    [documentId, queryClient],
  );

  const handleDocumentCommentMarkAllBlockAsRead = useCallback(async () => {
    if (!documentId) return;
    const blockId = activeBlockRef.current?.document_block_id;
    if (!blockId) return;
    await markBlockCommentsAsReadInDocumentCache(queryClient, documentId, blockId);
  }, [documentId, queryClient, activeBlockRef]);

  return {
    reviewComments,
    documentCommentLoading,
    documentCommentSubmitError,
    handleDocumentCommentSend,
    handleDocumentCommentEdit,
    handleDocumentCommentDelete,
    handleDocumentCommentMarkAsRead,
    handleDocumentCommentMarkAllBlockAsRead,
  };
}
