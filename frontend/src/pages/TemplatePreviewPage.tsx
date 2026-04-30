import { useEffect, useState } from 'react';
import { useLocation, useNavigate, useParams } from 'react-router-dom';
import { fetchTemplate, submitTemplateForReview, deleteTemplate, cloneTemplate } from '../api/templates';
import { fetchBlocks } from '../api/blocks';
import { apiFetchJson } from '../api/http';
import { normalizeBlockContentForEditor } from '../features/documents/lib/normalizeBlockContent';
import { BlockContentHtml } from '../features/templates/components/BlockContentHtml';
import { visibilityLabel } from '../features/templates/constants';
import type { Template } from '../types/templates';
import type { TemplateBlock } from '../types/blocks';
import { Button, ConfirmDialog } from '../ui';
import { PageTitle } from '@maya/shared-ui-react';
import { FavoriteButton } from '../components/FavoriteButton';
import { VersionHistoryPanel } from '../components/VersionHistoryPanel';
import { useUserProfile } from '../features/user-profile';

type ReviewComment = {
  id: string;
  blockable_id: string | null;
  author_id: string;
  author?: { id: string; name: string };
  body: string;
  resolved: boolean;
  created_at: string;
  parent_id?: string | null;
};

const STATUS_BADGE: Record<string, string> = {
  draft: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
  in_review: 'bg-amber-200 text-amber-900 dark:bg-amber-800/40 dark:text-amber-200',
  published: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
  archived: 'bg-ui-border text-text-secondary dark:bg-ui-dark-border dark:text-text-dark-secondary',
};

const STATUS_LABEL: Record<string, string> = {
  draft: 'Borrador',
  in_review: 'En revisión',
  published: 'Publicada',
  archived: 'Archivada',
};

function blockContentNodes(block: TemplateBlock): unknown[] {
  const fromContent = normalizeBlockContentForEditor(block.default_content);
  if (fromContent.length > 0) return fromContent;
  if (typeof block.default_content === 'string' && block.default_content.trim()) {
    return [];
  }
  return [];
}

function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—';
  return iso.slice(0, 10);
}

