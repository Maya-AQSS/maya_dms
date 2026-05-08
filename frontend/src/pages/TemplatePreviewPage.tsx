import { useEffect, useRef, useState } from 'react';
import { useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import {
  fetchTemplate,
  fetchTemplateVersionSummaries,
  submitTemplateForReview,
  deleteTemplate,
  cloneTemplate,
  startTemplateNewVersion,
  fetchTemplateVersion,
} from '../api/templates';
import { fetchBlocks } from '../api/blocks';
import { fetchProcesses } from '../api/processes';
import { apiFetchJson } from '../api/http';
import { normalizeBlockContentForEditor } from '../features/documents/lib/normalizeBlockContent';
import { BlockContentHtml } from '../features/templates/components/BlockContentHtml';
import { visibilityLabel } from '../features/templates/constants';
import type { Template } from '../types/templates';
import type { BlockState, TemplateBlock } from '../types/blocks';
import { Button, ConfirmDialog, PageTitle, statusBadgeClass } from '@maya/shared-ui-react';
import { FavoriteButton } from '../components/FavoriteButton';
import { VersionHistoryPanel } from '../components/VersionHistoryPanel';
import { useUserProfile } from '../features/user-profile';
import { BlockCommentsCard } from '../features/templates/components/BlockCommentsCard';
import type { BlockComment } from '../features/templates/components/BlockCommentsCard';

// Re-use the shared BlockComment type (has resolved, parent_id, etc.)
type ReviewComment = BlockComment;

// Estado: clases en `statusBadgeClass` (módulo `@maya/shared-ui-react/badges`).

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

function mapSnapshotToTemplateBlocks(templateId: string, snapshot: import('../api/templates').TemplateVersionSnapshotBlock[]): TemplateBlock[] {
  return snapshot.map((b, idx) => ({
    id: b.id,
    template_id: templateId,
    type: b.type,
    title: b.title ?? null,
    default_content: b.default_content ?? null,
    description: null,
    block_state: (b.block_state as BlockState) ?? 'locked',
    mandatory: Boolean(b.mandatory),
    sort_order: typeof b.sort_order === 'number' ? b.sort_order : idx,
  }));
}

export function TemplatePreviewPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const templateVersionId = searchParams.get('templateVersionId');
  const location = useLocation();
  const locationState = location.state as {
    selectionMode?: boolean;
    backTo?: string;
    moduleId?: string;
    processId?: string;
  } | null;
  const selectionMode = locationState?.selectionMode === true;
  const backTo = locationState?.backTo ?? '/documentos/nuevo';
  const defaultBackTo = locationState?.backTo ?? '/dashboard';
  const handleBack = () => {
    if (selectionMode) {
      navigate(backTo, {
        state: {
          moduleId: locationState?.moduleId,
          processId: locationState?.processId,
        },
      });
      return;
    }
    if (window.history.length > 1) {
      navigate(-1);
      return;
    }
    navigate(selectionMode ? backTo : defaultBackTo);
  };

  const { profile, hasPermission } = useUserProfile();

  const [template, setTemplate] = useState<Template | null>(null);
  const [blocks, setBlocks] = useState<TemplateBlock[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [showHistory, setShowHistory] = useState(false);
  const [snapshotVersionNumber, setSnapshotVersionNumber] = useState<number | null>(null);
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [processLabel, setProcessLabel] = useState<string | null>(null);

  // Review comments (only loaded when owner & has_review_comments)
  const [reviewComments, setReviewComments] = useState<ReviewComment[]>([]);
  const [selectedBlockId, setSelectedBlockId] = useState<string | null>(null);
  const [publishedVersionCount, setPublishedVersionCount] = useState<number | null>(null);

  // Ref for the comment card header — used to measure height for the fixed panel positioning.
  const commentCardHeaderRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!id) {
      setPublishedVersionCount(null);
      return;
    }
    let cancelled = false;
    void fetchTemplateVersionSummaries(id)
      .then((rows) => {
        if (!cancelled) setPublishedVersionCount(rows.length);
      })
      .catch(() => {
        if (!cancelled) setPublishedVersionCount(null);
      });
    return () => {
      cancelled = true;
    };
  }, [id]);

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
        setSnapshotVersionNumber(null);
        setReviewComments([]);

        if (templateVersionId) {
          const [tRes, vRes] = await Promise.all([fetchTemplate(id), fetchTemplateVersion(templateVersionId)]);
          if (cancelled) return;
          if (vRes.template_id !== id) {
            setError('La versión seleccionada no pertenece a esta plantilla.');
            setTemplate(null);
            setBlocks([]);
            return;
          }
          const t = tRes.data;
          setTemplate(t);
          setSnapshotVersionNumber(vRes.version_number);
          const snap = Array.isArray(vRes.blocks_snapshot) ? vRes.blocks_snapshot : [];
          setBlocks(mapSnapshotToTemplateBlocks(id, snap).sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)));
        } else {
          const [tRes, bRes] = await Promise.all([
            fetchTemplate(id),
            fetchBlocks(id),
          ]);
          if (!cancelled) {
            const t = tRes.data;
            setTemplate(t);
            setBlocks(bRes.data.slice().sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)));
            if (t.has_review_comments) {
              void apiFetchJson<{ data: ReviewComment[] }>(`templates/${id}/comments`)
                .then((res) => { if (!cancelled) setReviewComments(res.data); })
                .catch((err) => { console.error('Error loading review comments', err); });
            }
          }
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
  // profile?.id was previously used in the loading condition but is no longer needed here;
  // the backend handles authorization. Keeping it out of deps avoids a double-load flash.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id, templateVersionId]);


  // Unresolved root-level comments for a block (drives badge + card visibility).
  const blockPendingComments = (blockId: string) =>
    reviewComments.filter((c) => c.blockable_id === blockId && !c.parent_id && !c.resolved);

  // All comments for a block (top-level + replies, for the card to use).
  const blockAllComments = (blockId: string) =>
    reviewComments.filter((c) => c.blockable_id === blockId);

  const isDraft = template?.status === 'draft';
  const isOwner = profile?.id === template?.created_by;
  const isPublished = template?.status === 'published';
  const hasReviewers = (template?.reviewers?.length ?? 0) > 0;
  const authorDisplay =
    template?.author_name?.trim() ||
    (isOwner ? profile?.name?.trim() : '') ||
    'Autor desconocido';

  const viewingPublishedSnapshot = snapshotVersionNumber !== null;
  const showVersionHistory = publishedVersionCount !== null && publishedVersionCount > 1;

  const canEdit = isOwner && isDraft && !viewingPublishedSnapshot;
  /** Igual que `TemplatePolicy::delete` (backend): creador o `templates.delete`, cualquier estado. */
  const canDelete =
    !viewingPublishedSnapshot &&
    template != null &&
    (
      (isDraft && isOwner) ||
      (isPublished && (isOwner || hasPermission('templates.delete')))
    );
  /** Coincide con `TemplatePolicy::clone` y `data.can_clone` de la API. */
  const canClone = !viewingPublishedSnapshot && template?.can_clone === true;
  const canSubmit =
    !viewingPublishedSnapshot && isOwner && isDraft && hasReviewers && !template.has_review_comments;
  /** Alineado con `TemplatePolicy::startRevision`: creador o `templates.update` en publicada. */
  const canStartNewVersion =
    !viewingPublishedSnapshot &&
    isPublished &&
    !selectionMode &&
    (isOwner || hasPermission('templates.update'));

  useEffect(() => {
    if (!template?.process_id) {
      setProcessLabel(null);
      return;
    }
    let cancelled = false;
    void fetchProcesses()
      .then((res) => {
        if (cancelled) return;
        const process = res.data.find((p) => p.id === template.process_id) ?? null;
        if (!process) {
          setProcessLabel(null);
          return;
        }
        setProcessLabel(`Proceso: ${process.code} — ${process.name}`);
      })
      .catch(() => {
        if (!cancelled) setProcessLabel(null);
      });
    return () => {
      cancelled = true;
    };
  }, [template?.process_id]);

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

  const handleStartNewVersion = async () => {
    if (!id) return;
    setActionLoading(true);
    setActionError(null);
    try {
      const res = await startTemplateNewVersion(id);
      setTemplate(res.data);
      navigate(`/templates/${id}/edit`);
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'No se pudo abrir una nueva versión.');
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
      navigate(defaultBackTo);
    } catch (e) {
      setDeleteError(e instanceof Error ? e.message : 'No se pudo eliminar la plantilla.');
      setDeleteLoading(false);
    }
  };

  const headerToolbar = template ? (
    <div className="flex items-center justify-center gap-2 flex-wrap">
      {viewingPublishedSnapshot ? (
        <>
          <span className="text-xs font-medium px-2 py-0.5 rounded-full bg-primary/15 text-primary-dark dark:text-primary-light border border-primary/25">
            Versión publicada v{snapshotVersionNumber}
          </span>
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => navigate(`/templates/${id}`, { replace: true })}
          >
            Estado actual
          </Button>
        </>
      ) : (
        <>
          <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${statusBadgeClass(template.status)}`}>
            {STATUS_LABEL[template.status] ?? template.status}
          </span>
          <span className="text-xs font-mono bg-ui-body dark:bg-ui-dark-bg border border-ui-border dark:border-ui-dark-border px-2 py-0.5 rounded-full text-text-secondary dark:text-text-dark-secondary">
            v{template.version}
          </span>
        </>
      )}
      {selectionMode ? (
        <>
          {showVersionHistory ? (
            <Button type="button" variant="outline" size="sm" onClick={() => setShowHistory(true)}>
              Versiones
            </Button>
          ) : null}
          <Button
            type="button"
            variant="primary"
            size="sm"
            onClick={() => navigate(`/documentos/nuevo/${id}/wizard`, {
              state: { moduleId: locationState?.moduleId, processId: locationState?.processId },
            })}
          >
            Usar plantilla
          </Button>
        </>
      ) : (
        <>
          {!viewingPublishedSnapshot && id ? <FavoriteButton entityType="template" entityId={id} /> : null}
          {showVersionHistory ? (
            <Button type="button" variant="outline" size="sm" onClick={() => setShowHistory(true)}>
              Historial
            </Button>
          ) : null}
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
          {canStartNewVersion && (
            <Button type="button" variant="outline" size="sm" loading={actionLoading} onClick={() => void handleStartNewVersion()}>
              Nueva versión
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
      {processLabel ? (
        <>
          {processLabel}
          {' · '}
        </>
      ) : null}
      {authorDisplay}
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
        onBack={handleBack}
        backLabel={selectionMode ? 'Seleccionar plantilla' : 'Volver'}
        meta={
          <div className="space-y-3">
            {headerMeta}
            {headerToolbar}
          </div>
        }
      />

      {actionError && (
        <div className="max-w-[960px] mx-auto px-6 py-2">
          <p className="text-sm text-warning-dark dark:text-warning-light">{actionError}</p>
        </div>
      )}

      <div className={selectedBlockId ? 'pr-96' : ''}>
        {/* Article (paper) — same layout as DocumentPreviewPage */}
        <article
          className="mx-auto bg-ui-card dark:bg-ui-dark-card shadow-xl preview-content"
          style={{ maxWidth: '760px', minHeight: 'calc(100vh - 52px)', padding: '56px 72px' }}
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
                    const pendingComments = blockPendingComments(block.id);
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
                              className="inline-flex items-center gap-1 text-xs font-black uppercase tracking-widest px-2 py-0.5 rounded-full bg-danger/10 text-danger-dark dark:text-danger border border-danger/20"
                              title="Este bloque tiene comentarios de revisión pendientes"
                            >
                              ⚠ {pendingComments.length} {pendingComments.length === 1 ? 'comentario' : 'comentarios'}
                            </span>
                          )}
                          {block.mandatory && (
                            <span className="text-xs font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded bg-success-light text-success-dark dark:bg-success-dark/30 dark:text-success-light">
                              Obligatorio
                            </span>
                          )}
                          {isLocked && (
                            <span className="text-xs font-medium uppercase tracking-wide px-1.5 py-0.5 rounded bg-ui-border/60 dark:bg-ui-dark-border text-text-muted dark:text-text-dark-muted">
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

        {/* Comments panel — fixed-position card, creator-readonly mode */}
        {selectedBlockId && (() => {
          const block = blocks.find((b) => b.id === selectedBlockId);
          return (
            <div className="fixed right-6 top-[70px] w-[384px] z-30">
              <BlockCommentsCard
                mode="creator-readonly"
                blockSortOrder={block?.sort_order ?? '?'}
                blockComments={blockAllComments(selectedBlockId)}
                allComments={reviewComments}
                headerRef={commentCardHeaderRef}
                onClose={() => setSelectedBlockId(null)}
              />
            </div>
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
