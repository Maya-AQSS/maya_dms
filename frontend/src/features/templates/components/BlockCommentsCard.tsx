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

// ── BlockCommentsCard ──────────────────────────────────────────────────────────

type BlockCommentsCardProps = {
  mode: CommentMode;
  blockSortOrder?: number | string | null;

  // All comments for this block (top-level + replies).
  blockComments: BlockComment[];
  // All comments for the template (used to look up replies by parent_id).
  allComments: BlockComment[];

  // validator mode — new top-level comment
  newCommentBody?: string;
  onNewCommentBodyChange?: (v: string) => void;
  onAddComment?: () => void;
  commentLoading?: boolean;
  canAddComments?: boolean;

  // creator-edit mode — per-thread actions
  onReply?: (parentCommentId: string, body: string) => Promise<void>;

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
  newCommentBody,
  onNewCommentBodyChange,
  onAddComment,
  commentLoading,
  canAddComments = true,
  onReply,
  headerRef,
  onClose,
}: BlockCommentsCardProps) {
  const [replyingTo, setReplyingTo] = useState<string | null>(null);
  const [replyBody, setReplyBody] = useState('');
  const [replyLoading, setReplyLoading] = useState(false);

  const rootComments = blockComments.filter(c => !c.parent_id);

  const handleReply = async (parentId: string) => {
    if (!replyBody.trim() || !onReply) return;
    setReplyLoading(true);
    try {
      await onReply(parentId, replyBody.trim());
      setReplyBody('');
      setReplyingTo(null);
    } finally {
      setReplyLoading(false);
    }
  };

  return (
    <div className="bg-white dark:bg-ui-dark-card rounded-xl shadow-2xl border border-ui-border/60 dark:border-ui-dark-border flex flex-col overflow-hidden animate-in fade-in slide-in-from-right-4 duration-300">
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
      <div className="max-h-[50vh] overflow-y-auto custom-scrollbar px-4 pt-4 pb-2 space-y-6">
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
          <div className="space-y-6">
            {rootComments.map((comment) => {
              const replies = allComments.filter(r => r.parent_id === comment.id);

              return (
                <div key={comment.id} className="space-y-3">
                  {/* Root comment */}
                  <div className="group relative pl-5">
                    <div className="absolute left-0 top-0 bottom-0 w-1 rounded-full transition-colors bg-ui-border dark:bg-ui-dark-border group-hover:bg-odoo-purple/40" />

                    <div className="flex items-center justify-between mb-1.5">
                      <span className="text-xs font-black text-text-primary dark:text-text-dark-primary">
                        {comment.author?.name || 'Validador'}
                      </span>
                      <span className="text-[10px] text-text-muted font-bold uppercase tracking-wider opacity-70">
                        {new Date(comment.created_at).toLocaleDateString()}
                      </span>
                    </div>

                    <div className="text-sm leading-relaxed p-4 rounded-xl border shadow-sm text-text-primary dark:text-text-dark-primary bg-ui-body/30 dark:bg-ui-dark-bg border-ui-border/50 dark:border-ui-dark-border/50">
                      {comment.body}
                    </div>

                    {/* Reply action — creator-edit only */}
                    {mode === 'creator-edit' && (
                      <div className="mt-2 flex items-center gap-4">
                        <button
                          type="button"
                          onClick={() => setReplyingTo(replyingTo === comment.id ? null : comment.id)}
                          className="text-xs font-bold text-odoo-purple hover:underline"
                        >
                          {replyingTo === comment.id ? 'Cancelar' : 'Responder'}
                        </button>
                      </div>
                    )}
                  </div>

                  {/* Replies */}
                  {replies.length > 0 && (
                    <div className="ml-8 space-y-3">
                      {replies.map(r => (
                        <div key={r.id} className="relative pl-3 border-l-2 border-ui-border/30 dark:border-ui-dark-border/30">
                          <div className="flex items-center justify-between mb-1">
                            <span className="text-xs font-bold text-text-primary dark:text-text-dark-primary">
                              {r.author?.name || 'Usuario'}
                            </span>
                            <span className="text-[10px] text-text-muted font-bold uppercase tracking-widest">
                              {new Date(r.created_at).toLocaleDateString()}
                            </span>
                          </div>
                          <div className="text-xs text-text-primary dark:text-text-dark-primary bg-ui-body/10 dark:bg-ui-dark-bg p-3 rounded-lg border border-ui-border/20 dark:border-ui-dark-border/20 italic">
                            {r.body}
                          </div>
                        </div>
                      ))}
                    </div>
                  )}

                  {/* Inline reply form — creator-edit only */}
                  {mode === 'creator-edit' && replyingTo === comment.id && (
                    <div className="ml-8 space-y-2 animate-in slide-in-from-top-1">
                      <textarea
                        value={replyBody}
                        onChange={e => setReplyBody(e.target.value)}
                        placeholder="Escribe una respuesta..."
                        className="w-full text-xs p-3 rounded-xl border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg text-text-primary dark:text-text-dark-primary focus:ring-2 focus:ring-odoo-purple/20 focus:border-odoo-purple outline-none transition-all resize-none h-20 shadow-inner"
                      />
                      <div className="flex justify-end">
                        <Button
                          size="xs"
                          variant="primary"
                          loading={replyLoading}
                          disabled={!replyBody.trim() || replyLoading}
                          onClick={() => void handleReply(comment.id)}
                        >
                          Enviar respuesta
                        </Button>
                      </div>
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Footer — validator mode: new top-level comment input */}
      {mode === 'validator' && (
        canAddComments ? (
          <div className="px-4 pt-3 pb-4 border-t border-ui-border dark:border-ui-dark-border shrink-0">
            <textarea
              value={newCommentBody}
              onChange={(e) => onNewCommentBodyChange?.(e.target.value)}
              placeholder="Escribe un mensaje de revisión..."
              className="w-full h-24 p-3 text-sm rounded-xl border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg text-text-primary dark:text-text-dark-primary focus:ring-2 focus:ring-odoo-purple/20 focus:border-odoo-purple outline-none transition-all resize-none shadow-inner"
            />
            <div className="mt-3 flex justify-end">
              <Button
                variant="primary"
                size="md"
                className="text-xs font-black uppercase tracking-widest px-6 py-2"
                onClick={onAddComment}
                loading={commentLoading}
                disabled={!newCommentBody?.trim() || commentLoading}
              >
                Enviar mensaje
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
