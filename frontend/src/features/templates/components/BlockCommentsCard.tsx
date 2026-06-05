import { useState, useEffect, type RefObject } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@ceedcv-maya/shared-ui-react';
import { canEditOwnBlockComment } from '../../../permissions';

// ── Types ─────────────────────────────────────────────────────────────────────

export type CommentMode = 'validator' | 'creator-readonly' | 'creator-edit';

export type BlockComment = {
  id: string;
  blockable_id: string | null;
  author_id: string;
  author?: { id: string; name: string };
  body: string;
  created_at: string;
  updated_at?: string | null;
  is_edited?: boolean;
  is_deleted?: boolean;
  is_read_by_me?: boolean;
  deleted_at?: string | null;
  deleted_by_name?: string | null;
  parent_id?: string | null;
};

// ── Helpers ───────────────────────────────────────────────────────────────────

const formatTime = (isoString: string) => {
  const date = new Date(isoString);
  const timeStr = date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
  const dateStr = date.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
  return (
    <span className="inline-flex items-center gap-1.5">
      <span className="text-text-primary dark:text-text-dark-primary font-black">{timeStr}</span>
      <span className="opacity-30">·</span>
      <span>{dateStr}</span>
    </span>
  );
};

// ── ViewCardHeader ─────────────────────────────────────────────────────────────

export function ViewCardHeader({
  blockSortOrder,
  title,
  onClose,
  headerRef,
}: {
  blockSortOrder: number | string | null;
  title: string;
  onClose: () => void;
  headerRef?: RefObject<HTMLDivElement | null>;
}) {
  const { t } = useTranslation('documents');
  return (
    <div
      ref={headerRef}
      className="flex items-stretch border-b border-ui-border dark:border-ui-dark-border shrink-0 bg-white dark:bg-ui-dark-card"
    >
      <div className="px-4 py-4 flex items-center shrink-0 border-r border-ui-border dark:border-ui-dark-border bg-ui-body/30 dark:bg-ui-dark-bg/50">
        <span className="text-2xs font-black uppercase tracking-[0.2em] text-odoo-purple">
          Bloque #{blockSortOrder ?? '?'}
        </span>
      </div>
      <div className="flex-1 px-5 py-4 flex items-center justify-between min-w-0">
        <span className="text-2xs font-black uppercase tracking-[0.15em] text-text-primary dark:text-text-dark-primary truncate">
          {title}
        </span>
        <button
          aria-label={t('blocks.closePanelAria')}
          onClick={onClose}
          className="group ml-3 w-8 h-8 rounded-full hover:bg-ui-body dark:hover:bg-ui-dark-bg flex items-center justify-center text-text-muted transition-all shrink-0"
        >
          <span className="block text-sm leading-none group-hover:rotate-90 transition-transform duration-200">✕</span>
        </button>
      </div>
    </div>
  );
}

// ── QuotedReply ───────────────────────────────────────────────────────────────

function QuotedReply({
  parent,
  onClick,
}: {
  parent: BlockComment;
  onClick: () => void;
}) {
  if (parent.is_deleted) {
    return (
      <div className="flex mb-3 rounded-lg overflow-hidden border border-ui-border/50 dark:border-ui-dark-border/50 opacity-60">
        <div className="w-1 bg-text-muted shrink-0" />
        <div className="px-3 py-2 bg-black/5 dark:bg-white/5 flex-1 min-w-0 flex items-center gap-1.5">
          <svg className="w-3 h-3 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2" />
          </svg>
          <p className="text-xs text-text-muted dark:text-text-dark-muted italic">Comentario eliminado</p>
        </div>
      </div>
    );
  }

  const preview = parent.body.length > 120 ? parent.body.slice(0, 120) + '…' : parent.body;

  return (
    <div
      role="button"
      tabIndex={0}
      onClick={onClick}
      onKeyDown={(e) => e.key === 'Enter' && onClick()}
      className="flex mb-3 rounded-lg overflow-hidden border border-ui-border/50 dark:border-ui-dark-border/50 cursor-pointer hover:border-odoo-purple/60 transition-colors group"
    >
      <div className="w-1 bg-odoo-purple shrink-0" />
      <div className="px-3 py-2 bg-black/5 dark:bg-white/5 group-hover:bg-odoo-purple/5 transition-colors flex-1 min-w-0">
        <p className="text-2xs font-black text-odoo-purple mb-0.5 truncate">
          {parent.author?.name || 'Usuario'}
        </p>
        <p className="text-xs text-text-muted dark:text-text-dark-muted line-clamp-2 break-words whitespace-pre-wrap">
          {preview}
        </p>
      </div>
    </div>
  );
}

