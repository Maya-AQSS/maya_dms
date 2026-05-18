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
  return (
    <div
      ref={headerRef}
      className="flex items-stretch border-b border-ui-border dark:border-ui-dark-border shrink-0 bg-white dark:bg-ui-dark-card"
    >
      <div className="px-4 py-4 flex items-center shrink-0 border-r border-ui-border dark:border-ui-dark-border bg-ui-body/30 dark:bg-ui-dark-bg/50">
        <span className="text-[10px] font-black uppercase tracking-[0.2em] text-odoo-purple">
          Bloque #{blockSortOrder ?? '?'}
        </span>
      </div>
      <div className="flex-1 px-5 py-4 flex items-center justify-between min-w-0">
        <span className="text-[10px] font-black uppercase tracking-[0.15em] text-text-primary dark:text-text-dark-primary truncate">
          {title}
        </span>
        <button
          aria-label="Cerrar panel"
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
// WhatsApp-style quoted bubble shown inside a reply message.

function QuotedReply({
  parent,
  onClick,
}: {
  parent: BlockComment;
  onClick: () => void;
}) {
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
        <p className="text-[11px] font-black text-odoo-purple mb-0.5 truncate">
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
}: {
  comment: BlockComment;
  mode: CommentMode;
  onReplyClick: (parentId: string, authorName: string) => void;
  parentComment?: BlockComment;
  onScrollToComment?: (commentId: string) => void;
  isHighlighted?: boolean;
}) {
  return (
    <div id={`comment-${comment.id}`} className="relative">
      <div className="flex items-center justify-between mb-1.5">
        <span className="text-xs font-black text-text-primary dark:text-text-dark-primary">
          {comment.author?.name || 'Usuario'}
        </span>
        <span className="text-[10px] text-text-muted font-bold uppercase tracking-wider opacity-70">
          {formatTime(comment.created_at)}
        </span>
      </div>

      <div
        className={`text-sm leading-relaxed p-4 rounded-xl border shadow-sm break-words whitespace-pre-wrap transition-all duration-500 ${
          isHighlighted
            ? 'bg-[#E3F2FD] dark:bg-odoo-purple/30 border-odoo-purple ring-2 ring-odoo-purple/50 text-text-primary dark:text-text-dark-primary'
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

  const commentById = new Map(allComments.map(c => [c.id, c]));

  const sortedComments = [...blockComments].sort(
    (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime()
  );

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
        title="Comentarios de Revisión"
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
        {sortedComments.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-32 text-center opacity-40">
            <svg className="w-10 h-10 mb-3 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
            <p className="text-sm font-medium text-text-muted leading-relaxed">
              No hay mensajes en este bloque.
            </p>
          </div>
        ) : (
          sortedComments.map((comment) => {
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
              />
            );
          })
        )}
      </div>

      {/* Footer — chat input */}
      {mode !== 'creator-readonly' && (
        canAddComments ? (
          <div className="px-4 pt-3 pb-4 border-t border-ui-border dark:border-ui-dark-border shrink-0 bg-ui-body/10 dark:bg-ui-dark-bg/30">
            {replyingTo && (
              <div className="flex items-stretch mb-2 rounded-lg overflow-hidden border-l-4 border-odoo-purple bg-odoo-purple/10 animate-in slide-in-from-bottom-1">
                <div className="flex-1 px-3 py-2 min-w-0">
                  <p className="text-[11px] font-black text-odoo-purple mb-0.5 truncate">
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
