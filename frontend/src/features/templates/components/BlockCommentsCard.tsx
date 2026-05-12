import { useState, type RefObject } from 'react';
import { Button } from '@maya/shared-ui-react';

// ── Types ─────────────────────────────────────────────────────────────────────

export type CommentMode = 'validator' | 'creator-readonly' | 'creator-edit';

export type BlockComment = {
  id: string;
  blockable_id: string | null;
  author_id: string;
  author?: { id: string; name: string };
  body: string;
  created_at: string;
  parent_id?: string | null;
};

// ── Helpers ───────────────────────────────────────────────────────────────────

const formatTime = (isoString: string) => {
  return new Date(isoString).toLocaleString('es-ES', { dateStyle: 'short', timeStyle: 'short' });
};

// ── ViewCardHeader ─────────────────────────────────────────────────────────────
// Git-diff-style two-zone header: [BLOQUE #N] | [title ✕]

export function ViewCardHeader({
  blockSortOrder,
  title,
  onClose,
  headerRef,
}: {
  blockSortOrder: unknown;
  title: string;
  onClose: () => void;
  headerRef?: RefObject<HTMLDivElement | null>;
}) {
  return (
    <div
      ref={headerRef}
      className="flex items-stretch border-b border-ui-border dark:border-ui-dark-border shrink-0"
    >
      <div className="px-4 py-3 flex items-center shrink-0">
        <span className="text-[11px] font-black uppercase tracking-widest text-odoo-purple">
          Bloque #{blockSortOrder as any}
        </span>
      </div>
      <div className="w-px bg-ui-border dark:bg-ui-dark-border self-stretch" />
      <div className="flex-1 px-4 py-3 flex items-center justify-between min-w-0">
        <span className="text-[11px] font-black uppercase tracking-widest text-text-primary dark:text-text-dark-primary truncate">
          {title}
        </span>
        <button
          aria-label="Cerrar panel de revisión"
          onClick={onClose}
          className="group ml-3 w-7 h-7 rounded-full hover:bg-ui-body dark:hover:bg-ui-dark-bg flex items-center justify-center text-text-muted transition-all shrink-0"
        >
          <span className="block text-sm leading-none group-hover:rotate-90 transition-transform duration-200">✕</span>
        </button>
      </div>
    </div>
  );
}

// ── Flat Comment Rendering (Instagram Style) ─────────────────────────────────

function getFlatReplies(rootId: string, allComments: BlockComment[]): BlockComment[] {
  const flat: BlockComment[] = [];
  function traverse(parentId: string) {
    const children = allComments.filter(c => c.parent_id === parentId);
    // Sort oldest first
    children.sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime());
    for (const child of children) {
      flat.push(child);
      traverse(child.id);
    }
  }
  traverse(rootId);
  return flat;
}

function CommentItem({
  comment,
  mode,
  onReplyClick,
  onMentionClick,
  mentionName,
  mentionId,
  isSubReply = false,
  isHighlighted = false,
}: {
  comment: BlockComment;
  mode: CommentMode;
  onReplyClick: (parentId: string, authorName: string) => void;
  onMentionClick?: (mentionId: string) => void;
  mentionName?: string;
  mentionId?: string;
  isSubReply?: boolean;
  isHighlighted?: boolean;
}) {
  return (
    <div className="relative">
      <div className="flex items-center justify-between mb-1.5">
        <span className="text-xs font-black text-text-primary dark:text-text-dark-primary">
          {comment.author?.name || 'Usuario'}
        </span>
        <span className="text-[10px] text-text-muted font-bold uppercase tracking-wider opacity-70">
          {formatTime(comment.created_at)}
        </span>
      </div>

      <div 
        id={`comment-${comment.id}`}
        onClick={() => {
          if (mentionId && onMentionClick) onMentionClick(mentionId);
        }}
        className={`text-sm leading-relaxed p-4 rounded-xl border shadow-sm break-words whitespace-pre-wrap transition-all duration-500 ${
          mentionId ? 'cursor-pointer hover:border-odoo-purple/50' : ''
        } ${
          isHighlighted 
            ? 'bg-[#E3F2FD] dark:bg-odoo-purple/30 border-odoo-purple ring-2 ring-odoo-purple/50 text-text-primary dark:text-text-dark-primary' 
            : isSubReply 
              ? 'bg-ui-body/10 dark:bg-ui-dark-bg italic border-ui-border/50 text-text-primary dark:text-text-dark-primary' 
              : 'bg-ui-body/30 dark:bg-ui-dark-bg border-ui-border/50 text-text-primary dark:text-text-dark-primary'
        }`}
      >
        {mentionName && (
          <div className="mb-2">
            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-odoo-purple/10 text-odoo-purple text-[10px] font-black uppercase tracking-widest">
              <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
              </svg>
              Responde a {mentionName}
            </span>
          </div>
        )}
        {comment.body}
      </div>

      {mode !== 'creator-readonly' && (
        <div className="mt-2 flex items-center gap-4">
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              onReplyClick(comment.id, comment.author?.name || 'Usuario');
            }}
            className="text-xs font-bold text-odoo-purple hover:underline relative z-10"
          >
            Responder
          </button>
        </div>
      )}
    </div>
  );
}

