import { useQueryClient } from '@tanstack/react-query';
import { useCallback, useMemo, useState } from 'react';
import { apiFetchJson } from '../../../api/http';
import { resolveCommentBlockableId } from '../../../utils/blockComments';
import {
  appendCommentToTemplateCache,
  markBlockCommentsAsReadInTemplateCache,
  markCommentAsReadInTemplateCache,
  markCommentDeletedInTemplateCache,
  patchTemplateCommentCache,
} from '../../comments/commentCache';
import type { BlockComment } from './BlockCommentsCard';

interface UseWizardBlockCommentsArgs {
  templateId: string;
  activeSingleId: string | null;
  reviewComments: BlockComment[];
  profileName?: string | null;
}

/**
 * Comment lifecycle for the template block wizard: send/anchor/edit/delete and
 * read-state mutations, plus the `commentsById` dictionary fed to the editor so
 * a CommentMark span renders the author + body in a popover.
 *
 * Owns its own submit loading/error state — the rest of the wizard only reads
 * them to drive the BlockCommentsCard.
 */
export function useWizardBlockComments({
  templateId,
  activeSingleId,
  reviewComments,
  profileName,
}: UseWizardBlockCommentsArgs) {
  const queryClient = useQueryClient();
  const [commentSubmitLoading, setCommentSubmitLoading] = useState(false);
  const [commentSubmitError, setCommentSubmitError] = useState<string | null>(null);

  // Comments dict keyed by id, fed to MayaEditor so hovering over a
  // CommentMark span shows the author + body in a portal popover.
  const commentsById = useMemo(() => {
    const out: Record<string, { author?: string; createdAt?: string; body: string }> = {};
    for (const c of reviewComments) {
      out[c.id] = {
        author: c.author?.name ?? undefined,
        createdAt: c.created_at
          ? new Date(c.created_at).toLocaleString('es-ES', {
              dateStyle: 'short',
              timeStyle: 'short',
            })
          : undefined,
        body: c.body
          .replace(/<br\s*\/?\s*>/gi, '\n')
          .replace(/<\/p>\s*<p[^>]*>/gi, '\n\n')
          .replace(/<\/?p[^>]*>/gi, '')
          .replace(/<[^>]+>/g, '')
          .replace(/&lt;/g, '<')
          .replace(/&gt;/g, '>')
          .replace(/&amp;/g, '&')
          .trim(),
      };
    }
    return out;
  }, [reviewComments]);

  const handleSendMessage = useCallback(
    async (parentId: string | null, body: string) => {
      if (!activeSingleId) return;
      setCommentSubmitError(null);
      setCommentSubmitLoading(true);
      try {
        const blockableId = resolveCommentBlockableId(parentId, reviewComments, activeSingleId);
        const res = await apiFetchJson<{ data: BlockComment }>(`templates/${templateId}/comments`, {
          method: 'POST',
          body: { body, parent_id: parentId, blockable_id: blockableId },
        });
        appendCommentToTemplateCache(queryClient, templateId, res.data);
      } catch {
        setCommentSubmitError('No se pudo guardar el comentario.');
        throw new Error('comment-send-failed');
      } finally {
        setCommentSubmitLoading(false);
      }
    },
    [activeSingleId, queryClient, reviewComments, templateId],
  );

  /**
   * Anchored comment on a text selection from inside the editor.
   *
   * Two-step flow:
   *   1. POST templates/{id}/comments  → creates the comment row used as
   *      the right-rail entry (visible in BLOQUE #N comments).
   *   2. POST template/{id}/anchored-comments → records the position
   *      range so the editor's CommentMark can survive concurrent edits.
   *
   * Returns the comment id so MayaEditor applies the mark to the
   * selected range.
   */
  const handleCreateAnchoredComment = useCallback(
    async (range: { from: number; to: number; text: string }): Promise<string | null> => {
      if (!activeSingleId) return null;
      const body = window.prompt(
        `Comentario sobre la selección "${range.text.slice(0, 80)}${range.text.length > 80 ? '…' : ''}"`,
        '',
      );
      if (!body?.trim()) return null;
      try {
        const res = await apiFetchJson<{ data: BlockComment }>(`templates/${templateId}/comments`, {
          method: 'POST',
          body: { body: body.trim(), parent_id: null, blockable_id: activeSingleId },
        });
        const commentId = res.data.id;
        // Anchor record — failures here don't roll back the comment; the
        // anchor can be re-attached later if needed.
        try {
          await apiFetchJson(`template/${templateId}/anchored-comments`, {
            method: 'POST',
            body: {
              comment_id: commentId,
              anchor_from: range.from,
              anchor_to: range.to,
              anchor_text_snapshot: range.text.slice(0, 1000),
            },
          });
        } catch (e) {
          console.warn('[WizardStep2Blocks] anchor save failed', e);
        }
        appendCommentToTemplateCache(queryClient, templateId, res.data);
        return commentId;
      } catch (e) {
        console.error('[WizardStep2Blocks] comment create failed', e);
        return null;
      }
    },
    [activeSingleId, queryClient, templateId],
  );

  const handleEditComment = useCallback(
    async (commentId: string, newBody: string) => {
      const res = await apiFetchJson<{ data: BlockComment }>(`comments/${commentId}`, {
        method: 'PATCH',
        body: { body: newBody },
      });
      patchTemplateCommentCache(queryClient, templateId, (comments) =>
        comments.map((c) => (c.id === commentId ? res.data : c)),
      );
    },
    [queryClient, templateId],
  );

  const handleDeleteComment = useCallback(
    async (commentId: string) => {
      await apiFetchJson(`comments/${commentId}`, { method: 'DELETE' });
      markCommentDeletedInTemplateCache(queryClient, templateId, commentId, profileName);
    },
    [queryClient, templateId, profileName],
  );

  const handleMarkCommentAsRead = useCallback(
    async (commentId: string) => {
      await markCommentAsReadInTemplateCache(queryClient, templateId, commentId);
    },
    [queryClient, templateId],
  );

  const handleMarkAllBlockCommentsAsRead = useCallback(async () => {
    if (!activeSingleId) return;
    await markBlockCommentsAsReadInTemplateCache(queryClient, templateId, activeSingleId);
  }, [activeSingleId, queryClient, templateId]);

  return {
    commentSubmitLoading,
    commentSubmitError,
    commentsById,
    handleSendMessage,
    handleCreateAnchoredComment,
    handleEditComment,
    handleDeleteComment,
    handleMarkCommentAsRead,
    handleMarkAllBlockCommentsAsRead,
  };
}
