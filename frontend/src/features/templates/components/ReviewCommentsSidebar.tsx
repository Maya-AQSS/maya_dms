import { useState } from 'react';
import { Button } from '@maya/shared-ui-react';
import { apiFetchJson } from '../../../api/http';
import { resolveComment } from '../../../api/templates';

export type ReviewComment = {
  id: string;
  blockable_id: string | null;
  author_id: string;
  author?: { id: string; name: string };
  body: string;
  resolved: boolean;
  created_at: string;
  parent_id?: string | null;
};

type Props = {
  comments: ReviewComment[];
  selectedBlockId: string | null;
  onCommentsChange: (comments: ReviewComment[]) => void;
  onClose: () => void;
  isOwner: boolean;
  resourceId: string;
  resourceType: 'templates' | 'documents';
};

export function ReviewCommentsSidebar({
  comments,
  selectedBlockId,
  onCommentsChange,
  onClose,
  isOwner,
  resourceId,
  resourceType,
}: Props) {
  const [replyingTo, setReplyingTo] = useState<string | null>(null);
  const [replyBody, setReplyBody] = useState('');
  const [replyLoading, setReplyLoading] = useState(false);
  const [resolvingIds, setResolvingIds] = useState<Set<string>>(new Set());

  const blockComments = comments.filter((c) => c.blockable_id === selectedBlockId);
  const topLevelComments = blockComments.filter((c) => !c.parent_id);

  const handleResolve = async (commentId: string) => {
    setResolvingIds((prev) => new Set(prev).add(commentId));
    try {
      await resolveComment(commentId);
      onCommentsChange(
        comments.map((c) => (c.id === commentId ? { ...c, resolved: true } : c))
      );
    } catch (e) {
      console.error('Error resolving comment', e);
    } finally {
      setResolvingIds((prev) => {
        const next = new Set(prev);
        next.delete(commentId);
        return next;
      });
    }
  };

  const handleSendReply = async (parentId: string) => {
    if (!replyBody.trim()) return;
    setReplyLoading(true);
    try {
      const res = await apiFetchJson<{ data: ReviewComment }>(`${resourceType}/${resourceId}/comments`, {
        method: 'POST',
        body: {
          body: replyBody,
          parent_id: parentId,
          blockable_id: selectedBlockId,
        },
      });
      onCommentsChange([...comments, res.data]);
      setReplyBody('');
      setReplyingTo(null);
    } catch (e) {
      console.error('Error sending reply', e);
    } finally {
      setReplyLoading(false);
    }
  };

  return (
    <aside className="w-96 shrink-0 border-l border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card flex flex-col sticky top-0 h-full overflow-hidden shadow-lg">
      {/* Header */}
      <div className="shrink-0 px-5 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center gap-2 bg-danger/5">
        <span className="flex-1 text-xs font-black uppercase tracking-widest text-danger-dark dark:text-danger truncate">
          ⚠ Revisión de bloque
        </span>
        <span className="text-xs text-text-muted font-bold shrink-0">
          {topLevelComments.filter(c => !c.resolved).length} pendientes
        </span>
        <button
          type="button"
          onClick={onClose}
          className="shrink-0 w-6 h-6 flex items-center justify-center rounded-full hover:bg-ui-body dark:hover:bg-ui-dark-bg text-text-muted hover:text-text-primary transition-colors text-sm"
        >
          ✕
        </button>
      </div>

      {/* Comment list */}
      <div className="flex-1 overflow-y-auto px-5 py-5 space-y-8 custom-scrollbar">
        {topLevelComments.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-32 text-center opacity-40">
            <p className="text-sm font-medium text-text-muted">No hay comentarios en este bloque.</p>
          </div>
        ) : (
          topLevelComments.map((c) => {
            const replies = comments.filter((r) => r.parent_id === c.id);
            const isResolved = c.resolved;
            const isResolving = resolvingIds.has(c.id);

            return (
              <div key={c.id} className="space-y-4">
                <div className={`group relative pl-5 ${isResolved ? 'opacity-50' : ''}`}>
                  <div
                    className={`absolute left-0 top-0 bottom-0 w-1 ${isResolved ? 'bg-success/30' : 'bg-danger/30 group-hover:bg-danger/60'
                      } transition-colors rounded-full`}
                  />
                  <div className="flex items-center justify-between mb-1.5 gap-2">
                    <span className="text-xs font-black text-text-primary dark:text-text-dark-primary">
                      {c.author?.name || 'Validador'}
                      {isResolved && (
                        <span className="ml-2 text-xs text-success-dark font-bold uppercase tracking-wider">
                          ✓ Resuelto
                        </span>
                      )}
                    </span>
                    <time className="text-xs text-text-muted font-bold uppercase tracking-wider shrink-0">
                      {new Date(c.created_at).toLocaleDateString()}
                    </time>
                  </div>
                  <div
                    className={`text-sm text-text-secondary dark:text-text-dark-secondary leading-relaxed ${isResolved ? 'bg-success/5' : 'bg-ui-body/40 dark:bg-ui-dark-bg/40'
                      } px-4 py-3 rounded-lg border border-ui-border/60 dark:border-ui-dark-border/60 whitespace-pre-wrap`}
                  >
                    {c.body}
                  </div>

                  <div className="mt-2 flex items-center gap-3">
                    {isOwner && !isResolved && (
                      <Button
                        variant="outline"
                        size="xs"
                        className="text-success border-success/30 hover:bg-success/5 hover:border-success/60 text-xs font-bold"
                        onClick={() => void handleResolve(c.id)}
                        loading={isResolving}
                      >
                        ✓ Resolver
                      </Button>
                    )}
                    <button
                      type="button"
                      onClick={() => setReplyingTo(replyingTo === c.id ? null : c.id)}
                      className="text-xs font-bold text-odoo-purple hover:underline"
                    >
                      {replyingTo === c.id ? 'Cancelar' : 'Responder'}
                    </button>
                  </div>
                </div>

                {/* Replies */}
                {replies.length > 0 && (
                  <div className="ml-8 space-y-4">
                    {replies.map((r) => (
                      <div
                        key={r.id}
                        className="relative pl-4 border-l-2 border-ui-border/40 dark:border-ui-dark-border/40"
                      >
                        <div className="flex items-center justify-between mb-1 gap-2">
                          <span className="text-xs font-bold text-text-primary dark:text-text-dark-primary">
                            {r.author?.name || 'Autor'}
                          </span>
                          <time className="text-xs text-text-muted font-bold">
                            {new Date(r.created_at).toLocaleDateString()}
                          </time>
                        </div>
                        <div className="text-xs text-text-secondary dark:text-text-dark-secondary bg-ui-body/20 dark:bg-ui-dark-bg/20 p-2 rounded border border-ui-border/30">
                          {r.body}
                        </div>
                      </div>
                    ))}
                  </div>
                )}

                {/* Reply form */}
                {replyingTo === c.id && (
                  <div className="ml-8 mt-2 space-y-2 animate-in slide-in-from-top-1">
                    <textarea
                      value={replyBody}
                      onChange={(e) => setReplyBody(e.target.value)}
                      placeholder="Escribe una respuesta..."
                      className="w-full text-xs p-3 rounded-lg border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg focus:ring-2 focus:ring-odoo-purple/20 outline-none transition-all resize-none h-20 shadow-inner"
                    />
                    <div className="flex justify-end">
                      <Button
                        size="xs"
                        variant="primary"
                        loading={replyLoading}
                        disabled={!replyBody.trim() || replyLoading}
                        onClick={() => void handleSendReply(c.id)}
                        className="text-xs font-bold uppercase tracking-wider"
                      >
                        Enviar respuesta
                      </Button>
                    </div>
                  </div>
                )}
              </div>
            );
          })
        )}
      </div>
    </aside>
  );
}