function CommentThread({
  rootComment,
  allComments,
  mode,
  onReplyClick,
  onMentionClick,
  highlightedCommentId,
}: {
  rootComment: BlockComment;
  allComments: BlockComment[];
  mode: CommentMode;
  onReplyClick: (parentId: string, authorName: string) => void;
  onMentionClick: (mentionId: string) => void;
  highlightedCommentId: string | null;
}) {
  const flatReplies = getFlatReplies(rootComment.id, allComments);

  return (
    <div className="space-y-3 relative pl-5 group">
      <div className="absolute left-0 top-0 bottom-0 w-1 rounded-full transition-colors bg-ui-border dark:bg-ui-dark-border group-hover:bg-odoo-purple/40" />

      <CommentItem
        comment={rootComment}
        mode={mode}
        onReplyClick={onReplyClick}
        onMentionClick={onMentionClick}
        isHighlighted={highlightedCommentId === rootComment.id}
      />

      {flatReplies.length > 0 && (
        <div className="pt-2 space-y-5 ml-4 pl-4 border-l-2 border-ui-border/30 dark:border-ui-dark-border/30">
          {flatReplies.map(reply => {
            const parent = allComments.find(c => c.id === reply.parent_id);
            return (
              <CommentItem
                key={reply.id}
                comment={reply}
                mode={mode}
                onReplyClick={onReplyClick}
                onMentionClick={onMentionClick}
                isSubReply={true}
                mentionName={parent?.author?.name || 'Usuario'}
                mentionId={parent?.id}
                isHighlighted={highlightedCommentId === reply.id}
              />
            );
          })}
        </div>
      )}
    </div>
  );
}

// ── BlockCommentsCard ──────────────────────────────────────────────────────────

type BlockCommentsCardProps = {
  mode: CommentMode;
  blockSortOrder?: number | string | null;

  // All comments for this block (top-level + replies).
  blockComments: BlockComment[];
  // All comments for the template (used to look up replies by parent_id).
  allComments: BlockComment[];

  // unified action
  onSendMessage?: (parentId: string | null, body: string) => Promise<void>;
  commentLoading?: boolean;
  canAddComments?: boolean;

  // shared
  commentingClosed?: boolean;
  headerRef?: RefObject<HTMLDivElement | null>;
  onClose: () => void;
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
}: BlockCommentsCardProps) {
  const [replyingTo, setReplyingTo] = useState<{ id: string; name: string } | null>(null);
  const [replyBody, setReplyBody] = useState('');
  const [highlightedCommentId, setHighlightedCommentId] = useState<string | null>(null);

  const rootComments = blockComments.filter(c => !c.parent_id);

  const handleSubmit = async () => {
    if (!replyBody.trim() || !onSendMessage) return;
    try {
      await onSendMessage(replyingTo ? replyingTo.id : null, replyBody.trim());
      setReplyBody('');
      setReplyingTo(null);
    } catch (e) {
      // handled by parent
    }
  };

  const handleReplyClick = (parentId: string, authorName: string) => {
    setReplyingTo({ id: parentId, name: authorName });
    // If we wanted to, we could focus the textarea here. 
    // Using a simple ID for the textarea allows us to focus it.
    setTimeout(() => {
      document.getElementById('block-comments-chat-input')?.focus();
    }, 50);
  };

  const handleMentionClick = (mentionId: string) => {
    setHighlightedCommentId(mentionId);
    document.getElementById(`comment-${mentionId}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => {
      setHighlightedCommentId(null);
    }, 1500);
  };

  return (
    <div className="bg-white dark:bg-ui-dark-card rounded-xl shadow-2xl border border-ui-border/60 dark:border-ui-dark-border flex flex-col overflow-hidden animate-in fade-in slide-in-from-right-4 duration-300 h-full">
      <ViewCardHeader
        blockSortOrder={blockSortOrder ?? '?'}
        title="Comentarios de Revisión"
        onClose={onClose}
        headerRef={headerRef}
      />

      {/* Commenting closed notice */}
      {commentingClosed && (
        <div className="px-4 py-2 border-b border-ui-border dark:border-ui-dark-border bg-ui-body/50 dark:bg-ui-dark-bg/50 shrink-0">
          <p className="text-xs text-text-muted dark:text-text-dark-muted font-bold uppercase tracking-widest text-center">
            Comentarios cerrados
          </p>
        </div>
      )}

      {/* Comment thread list */}
      <div className="flex-1 overflow-y-auto custom-scrollbar px-4 pt-4 pb-4 space-y-6">
        {rootComments.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-32 text-center opacity-40">
            <svg className="w-10 h-10 mb-3 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
            <p className="text-sm font-medium text-text-muted leading-relaxed">
              No hay mensajes en este bloque.
            </p>
          </div>
        ) : (
          <div className="space-y-6 pb-6">
            {rootComments.map((comment) => (
              <CommentThread
                key={comment.id}
                rootComment={comment}
                allComments={allComments}
                mode={mode}
                onReplyClick={handleReplyClick}
                onMentionClick={handleMentionClick}
                highlightedCommentId={highlightedCommentId}
              />
            ))}
          </div>
        )}
      </div>

      {/* Footer — unified chat input */}
      {mode !== 'creator-readonly' && (
        canAddComments ? (
          <div className="px-4 pt-3 pb-4 border-t border-ui-border dark:border-ui-dark-border shrink-0 bg-ui-body/10 dark:bg-ui-dark-bg/30">
            {replyingTo && (
              <div className="flex items-center justify-between mb-2 px-2 py-1.5 bg-odoo-purple/10 rounded-lg text-odoo-purple animate-in slide-in-from-bottom-1">
                <span className="text-[11px] font-black uppercase tracking-widest truncate">
                  Respondiendo a {replyingTo.name}
                </span>
                <button
                  onClick={() => setReplyingTo(null)}
                  className="w-5 h-5 flex items-center justify-center rounded-full hover:bg-odoo-purple/20 transition-colors"
                >
                  ✕
                </button>
              </div>
            )}
            <textarea
              id="block-comments-chat-input"
              value={replyBody}
              onChange={(e) => setReplyBody(e.target.value)}
              placeholder="Escribe un mensaje..."
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
