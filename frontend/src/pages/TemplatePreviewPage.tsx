import { useEffect, useState } from 'react';
import { useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import {
  fetchTemplate,
  fetchTemplateVersionSummaries,
  type TemplateVersionDetail,
  submitTemplateForReview,
  deleteTemplate,
  cloneTemplate,
  startTemplateNewVersion,
  discardTemplateWorkingVersion,
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
import { useHierarchy } from '../features/hierarchy';

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

function isTemplateVisibilityLevel(
  value: string | null | undefined,
): value is Template['visibility_level'] {
  return value === 'global'
    || value === 'study_type'
    || value === 'study'
    || value === 'module'
    || value === 'team'
    || value === 'personal';
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
    templateVersionId?: string | null;
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
  const [showDiscardVersionModal, setShowDiscardVersionModal] = useState(false);
  const [discardVersionLoading, setDiscardVersionLoading] = useState(false);
  const [discardVersionError, setDiscardVersionError] = useState<string | null>(null);
  const [processLabel, setProcessLabel] = useState<string | null>(null);
  const { hierarchy } = useHierarchy();
  const [historicalVersionDetail, setHistoricalVersionDetail] = useState<TemplateVersionDetail | null>(null);

  // Review comments (only loaded when owner & has_review_comments)
  const [reviewComments, setReviewComments] = useState<ReviewComment[]>([]);
  const [selectedBlockId, setSelectedBlockId] = useState<string | null>(null);
  const [replyingTo, setReplyingTo] = useState<string | null>(null);
  const [replyBody, setReplyBody] = useState('');
  const [replyLoading, setReplyLoading] = useState(false);
  const [publishedVersionCount, setPublishedVersionCount] = useState<number | null>(null);

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
        setHistoricalVersionDetail(null);
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
          setHistoricalVersionDetail(vRes);
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
            if (t.created_by === profile?.id && t.has_review_comments) {
              void apiFetchJson<{ data: ReviewComment[] }>(`templates/${id}/comments`)
                .then((res) => { if (!cancelled) setReviewComments(res.data); })
                .catch(() => { /* non-critical */ });
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
  }, [id, profile?.id, templateVersionId]);


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
  const viewingPublishedSnapshot = snapshotVersionNumber !== null;
  const snapshotTemplate = historicalVersionDetail?.template_snapshot ?? null;
  const snapshotAuthorName = historicalVersionDetail?.author_name?.trim() ?? null;
  const authorDisplay = viewingPublishedSnapshot
    ? (snapshotAuthorName && snapshotAuthorName !== ''
      ? snapshotAuthorName
      : 'Autor desconocido')
    : (
      template?.author_name?.trim() ||
      (isOwner ? profile?.name?.trim() : '') ||
      'Autor desconocido'
    );
  const displayVisibilityRaw = viewingPublishedSnapshot
    ? (typeof snapshotTemplate?.visibility_level === 'string'
      ? snapshotTemplate.visibility_level
      : template?.visibility_level)
    : template?.visibility_level;
  const displayVisibility = isTemplateVisibilityLevel(displayVisibilityRaw) ? displayVisibilityRaw : null;
  const displayDeadline = viewingPublishedSnapshot
    ? (typeof snapshotTemplate?.delivery_deadline === 'string' || snapshotTemplate?.delivery_deadline === null
      ? snapshotTemplate.delivery_deadline
      : template?.delivery_deadline)
    : template?.delivery_deadline;
  const displayUpdatedAt = viewingPublishedSnapshot
    ? (typeof snapshotTemplate?.updated_at === 'string' || snapshotTemplate?.updated_at === null
      ? snapshotTemplate.updated_at
      : historicalVersionDetail?.published_at ?? template?.updated_at)
    : template?.updated_at;
  const displayTitle = viewingPublishedSnapshot
    ? (typeof snapshotTemplate?.name === 'string' && snapshotTemplate.name.trim() !== ''
      ? snapshotTemplate.name
      : template?.name)
    : template?.name;
  const showVersionHistory = publishedVersionCount !== null && publishedVersionCount > 0;

  const canEdit = isOwner && isDraft && !viewingPublishedSnapshot;
  /** Igual que `TemplatePolicy::delete` (backend): creador o `templates.delete`, cualquier estado. */
  const canDelete =
    !viewingPublishedSnapshot &&
    template != null &&
    !template.latest_published_version_id &&
    ((isDraft && isOwner) || (isPublished && (isOwner || hasPermission('templates.delete'))));
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
  const canDiscardWorkingVersion =
    !viewingPublishedSnapshot &&
    template != null &&
    (template.status === 'draft' || template.status === 'in_review') &&
    !!template.latest_published_version_id &&
    !!template.working_version_id &&
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

  const handleDiscardWorkingVersion = async () => {
    if (!id || !template?.working_version_id) return;
    setDiscardVersionLoading(true);
    setDiscardVersionError(null);
    try {
      const restored = await discardTemplateWorkingVersion(id, template.working_version_id);
      setTemplate(restored.data);
      setShowDiscardVersionModal(false);
      setActionError(null);
    } catch (e) {
      setDiscardVersionError(e instanceof Error ? e.message : 'No se pudo descartar la versión en curso.');
    } finally {
      setDiscardVersionLoading(false);
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
              state: {
                moduleId: locationState?.moduleId,
                processId: locationState?.processId,
                templateVersionId: templateVersionId ?? locationState?.templateVersionId ?? null,
              },
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
          {canDiscardWorkingVersion && (
            <Button
              type="button"
              variant="outlineWarning"
              size="sm"
              loading={discardVersionLoading}
              onClick={() => setShowDiscardVersionModal(true)}
            >
              Descartar nueva versión
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
      {displayVisibility ? visibilityLabel(displayVisibility) : '—'}
      {displayVisibility === 'study_type' && template.study_type_id ? (
        <> ({(hierarchy.find((t: any) => String(t.id) === String(template.study_type_id))?.name ?? template.study_type_id)})</>
      ) : null}
      {displayVisibility === 'study' && template.study_id ? (
        <> ({(hierarchy.flatMap((t: any) => t.studies ?? []).find((s: any) => String(s.id) === String(template.study_id))?.name ?? template.study_id)})</>
      ) : null}
      {displayVisibility === 'module' && template.module_id ? (
        <> ({(hierarchy.flatMap((t: any) => t.studies ?? []).flatMap((s: any) => s.course_modules ?? []).find((m: any) => String(m.id) === String(template.module_id))?.name ?? template.module_id)})</>
      ) : null}
      {displayVisibility === 'team' && (template.team?.name || template.team_id) ? (
        <> ({template.team?.name ?? template.team_id})</>
      ) : null}
      {' · '}
      Fecha límite de validación: {formatDate(displayDeadline)}
      {' · '}
      Última edición: {formatDate(displayUpdatedAt)}
    </p>
  ) : null;

  return (
    <div className="min-h-full overflow-y-auto">
      <PageTitle
        title={displayTitle ?? 'Plantilla'}
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
                {displayTitle ?? template.name}
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

        {/* Comments side panel — fixed position so the article remains centered */}
        {selectedBlockId && (() => {
          const block = blocks.find((b) => b.id === selectedBlockId);
          const pending = blockComments(selectedBlockId);
          return (
            <aside className="fixed right-0 top-13 w-96 h-[calc(100vh-52px)] z-30 border-l border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card flex flex-col overflow-hidden shadow-lg animate-in slide-in-from-right-2">
              {/* Header */}
              <div className="shrink-0 px-5 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center gap-2 bg-danger/5">
                <span className="flex-1 text-xs font-black uppercase tracking-widest text-danger-dark dark:text-danger truncate">
                  ⚠ {block?.title ?? 'Bloque'}
                </span>
                <span className="text-xs text-text-muted font-bold shrink-0">
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
                              {isResolved && <span className="ml-2 text-xs text-success-dark font-bold uppercase tracking-wider">✓ Resuelto</span>}
                            </span>
                            {c.created_at && (
                              <time className="text-xs text-text-muted font-bold uppercase tracking-wider shrink-0" dateTime={c.created_at}>
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
                              className="text-xs font-bold text-odoo-purple hover:underline"
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
                                  <span className="text-xs font-bold text-text-primary dark:text-text-dark-primary">
                                    {r.author?.name || 'Autor'}
                                  </span>
                                  <time className="text-xs text-text-muted font-bold" dateTime={r.created_at}>
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
      <ConfirmDialog
        open={showDiscardVersionModal}
        variant="danger"
        title="¿Descartar nueva versión?"
        description="Se descartarán los cambios en borrador/en revisión y se restaurará la última versión publicada de la plantilla."
        confirmLabel="Descartar versión"
        cancelLabel="Cancelar"
        loading={discardVersionLoading}
        error={discardVersionError}
        onConfirm={() => void handleDiscardWorkingVersion()}
        onCancel={() => {
          setShowDiscardVersionModal(false);
          setDiscardVersionError(null);
        }}
      />
    </div>
  );
}