export function TemplatePreviewPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const location = useLocation();
  const locationState = location.state as { selectionMode?: boolean; backTo?: string; moduleId?: string } | null;
  const selectionMode = locationState?.selectionMode === true;
  const backTo = locationState?.backTo ?? '/nueva-programacion';
  const { profile } = useUserProfile();

  const [template, setTemplate] = useState<Template | null>(null);
  const [blocks, setBlocks] = useState<TemplateBlock[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [showHistory, setShowHistory] = useState(false);
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  // Review comments (only loaded when owner & has_review_comments)
  const [reviewComments, setReviewComments] = useState<ReviewComment[]>([]);
  const [selectedBlockId, setSelectedBlockId] = useState<string | null>(null);
  const [replyingTo, setReplyingTo] = useState<string | null>(null);
  const [replyBody, setReplyBody] = useState('');
  const [replyLoading, setReplyLoading] = useState(false);

  useEffect(() => {
    if (!id) {
      setLoading(false);
      setError('Identificador de plantilla no válido.');
      return;
    }

    let cancelled = false;
    const load = async () => {
      try {
        setLoading(true);
        setError(null);
        const [tRes, bRes] = await Promise.all([
          fetchTemplate(id),
          fetchBlocks(id),
        ]);
        if (!cancelled) {
          setTemplate(tRes.data);
          setBlocks(bRes.data.slice().sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)));
        }
      } catch (e) {
        if (!cancelled) {
          setError(e instanceof Error ? e.message : 'No se pudo cargar la plantilla.');
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    void load();
    return () => { cancelled = true; };
  }, [id]);

  // Load review comments once both template and profile are available
  useEffect(() => {
    if (!id || !template || !profile?.id) return;
    if (template.created_by !== profile.id || !template.has_review_comments) return;

    let cancelled = false;
    void apiFetchJson<{ data: ReviewComment[] }>(`templates/${id}/comments`)
      .then((res) => { if (!cancelled) setReviewComments(res.data); })
      .catch(() => { /* non-critical */ });
    return () => { cancelled = true; };
  }, [id, template?.id, template?.created_by, template?.has_review_comments, profile?.id]);


  const handleSendReply = async (parentId: string) => {
    if (!replyBody.trim()) return;
    setReplyLoading(true);
    try {
      const res = await apiFetchJson<{ data: ReviewComment }>(`templates/${id}/comments`, {
        method: 'POST',
        body: {
          body: replyBody,
          parent_id: parentId,
          blockable_id: selectedBlockId,
        }
      });
      setReviewComments(prev => [...prev, res.data]);
      setReplyBody('');
      setReplyingTo(null);
    } catch (e) {
      console.error('Error sending reply', e);
    } finally {
      setReplyLoading(false);
    }
  };

  const blockComments = (blockId: string) =>
    reviewComments.filter((c) => c.blockable_id === blockId);

  const isDraft = template?.status === 'draft';
  const isOwner = profile?.id === template?.created_by;
  const isPublished = template?.status === 'published';
  const hasReviewers = (template?.reviewers?.length ?? 0) > 0;

  const canEdit = isOwner && isDraft;
  const canDelete = isOwner && isDraft;
  const canClone = isPublished || isOwner;
  const canSubmit = isOwner && isDraft && hasReviewers && !template.has_review_comments;

  const handleSubmitForReview = async () => {
    if (!id || !template) return;
    setActionLoading(true);
    setActionError(null);
    try {
      const res = await submitTemplateForReview(id);
      setTemplate(res.data);
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'No se pudo enviar a validar.');
    } finally {
      setActionLoading(false);
    }
  };

  const handleClone = async () => {
    if (!id) return;
    setActionLoading(true);
    setActionError(null);
    try {
      const res = await cloneTemplate(id);
      // TODO: permitir al usuario personalizar nombre del clon
      navigate(`/templates/${res.data.id}/edit`);
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'No se pudo clonar la plantilla.');
    } finally {
      setActionLoading(false);
    }
  };

  const handleDelete = async () => {
    if (!id) return;
    setDeleteLoading(true);
    setDeleteError(null);
    try {
      await deleteTemplate(id);
      navigate('/procesos');
    } catch (e) {
      setDeleteError(e instanceof Error ? e.message : 'No se pudo eliminar la plantilla.');
      setDeleteLoading(false);
    }
  };

  const headerActions = template ? (
    <div className="flex items-center gap-2 flex-wrap">
      <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${STATUS_BADGE[template.status] ?? ''}`}>
        {STATUS_LABEL[template.status] ?? template.status}
      </span>
      <span className="text-xs font-mono bg-ui-body dark:bg-ui-dark-bg border border-ui-border dark:border-ui-dark-border px-2 py-0.5 rounded-full text-text-secondary dark:text-text-dark-secondary">
        v{template.version}
      </span>
      {selectionMode ? (
        <>
          <Button type="button" variant="outline" size="sm" onClick={() => setShowHistory(true)}>
            Versiones
          </Button>
          <Button
            type="button"
            variant="primary"
            size="sm"
            onClick={() => navigate(`/nueva-programacion/${id}/wizard`, {
              state: { moduleId: locationState?.moduleId },
            })}
          >
            Usar plantilla
          </Button>
        </>
      ) : (
        <>
          {id && <FavoriteButton entityType="template" entityId={id} />}
          <Button type="button" variant="outline" size="sm" onClick={() => setShowHistory(true)}>
            Historial
          </Button>
          {canDelete && (
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="text-danger border-danger/40 hover:border-danger hover:bg-danger/5"
              onClick={() => setShowDeleteModal(true)}
            >
              Eliminar
            </Button>
          )}
          {canEdit && (
            <Button type="button" variant="outline" size="sm" onClick={() => navigate(`/templates/${id}/edit`)}>
              Editar
            </Button>
          )}
          {canClone && (
            <Button type="button" variant="outline" size="sm" loading={actionLoading} onClick={() => void handleClone()}>
              Clonar
            </Button>
          )}
          {canSubmit && (
            <Button type="button" variant="primary" size="sm" loading={actionLoading} onClick={() => void handleSubmitForReview()}>
              Enviar a validar
            </Button>
          )}
        </>
      )}
    </div>
  ) : null;

  const headerMeta = template ? (
    <p className="text-xs text-text-muted dark:text-text-dark-muted text-center">
      {template.author_name ?? 'Autor desconocido'}
      {' · '}
      {visibilityLabel(template.visibility_level)}
      {' · '}
      Fecha límite de validación: {formatDate(template.delivery_deadline)}
      {' · '}
      Última edición: {formatDate(template.updated_at)}
    </p>
  ) : null;

  return (
    <div className="min-h-full overflow-y-auto">
      <PageTitle
        title={template?.name ?? 'Plantilla'}
        subtitle="Previsualización"
        onBack={() => navigate(selectionMode ? backTo : '/procesos')}
        backLabel={selectionMode ? 'Seleccionar plantilla' : 'Volver'}
        actions={headerActions}
        meta={headerMeta}
      />

      {actionError && (
        <div className="max-w-[960px] mx-auto px-6 py-2">
          <p className="text-sm text-warning-dark dark:text-warning-light">{actionError}</p>
        </div>
      )}

      {/* Two-column layout when comments panel is open */}
      <div className="flex min-h-[calc(100vh-52px)]">
        {/* Article (paper) */}
        <article
          className="bg-ui-card dark:bg-ui-dark-card shadow-xl preview-content flex-1"
          style={{ maxWidth: selectedBlockId ? '760px' : '760px', margin: selectedBlockId ? '0 auto 0 auto' : '0 auto', padding: '56px 72px' }}
        >
          {loading && (
            <p className="text-sm text-text-muted dark:text-text-dark-muted">Cargando plantilla…</p>
          )}
          {error && !loading && (
            <p className="text-sm text-warning-dark dark:text-warning-light">{error}</p>
          )}
          {!loading && !error && template && (
            <>
              <h1 className="text-2xl font-bold text-text-primary dark:text-text-dark-primary pb-4 mb-6 border-b border-ui-border dark:border-ui-dark-border">
                {template.name}
              </h1>
              {blocks.length === 0 ? (
                <p className="text-sm text-text-muted dark:text-text-dark-muted italic">
                  Esta plantilla no tiene bloques.
                </p>
              ) : (
                <div className="space-y-10">
                  {blocks.map((block) => {
                    const isLocked = block.block_state === 'locked';
                    const nodes = blockContentNodes(block);
                    const hasContent = nodes.length > 0;
                    const pendingComments = blockComments(block.id);
                    const isSelected = selectedBlockId === block.id;

                    return (
                      <section
                        key={block.id}
                        style={isLocked ? { opacity: 0.45 } : undefined}
                        className={[
                          'relative rounded-lg transition-all duration-150',
                          pendingComments.length > 0
                            ? 'cursor-pointer'
                            : '',
                          isSelected
                            ? 'ring-2 ring-danger/40 ring-offset-4'
                            : pendingComments.length > 0
                              ? 'hover:ring-1 hover:ring-danger/30 hover:ring-offset-2'
                              : '',
                        ].join(' ')}
                        onClick={pendingComments.length > 0 ? () => setSelectedBlockId(isSelected ? null : block.id) : undefined}
                      >
                        <div className="flex flex-wrap items-baseline gap-2 mb-2">
                          {block.title && (
                            <h4 className="text-sm font-bold text-text-secondary dark:text-text-dark-secondary">
                              {block.title}
                            </h4>
                          )}
                          {pendingComments.length > 0 && (
                            <span
                              className="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full bg-danger/10 text-danger-dark dark:text-danger border border-danger/20"
                              title="Este bloque tiene comentarios de revisión pendientes"
                            >
                              ⚠ {pendingComments.length} {pendingComments.length === 1 ? 'comentario' : 'comentarios'}
                            </span>
                          )}
                          {block.mandatory && (
                            <span className="text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                              Obligatorio
                            </span>
                          )}
                          {isLocked && (
                            <span className="text-[10px] font-medium uppercase tracking-wide px-1.5 py-0.5 rounded bg-ui-border/60 dark:bg-ui-dark-border text-text-muted dark:text-text-dark-muted">
                              Bloqueado
                            </span>
                          )}
                        </div>
                        {hasContent ? (
                          <BlockContentHtml content={nodes} />
                        ) : (
                          <p className="text-sm text-text-muted dark:text-text-dark-muted italic">
                            Sin contenido.
                          </p>
                        )}
                      </section>
                    );
                  })}
                </div>
              )}
            </>
          )}
        </article>

        {/* Comments side panel — same style as Descripción tab in block editor */}
        {selectedBlockId && (() => {
          const block = blocks.find((b) => b.id === selectedBlockId);
          const pending = blockComments(selectedBlockId);
          return (
            <aside className="w-96 shrink-0 border-l border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card flex flex-col sticky top-13 h-[calc(100vh-52px)] overflow-hidden shadow-lg animate-in slide-in-from-right-2">
              {/* Header */}
              <div className="shrink-0 px-5 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center gap-2 bg-danger/5">
                <span className="flex-1 text-[10px] font-black uppercase tracking-widest text-danger-dark dark:text-danger truncate">
                  ⚠ {block?.title ?? 'Bloque'}
                </span>
                <span className="text-[10px] text-text-muted font-bold shrink-0">
                  {pending.length} {pending.length === 1 ? 'comentario' : 'comentarios'}
                </span>
                <button
                  type="button"
                  onClick={() => setSelectedBlockId(null)}
                  className="shrink-0 w-6 h-6 flex items-center justify-center rounded-full hover:bg-ui-body dark:hover:bg-ui-dark-bg text-text-muted hover:text-text-primary transition-colors text-sm"
                  aria-label="Cerrar panel"
                >
                  ✕
                </button>
              </div>

              {/* Comment list */}
              <div className="flex-1 overflow-y-auto px-5 py-5 space-y-8">
                {pending.length === 0 ? (
                  <div className="flex flex-col items-center justify-center h-32 text-center opacity-40">
                    <p className="text-sm font-medium text-text-muted">No hay comentarios pendientes.</p>
                  </div>
                ) : (
                  pending.filter(c => !c.parent_id).map((c) => {
                    const replies = reviewComments.filter(r => r.parent_id === c.id);
                    const isResolved = c.resolved;

                    return (
                      <div key={c.id} className="space-y-4">
                        <div className={`group relative pl-5 ${isResolved ? 'opacity-50' : ''}`}>
                          <div className={`absolute left-0 top-0 bottom-0 w-1 ${isResolved ? 'bg-success/30' : 'bg-danger/30 group-hover:bg-danger/60'} transition-colors rounded-full`} />
                          <div className="flex items-center justify-between mb-1.5 gap-2">
                            <span className="text-xs font-black text-text-primary dark:text-text-dark-primary">
                              {c.author?.name || 'Validador'}
                              {isResolved && <span className="ml-2 text-[10px] text-success font-bold uppercase tracking-wider">✓ Resuelto</span>}
                            </span>
                            {c.created_at && (
                              <time className="text-[10px] text-text-muted font-bold uppercase tracking-wider shrink-0" dateTime={c.created_at}>
                                {new Date(c.created_at).toLocaleDateString()}
                              </time>
                            )}
                          </div>
                          <div className={`text-sm text-text-secondary dark:text-text-dark-secondary leading-relaxed ${isResolved ? 'bg-success/5' : 'bg-ui-body/40 dark:bg-ui-dark-bg/40'} px-4 py-3 rounded-lg border border-ui-border/60 dark:border-ui-dark-border/60 whitespace-pre-wrap`}>
                            {c.body}
                          </div>
                          
                          <div className="mt-2 flex items-center gap-3">
                            <button
                              type="button"
                              onClick={() => setReplyingTo(replyingTo === c.id ? null : c.id)}
                              className="text-[10px] font-bold text-odoo-purple hover:underline"
                            >
                              {replyingTo === c.id ? 'Cancelar respuesta' : 'Responder'}
                            </button>
                          </div>
                        </div>

                        {/* Replies */}
                        {replies.length > 0 && (
                          <div className="ml-8 space-y-4">
                            {replies.map(r => (
                              <div key={r.id} className="relative pl-4 border-l-2 border-ui-border/40 dark:border-ui-dark-border/40">
                                <div className="flex items-center justify-between mb-1 gap-2">
                                  <span className="text-[11px] font-bold text-text-primary dark:text-text-dark-primary">
                                    {r.author?.name || 'Autor'}
                                  </span>
                                  <time className="text-[9px] text-text-muted font-bold" dateTime={r.created_at}>
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
                          <div className="ml-8 mt-2 space-y-2">
                            <textarea
                              value={replyBody}
                              onChange={(e) => setReplyBody(e.target.value)}
                              placeholder="Escribe una respuesta..."
                              className="w-full text-xs p-3 rounded-lg border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg focus:ring-2 focus:ring-odoo-purple/20 outline-none transition-all resize-none h-20"
                            />
                            <div className="flex justify-end">
                              <Button
                                size="xs"
                                variant="primary"
                                loading={replyLoading}
                                disabled={!replyBody.trim() || replyLoading}
                                onClick={() => void handleSendReply(c.id)}
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
        })()}
      </div>

      {id && (
        <VersionHistoryPanel
          open={showHistory}
          entityType="template"
          entityId={id}
          onClose={() => setShowHistory(false)}
        />
      )}

      <ConfirmDialog
        open={showDeleteModal}
        variant="danger"
        title="¿Eliminar esta plantilla?"
        description="Estás a punto de eliminar este elemento. Esta acción es irreversible y no se puede deshacer."
        confirmLabel="Eliminar"
        cancelLabel="Cancelar"
        loading={deleteLoading}
        error={deleteError}
        onConfirm={() => void handleDelete()}
        onCancel={() => { setShowDeleteModal(false); setDeleteError(null); }}
      />
    </div>
  );
}