// ── CommentItem ───────────────────────────────────────────────────────────────

function CommentItem({
  comment,
  mode,
  onReplyClick,
  parentComment,
  onScrollToComment,
  isHighlighted = false,
  canEdit = false,
  canDelete = false,
  onEditComment,
  onDeleteComment,
  onMarkAsRead,
}: {
  comment: BlockComment;
  mode: CommentMode;
  onReplyClick: (parentId: string, authorName: string) => void;
  parentComment?: BlockComment;
  onScrollToComment?: (commentId: string) => void;
  isHighlighted?: boolean;
  canEdit?: boolean;
  canDelete?: boolean;
  onEditComment?: (commentId: string, newBody: string) => Promise<void>;
  onDeleteComment?: (commentId: string) => Promise<void>;
  onMarkAsRead?: (commentId: string) => Promise<void>;
}) {
  const [isEditing, setIsEditing] = useState(false);
  const [editBody, setEditBody] = useState(comment.body);
  const [editLoading, setEditLoading] = useState(false);
  const [editError, setEditError] = useState<string | null>(null);
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [markReadLoading, setMarkReadLoading] = useState(false);

  const isUnread = comment.is_read_by_me !== true;

  useEffect(() => {
    if (!isEditing) setEditBody(comment.body);
  }, [comment.body, isEditing]);

  const bodyUnchanged = editBody.trim() === comment.body.trim();

  const handleStartEdit = () => {
    setEditBody(comment.body);
    setEditError(null);
    setIsEditing(true);
  };

  const handleSaveEdit = async () => {
    if (!editBody.trim() || bodyUnchanged || !onEditComment) return;
    setEditLoading(true);
    setEditError(null);
    try {
      await onEditComment(comment.id, editBody.trim());
      setIsEditing(false);
    } catch {
      setEditError('No se pudo guardar. Inténtalo de nuevo.');
    } finally {
      setEditLoading(false);
    }
  };

  const handleCancelEdit = () => {
    setEditBody(comment.body);
    setEditError(null);
    setIsEditing(false);
  };

  const handleDelete = async () => {
    if (!onDeleteComment) return;
    setDeleteLoading(true);
    try {
      await onDeleteComment(comment.id);
    } finally {
      setDeleteLoading(false);
      setConfirmDelete(false);
    }
  };

  const handleMarkAsRead = async () => {
    if (!onMarkAsRead || !isUnread) return;
    setMarkReadLoading(true);
    try {
      await onMarkAsRead(comment.id);
    } finally {
      setMarkReadLoading(false);
    }
  };

  return (
    <div id={`comment-${comment.id}`} className="relative group/comment">
      {/* Header */}
      <div className="flex items-center justify-between mb-1.5">
        <span className="text-xs font-black text-text-primary dark:text-text-dark-primary">
          {comment.author?.name || 'Usuario'}
        </span>
        <span className="text-2xs text-text-muted font-bold uppercase tracking-wider opacity-70 inline-flex items-center gap-1.5">
          {formatTime(comment.created_at)}
          {comment.is_edited && (
            <span className="opacity-50 normal-case tracking-normal font-medium italic">(editado)</span>
          )}
        </span>
      </div>

      {/* Edit form */}
      {isEditing ? (
        <div className="space-y-2">
          <textarea
            value={editBody}
            onChange={(e) => { setEditBody(e.target.value); setEditError(null); }}
            onKeyDown={(e) => { if (e.key === 'Escape') handleCancelEdit(); }}
            className="w-full p-3 text-sm rounded-xl border border-odoo-purple/60 bg-white dark:bg-ui-dark-bg text-text-primary dark:text-text-dark-primary focus:ring-2 focus:ring-odoo-purple/20 focus:border-odoo-purple outline-none transition-all resize-none shadow-inner"
            rows={Math.max(3, editBody.split('\n').length)}
            autoFocus
          />
          {editError && (
            <p className="text-xs text-danger-dark font-medium">{editError}</p>
          )}
          <div className="flex items-center gap-2 justify-end">
            <button
              type="button"
              onClick={handleCancelEdit}
              disabled={editLoading}
              className="px-3 py-1.5 text-xs font-bold rounded-lg border border-ui-border dark:border-ui-dark-border text-text-muted hover:text-text-primary dark:hover:text-text-dark-primary transition-colors disabled:opacity-40"
            >
              Cancelar
            </button>
            <button
              type="button"
              onClick={handleSaveEdit}
              disabled={!editBody.trim() || bodyUnchanged || editLoading}
              className="px-3 py-1.5 text-xs font-bold rounded-lg bg-odoo-purple text-text-inverse hover:bg-odoo-purple/90 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
            >
              {editLoading ? 'Guardando…' : 'Guardar'}
            </button>
          </div>
        </div>
      ) : (
        /* Bubble */
        <div className={`flex ${isUnread ? '' : 'opacity-80'}`}>
          {isUnread && (
            <div
              className="w-1 bg-odoo-purple rounded-l-xl shrink-0 self-stretch"
              aria-hidden="true"
            />
          )}
          <div
            className={`relative flex-1 min-w-0 text-sm leading-relaxed p-4 border shadow-sm break-words whitespace-pre-wrap transition-all duration-500 ${
              isUnread ? 'rounded-r-xl rounded-l-none' : 'rounded-xl'
            } ${
              isHighlighted
                ? 'bg-odoo-purple/10 dark:bg-odoo-purple/30 border-odoo-purple ring-2 ring-odoo-purple/50 text-text-primary dark:text-text-dark-primary'
                : 'bg-ui-body/30 dark:bg-ui-dark-bg border-ui-border/50 text-text-primary dark:text-text-dark-primary'
            }`}
          >
          {parentComment && onScrollToComment && (
            <QuotedReply
              parent={parentComment}
              onClick={() => onScrollToComment(parentComment.id)}
            />
          )}
          {comment.body}

          {/* Hover action pill */}
          {(canEdit || canDelete || (isUnread && onMarkAsRead)) && !confirmDelete && (
            <span className="absolute -top-3 right-2 opacity-0 group-hover/comment:opacity-100 transition-opacity duration-150 inline-flex items-center gap-0.5 bg-white dark:bg-ui-dark-card border border-ui-border dark:border-ui-dark-border rounded-full shadow-md px-1 py-0.5 z-10">
              {isUnread && onMarkAsRead && (
                <button
                  type="button"
                  onClick={handleMarkAsRead}
                  disabled={markReadLoading}
                  aria-label="Marcar como leído"
                  title="Marcar como leído"
                  className="p-1 rounded-full text-text-muted hover:text-odoo-purple hover:bg-odoo-purple/10 transition-colors disabled:opacity-40"
                >
                  <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                </button>
              )}
              {isUnread && onMarkAsRead && (canEdit || canDelete) && (
                <span className="w-px h-3 bg-ui-border dark:bg-ui-dark-border" />
              )}
              {canEdit && (
                <button
                  type="button"
                  onClick={handleStartEdit}
                  aria-label="Editar"
                  className="p-1 rounded-full text-text-muted hover:text-odoo-purple hover:bg-odoo-purple/10 transition-colors"
                >
                  <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 0l.172.172a2 2 0 010 2.828L12 15H9v-3z" />
                  </svg>
                </button>
              )}
              {canEdit && canDelete && (
                <span className="w-px h-3 bg-ui-border dark:bg-ui-dark-border" />
              )}
              {canDelete && (
                <button
                  type="button"
                  onClick={() => setConfirmDelete(true)}
                  aria-label="Eliminar"
                  className="p-1 rounded-full text-text-muted hover:text-danger-dark hover:bg-danger-light dark:hover:bg-danger/10 transition-colors"
                >
                  <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2" />
                  </svg>
                </button>
              )}
            </span>
          )}
          </div>
        </div>
      )}

      {/* Delete confirmation */}
      {confirmDelete && !isEditing && (
        <div className="mt-1.5 flex items-center justify-end gap-2">
          <span className="text-xs text-text-muted">¿Eliminar este comentario?</span>
          <button
            type="button"
            onClick={() => setConfirmDelete(false)}
            disabled={deleteLoading}
            className="px-2.5 py-1 text-xs font-bold rounded-lg border border-ui-border dark:border-ui-dark-border text-text-muted hover:text-text-primary transition-colors disabled:opacity-40"
          >
            No
          </button>
          <button
            type="button"
            onClick={handleDelete}
            disabled={deleteLoading}
            className="px-2.5 py-1 text-xs font-bold rounded-lg bg-danger text-text-inverse hover:bg-danger/90 transition-colors disabled:opacity-40"
          >
            {deleteLoading ? '…' : 'Eliminar'}
          </button>
        </div>
      )}

      {/* Reply button — visible on hover */}
      {mode !== 'creator-readonly' && !isEditing && !confirmDelete && (
        <div className="mt-1 h-5">
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              onReplyClick(comment.id, comment.author?.name || 'Usuario');
            }}
            className="text-xs font-bold text-odoo-purple hover:underline opacity-0 group-hover/comment:opacity-100 transition-opacity duration-150"
          >
            Responder
          </button>
        </div>
      )}
    </div>
  );
}

// ── BlockCommentsCard ──────────────────────────────────────────────────────────

type BlockCommentsCardProps = {
  mode: CommentMode;
  blockSortOrder?: number | string | null;
  blockComments: BlockComment[];
  allComments: BlockComment[];
  onSendMessage?: (parentId: string | null, body: string) => Promise<void>;
  commentLoading?: boolean;
  canAddComments?: boolean;
  commentingClosed?: boolean;
  headerRef?: RefObject<HTMLDivElement | null>;
  onClose: () => void;
  currentUserId?: string;
  canDeleteAnyComment?: boolean;
  onEditComment?: (commentId: string, newBody: string) => Promise<void>;
  onDeleteComment?: (commentId: string) => Promise<void>;
  onMarkAsRead?: (commentId: string) => Promise<void>;
  autoMarkUnreadOnOpen?: boolean;
};

export function BlockCommentsCard({
  mode,
  blockSortOrder,
  blockComments,
  allComments,
  commentingClosed = false,
  onSendMessage,
  commentLoading = false,
  canAddComments = true,
  headerRef,
  onClose,
  currentUserId,
  canDeleteAnyComment = false,
  onEditComment,
  onDeleteComment,
  onMarkAsRead,
  autoMarkUnreadOnOpen = true,
}: BlockCommentsCardProps) {
  const { t } = useTranslation(['templates', 'documents']);
  const [replyingTo, setReplyingTo] = useState<{ id: string; name: string } | null>(null);
  const [replyBody, setReplyBody] = useState('');
  const [highlightedCommentId, setHighlightedCommentId] = useState<string | null>(null);
  const [deletedOpen, setDeletedOpen] = useState(false);

  const commentById = new Map(allComments.map(c => [c.id, c]));

  const sortedComments = [...blockComments].sort(
    (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime()
  );

  const activeComments = sortedComments.filter(c => !c.is_deleted);
  const deletedComments = sortedComments.filter(c => c.is_deleted);

  const unreadSignature = activeComments
    .filter((c) => c.is_read_by_me !== true)
    .map((c) => c.id)
    .sort()
    .join('|');

  useEffect(() => {
    if (!autoMarkUnreadOnOpen || !onMarkAsRead || !unreadSignature) return;

    const unreadIds = unreadSignature.split('|');
    void (async () => {
      for (const commentId of unreadIds) {
        try {
          await onMarkAsRead(commentId);
        } catch {
          break;
        }
      }
    })();
  }, [autoMarkUnreadOnOpen, blockSortOrder, onMarkAsRead, unreadSignature]);

  const canEditComment = (comment: BlockComment) =>
    !commentingClosed && canEditOwnBlockComment(currentUserId, comment.author_id);

  const canDeleteComment = (comment: BlockComment) =>
    !commentingClosed && (canEditOwnBlockComment(currentUserId, comment.author_id) || canDeleteAnyComment);

  const handleSubmit = async () => {
    if (!replyBody.trim() || !onSendMessage) return;
    try {
      await onSendMessage(replyingTo ? replyingTo.id : null, replyBody.trim());
      setReplyBody('');
      setReplyingTo(null);
    } catch (_) {
      // handled by parent
    }
  };

  const handleReplyClick = (parentId: string, authorName: string) => {
    setReplyingTo({ id: parentId, name: authorName });
    setTimeout(() => {
      document.getElementById('block-comments-chat-input')?.focus();
    }, 50);
  };

  const handleScrollToComment = (commentId: string) => {
    setHighlightedCommentId(commentId);
    document.getElementById(`comment-${commentId}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => setHighlightedCommentId(null), 1500);
  };

  const replyingToMessage = replyingTo ? commentById.get(replyingTo.id) : undefined;

  return (
    <div className="bg-white dark:bg-ui-dark-card rounded-xl shadow-2xl border border-ui-border/60 dark:border-ui-dark-border flex flex-col overflow-hidden animate-in fade-in slide-in-from-right-4 duration-300 h-full">
      <ViewCardHeader
        blockSortOrder={blockSortOrder ?? '?'}
        title={t('templates:comments.title')}
        onClose={onClose}
        headerRef={headerRef}
      />

      {commentingClosed && (
        <div className="px-4 py-2 border-b border-ui-border dark:border-ui-dark-border bg-ui-body/50 dark:bg-ui-dark-bg/50 shrink-0">
          <p className="text-xs text-text-muted dark:text-text-dark-muted font-bold uppercase tracking-widest text-center">
            Comentarios cerrados
          </p>
        </div>
      )}

      {/* Flat message list — oldest first */}
      <div className="flex-1 overflow-y-auto custom-scrollbar px-4 pt-4 pb-4 space-y-5">
        {activeComments.length === 0 && deletedComments.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-32 text-center opacity-40">
            <svg className="w-10 h-10 mb-3 text-text-muted dark:text-text-dark-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
            <p className="text-sm font-medium text-text-muted dark:text-text-dark-muted leading-relaxed">
              No hay mensajes en este bloque.
            </p>
          </div>
        ) : (
          <>
          {activeComments.map((comment) => {
            const parentComment = comment.parent_id
              ? commentById.get(comment.parent_id)
              : undefined;
            return (
              <CommentItem
                key={comment.id}
                comment={comment}
                mode={mode}
                onReplyClick={handleReplyClick}
                parentComment={parentComment}
                onScrollToComment={handleScrollToComment}
                isHighlighted={highlightedCommentId === comment.id}
                canEdit={canEditComment(comment)}
                canDelete={canDeleteComment(comment)}
                onEditComment={onEditComment}
                onDeleteComment={onDeleteComment}
                onMarkAsRead={onMarkAsRead}
              />
            );
          })}

          {deletedComments.length > 0 && (
            <div className="pt-1">
              <button
                type="button"
                onClick={() => setDeletedOpen(o => !o)}
                className="w-full flex items-center gap-2 py-1.5 group/deleted-toggle"
                aria-expanded={deletedOpen}
              >
                <span className="flex-1 border-t border-dashed border-text-muted/20 dark:border-text-dark-muted/15" />
                <span className="inline-flex items-center gap-1.5 text-2xs text-text-muted/50 dark:text-text-dark-muted/40 font-medium shrink-0 select-none">
                  <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2" />
                  </svg>
                  {deletedComments.length === 1
                    ? '1 comentario eliminado'
                    : `${deletedComments.length} comentarios eliminados`}
                  <svg
                    className={`w-3 h-3 transition-transform duration-200 ${deletedOpen ? 'rotate-180' : ''}`}
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}
                  >
                    <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                  </svg>
                </span>
                <span className="flex-1 border-t border-dashed border-text-muted/20 dark:border-text-dark-muted/15" />
              </button>

              {deletedOpen && (
                <div className="mt-1 rounded-xl border border-ui-border/40 dark:border-ui-dark-border/40 overflow-hidden animate-in fade-in slide-in-from-top-1 duration-150">
                  {deletedComments.map((dc, i) => (
                    <div
                      key={dc.id}
                      className={`flex items-center gap-3 px-3 py-2.5 ${
                        i < deletedComments.length - 1
                          ? 'border-b border-ui-border/30 dark:border-ui-dark-border/30'
                          : ''
                      } bg-ui-body/20 dark:bg-ui-dark-bg/20`}
                    >
                      <svg className="w-3.5 h-3.5 text-danger/70 dark:text-danger/60 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2" />
                      </svg>
                      <span className="text-xs text-text-muted dark:text-text-dark-muted flex-1 min-w-0 truncate">
                        <span className="font-semibold">{dc.author?.name || 'Usuario'}</span>
                        {dc.deleted_by_name && (
                          <span className="font-normal opacity-70">
                            {' · '}eliminado por {dc.deleted_by_name}
                          </span>
                        )}
                      </span>
                      {dc.deleted_at && (
                        <span className="text-2xs text-text-muted/50 dark:text-text-dark-muted/40 font-medium shrink-0">
                          {formatTime(dc.deleted_at)}
                        </span>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}
          </>
        )}
      </div>

      {/* Footer — chat input */}
      {mode !== 'creator-readonly' && (
        canAddComments ? (
          <div className="px-4 pt-3 pb-4 border-t border-ui-border dark:border-ui-dark-border shrink-0 bg-ui-body/10 dark:bg-ui-dark-bg/30">
            {replyingTo && (
              <div className="flex items-stretch mb-2 rounded-lg overflow-hidden border-l-4 border-odoo-purple bg-odoo-purple/10 animate-in slide-in-from-bottom-1">
                <div className="flex-1 px-3 py-2 min-w-0">
                  <p className="text-2xs font-black text-odoo-purple mb-0.5 truncate">
                    {replyingTo.name}
                  </p>
                  {replyingToMessage && (
                    <p className="text-xs text-text-muted dark:text-text-dark-muted line-clamp-2 break-words">
                      {replyingToMessage.body}
                    </p>
                  )}
                </div>
                <button
                  onClick={() => setReplyingTo(null)}
                  className="px-3 flex items-center justify-center hover:bg-odoo-purple/20 transition-colors text-odoo-purple"
                >
                  ✕
                </button>
              </div>
            )}
            <textarea
              id="block-comments-chat-input"
              value={replyBody}
              onChange={(e) => setReplyBody(e.target.value)}
              placeholder={t('templates:comments.messagePlaceholder')}
              className="w-full h-20 p-3 text-sm rounded-xl border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg text-text-primary dark:text-text-dark-primary focus:ring-2 focus:ring-odoo-purple/20 focus:border-odoo-purple outline-none transition-all resize-none shadow-inner"
            />
            <div className="mt-3 flex justify-end">
              <Button
                variant="primary"
                size="md"
                className="text-xs font-black uppercase tracking-widest px-6 py-2 shadow-md"
                onClick={handleSubmit}
                loading={commentLoading}
                disabled={!replyBody.trim() || commentLoading}
              >
                Enviar
              </Button>
            </div>
          </div>
        ) : (
          <div className="px-4 pb-4 pt-3 border-t border-ui-border dark:border-ui-dark-border shrink-0">
            <div className="p-4 rounded-xl bg-ui-body/5 dark:bg-ui-dark-border/20 border border-ui-border dark:border-ui-dark-border text-center">
              <p className="text-xs text-text-muted dark:text-text-dark-muted italic font-medium">
                No puedes añadir más mensajes porque ya has finalizado tu validación.
              </p>
            </div>
          </div>
        )
      )}
    </div>
  );
}
