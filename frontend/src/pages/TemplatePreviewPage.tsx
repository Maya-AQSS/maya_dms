import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import {
  fetchTemplate,
  type TemplateVersionDetail,
  submitTemplateForReview,
  deleteTemplate,
  cloneTemplate,
  startTemplateNewVersion,
  discardTemplateWorkingVersion,
  fetchTemplateVersion,
} from '../api/templates';
import { fetchBlocks } from '../api/blocks';
import { apiFetchJson, ApiHttpError } from '../api/http';
import { useTemplateVersionSummariesQuery } from '../features/templates/hooks/useTemplateVersionSummaries';
import { useProcessesQuery } from '../hooks/useProcesses';
import { normalizeBlockContentForEditor } from '../features/documents/lib/normalizeBlockContent';
import { BlockContentHtml } from '../features/templates/components/BlockContentHtml';
import { StructuralBlockPreview, isStructuralBlockType } from '../features/documents/components/StructuralBlockPreview';
import { visibilityLabel } from '../features/templates/constants';
import type { Template } from '../types/templates';
import type { BlockState, TemplateBlock } from '../types/blocks';
import { Button, ConfirmDialog, statusBadgeClass } from '@ceedcv-maya/shared-ui-react';
import { VersionChangelogModal } from '../components/VersionChangelogModal';
import { FavoriteButton } from '../components/FavoriteButton';
import { VersionHistoryPanel } from '../components/VersionHistoryPanel';
import { useUserProfile } from '../features/user-profile';
import { canListTemplateBlocks, canDeleteBlockComment, DMS_PERMISSIONS } from '../permissions';
import { isDiscardWorkingVersionAllowed } from '../utils/versionableEntityActions';
import { useHierarchy } from '../features/hierarchy';
import { BlockCommentsCard, ViewCardHeader } from '../features/templates/components/BlockCommentsCard';
import type { BlockComment } from '../features/templates/components/BlockCommentsCard';
import { PaperPreviewLayout } from '../features/documents/components/PaperPreviewLayout';
import { PagedThemedPreview } from '../features/documents/components/PagedThemedPreview';
import { SequentialValidatorBadge } from '../features/documents/components/SequentialValidatorBadge';
import { formatCalendarDateForBrowser } from '../utils/formatCalendarDate';
import { getCommentsForBlock, countUnreadCommentsForBlock, resolveCommentBlockableId } from '../utils/blockComments';
import { markCommentAsRead, fetchResourceComments } from '../api/comments';
import { applyCommentDeleted } from '../features/comments/commentCache';

// Re-use the shared BlockComment type (has resolved, parent_id, etc.)
type ReviewComment = BlockComment;

// Estado: clases en `statusBadgeClass` (módulo `@ceedcv-maya/shared-ui-react/badges`).

const STATUS_LABEL: Record<string, string> = {
  draft: 'Borrador',
  in_review: 'En revisión',
  published: 'Publicada',
  archived: 'Archivada',
  rejected: 'Rechazada',
};

function blockContentNodes(block: TemplateBlock): unknown[] {
  const fromContent = normalizeBlockContentForEditor(block.default_content);
  if (fromContent.length > 0) return fromContent;
  if (typeof block.default_content === 'string' && block.default_content.trim()) {
    return [];
  }
  return [];
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
    block_type: ((b as { block_type?: string }).block_type as TemplateBlock['block_type']) ?? 'content',
    title: b.title ?? null,
    default_content: b.default_content ?? null,
    description: null,
    block_state: (b.block_state as BlockState) ?? 'locked',
    page_break_after: Boolean((b as { page_break_after?: boolean }).page_break_after),
    theme_id: (b as { theme_id?: string | null }).theme_id ?? null,
    apply_theme: (b as { apply_theme?: boolean }).apply_theme ?? true,
    mandatory: Boolean(b.mandatory),
    sort_order: typeof b.sort_order === 'number' ? b.sort_order : idx,
  }));
}

export function TemplatePreviewPage() {
  const { t } = useTranslation('templates');
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
  //const backTo = locationState?.backTo ?? '/documentos/nuevo';
  const defaultBackTo = locationState?.backTo ?? '/dashboard';
  const handleBack = () => {
    if (window.history.length <= 1) {
      navigate("/dashboard");
    } else {
      navigate(-1);
    }
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
  /**
   * `edit` = vista de bloques actual (con comentarios/info por bloque).
   * `themed` = iframe themed + paginado A4 (paged.js) — sólo lectura.
   */
  const [viewMode, setViewMode] = useState<'edit' | 'themed'>('edit');
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [showDiscardVersionModal, setShowDiscardVersionModal] = useState(false);
  const [discardVersionLoading, setDiscardVersionLoading] = useState(false);
  const [discardVersionError, setDiscardVersionError] = useState<string | null>(null);
  const [draftBlockedBy, setDraftBlockedBy] = useState<string | null>(null);
  const [showNewVersionConfirm, setShowNewVersionConfirm] = useState(false);
  const [newVersionLoading, setNewVersionLoading] = useState(false);
  const [newVersionError, setNewVersionError] = useState<string | null>(null);
  const [showChangelogModal, setShowChangelogModal] = useState(false);
  const [changelogModalError, setChangelogModalError] = useState<string | null>(null);
  // processLabel derived from useProcessesQuery + template.process_id below.
  const { hierarchy } = useHierarchy();
  const [historicalVersionDetail, setHistoricalVersionDetail] = useState<TemplateVersionDetail | null>(null);

  // Review comments (only loaded when owner & has_review_comments)
  const [reviewComments, setReviewComments] = useState<ReviewComment[]>([]);
  const [reviewCommentsLoading, setReviewCommentsLoading] = useState(false);
  const [reviewCommentSubmitError, setReviewCommentSubmitError] = useState<string | null>(null);
  const [activeView, setActiveView] = useState<{ blockId: string; mode: 'comments' | 'info' } | null>(null);
  // publishedVersionCount derived from useTemplateVersionSummariesQuery below.

  // Ref for the comment card header.
  const commentCardHeaderRef = useRef<HTMLDivElement>(null);

  // Dynamic top position for the fixed comment panel — no longer needed with PaperPreviewLayout

  const versionSummariesQuery = useTemplateVersionSummariesQuery(id ?? '', {
    enabled: !!id,
  });
  const publishedVersionCount = versionSummariesQuery.data?.length ?? null;

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
          const tRes = await fetchTemplate(id);
          if (cancelled) return;
          const t = tRes.data;
          setTemplate(t);
          const canListBlocks = canListTemplateBlocks(hasPermission, profile?.id, t);
          if (canListBlocks) {
            const bRes = await fetchBlocks(id);
            if (!cancelled) {
              setBlocks(bRes.data.slice().sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)));
            }
          } else if (!cancelled) {
            setBlocks([]);
          }
          if (!cancelled) {
            if (t.has_review_comments) {
              void fetchResourceComments(`templates/${id}/comments`)
                .then((res) => { if (!cancelled) setReviewComments(res.data); })
                .catch(() => { /* TODO: send to error tracker */ });
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
  }, [id, templateVersionId, hasPermission, profile?.id]);

  const handleSendMessage = async (parentId: string | null, body: string) => {
    if (!activeView?.blockId || !id) return;
    setReviewCommentSubmitError(null);
    setReviewCommentsLoading(true);
    try {
      const blockableId = resolveCommentBlockableId(
        parentId,
        reviewComments,
        activeView.blockId,
      );
      const res = await apiFetchJson<{ data: ReviewComment }>(`templates/${id}/comments`, {
        method: 'POST',
        body: { body, parent_id: parentId, blockable_id: blockableId },
      });
      setReviewComments(prev => [...prev, res.data]);
    } catch {
      setReviewCommentSubmitError('No se pudo guardar el comentario.');
      throw new Error('comment-send-failed');
    } finally {
      setReviewCommentsLoading(false);
    }
  };

  const handleEditComment = async (commentId: string, newBody: string) => {
    const res = await apiFetchJson<{ data: ReviewComment }>(`comments/${commentId}`, {
      method: 'PATCH',
      body: { body: newBody },
    });
    setReviewComments(prev => prev.map(c => c.id === commentId ? res.data : c));
  };

  const handleDeleteComment = async (commentId: string) => {
    await apiFetchJson(`comments/${commentId}`, { method: 'DELETE' });
    setReviewComments(prev =>
      prev.map(c => (c.id === commentId ? applyCommentDeleted(c, profile?.name) : c)),
    );
  };

  const handleMarkCommentAsRead = async (commentId: string) => {
    const updated = await markCommentAsRead(commentId);
    setReviewComments(prev => prev.map(c => (c.id === commentId ? updated : c)));
  };



  const isDraft = template?.status === 'draft' || template?.status === 'rejected';
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
  // Con una sola versión publicada no hay historial que mostrar (ni comparación
  // posible): ocultamos el botón hasta que existan al menos dos.
  const showVersionHistory =
    publishedVersionCount !== null
    && publishedVersionCount > 1
    && (isOwner || hasPermission(DMS_PERMISSIONS.templateHistoryView));

  const canEdit = isOwner && isDraft && !viewingPublishedSnapshot;
  /** Eliminar solo si nunca hubo versión publicada (alta o clon sin publicar). */
  const canDelete =
    !viewingPublishedSnapshot &&
    template != null &&
    !template.latest_published_version_id &&
    (isOwner || hasPermission(DMS_PERMISSIONS.templateDelete));
  /** Coincide con `TemplatePolicy::clone` y `data.can_clone` de la API, también en vista de snapshot publicado. */
  const canClone = template?.can_clone === true;
  const canSubmit =
    !viewingPublishedSnapshot && isOwner && isDraft && hasReviewers && !template.has_review_comments;
  /** Alineado con `TemplatePolicy::startRevision`: creador o `template.version` en publicada. */
  const canStartNewVersion =
    !viewingPublishedSnapshot &&
    isPublished &&
    !selectionMode &&
    (isOwner || hasPermission(DMS_PERMISSIONS.templateVersion));
  /** Alineado con `TemplatePolicy::discard`: solo el creador. */
  const canDiscardWorkingVersion =
    !viewingPublishedSnapshot &&
    template != null &&
    isOwner &&
    isDiscardWorkingVersionAllowed(
      template.latest_published_version_id,
      template.working_version_id,
      template.status,
      ['draft', 'in_review', 'rejected'],
    );

  const processesQuery = useProcessesQuery(undefined, {
    enabled: !!template?.process_id,
  });
  const processLabel = (() => {
    if (!template?.process_id) return null;
    const process = processesQuery.data?.data.find((p) => p.id === template.process_id) ?? null;
    if (!process) return null;
    return `Proceso: ${process.code} — ${process.name}`;
  })();

  const handleConfirmChangelogSubmit = async (changelog: string) => {
    if (!id || !template) return false;
    setActionLoading(true);
    setActionError(null);
    setChangelogModalError(null);
    try {
      const res = await submitTemplateForReview(id, changelog);
      setTemplate(res.data);
      setShowChangelogModal(false);
      return true;
    } catch (e) {
      const message = e instanceof Error ? e.message : 'No se pudo enviar a validar.';
      setChangelogModalError(message);
      return false;
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
    setNewVersionLoading(true);
    setNewVersionError(null);
    try {
      const res = await startTemplateNewVersion(id);
      setTemplate(res.data);
      setShowNewVersionConfirm(false);
      navigate(`/templates/${id}/edit`);
    } catch (e) {
      if (e instanceof ApiHttpError && e.status === 409) {
        setShowNewVersionConfirm(false);
        setDraftBlockedBy(e.message);
        return;
      }
      setNewVersionError(e instanceof Error ? e.message : 'No se pudo abrir una nueva versión.');
    } finally {
      setNewVersionLoading(false);
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

  const viewToggle = template && id ? (
    <div className="group flex items-center gap-1 rounded-full border border-ui-border bg-ui-body/60 dark:bg-transparent dark:border-ui-dark-border p-0.5 text-xs hover:border-odoo-purple/80 hover:bg-black/10">
       <button
        type="button"
        onClick={() => setViewMode(prev => (prev === 'edit' ? 'themed' : 'edit'))}
        className={[
          'rounded-full px-2.5 py-1 font-medium transition-colors',
          viewMode === 'edit' ? 'bg-white dark:opacity-60 shadow-sm text-text-primary duration-900 group-hover:translate-x-2 group-hover:animate-slide group-hover:pl-0 group-hover:pr-5 group-hover:opacity-100 dark:bg-dark' : 'text-text-mutted',
        ].join(' ')}
        aria-pressed={viewMode === 'edit'}
      >
        Edición
      </button>
      <button
        type="button"
        onClick={() => setViewMode(prev => (prev === 'themed' ? 'edit' : 'themed'))}
        className={[
          'rounded-full px-2.5 py-1 font-medium transition-colors',
          viewMode === 'themed' ? 'bg-white dark:opacity-60 shadow-sm text-text-primary duration-900 group-hover:-translate-x-2 group-hover:animate-slide group-hover:pr-0 group-hover:pl-5 group-hover:opacity-100 dark:bg-dark ' : 'text-text-mutted',
        ].join(' ')}
        aria-pressed={viewMode === 'themed'}
      >
        Vista PDF
      </button>
    </div>
  ) : null;

  const headerToolbar = template ? (
    <div className="flex items-center justify-center gap-2 flex-wrap">
      {viewToggle}
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
          {template.status !== 'draft' && (
            <span className="text-xs font-mono bg-ui-body dark:bg-ui-dark-bg border border-ui-border dark:border-ui-dark-border px-2 py-0.5 rounded-full text-text-secondary dark:text-text-dark-secondary">
              v{template.version}
            </span>
          )}
          {template.status === 'in_review' && (template.reviewers?.length ?? 0) > 0 && (
            <SequentialValidatorBadge
              reviewMode={template.review_mode}
              reviewers={(template.reviewers ?? []).map((r) => ({ stage: r.stage ?? 0, status: r.status, name: r.user_name }))}
            />
          )}
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
          {!viewingPublishedSnapshot && template?.latest_published_version_id
            ? <FavoriteButton entityType="template" entityId={template.latest_published_version_id} />
            : null}
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
            <Button
              type="button"
              variant="outline"
              size="sm"
              loading={actionLoading}
              onClick={() => void handleClone()}
              title="Crea una plantilla nueva e independiente (no una versión de esta). Para una versión nueva, edita y publica esta plantilla."
            >
              Clonar como nueva plantilla
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
            <Button
              type="button"
              variant="primary"
              size="sm"
              loading={actionLoading}
              onClick={() => {
                setChangelogModalError(null);
                setShowChangelogModal(true);
              }}
            >
              Enviar a validar
            </Button>
          )}
        </>
      )}
    </div>
  ) : null;

  const headerMeta = template ? (
    <p className="text-xs text-text-muted dark:text-text-dark-muted text-center">
      {authorDisplay}
      {' · '}
      {displayVisibility ? visibilityLabel(displayVisibility) : '—'}
      {displayVisibility === 'study_type' && template.study_type_id ? (
        <> ({(hierarchy.find((t) => String(t.id) === String(template.study_type_id))?.name ?? template.study_type_id)})</>
      ) : null}
      {displayVisibility === 'study' && template.study_id ? (
        <> ({(hierarchy.flatMap((t) => t.studies ?? []).find((s) => String(s.id) === String(template.study_id))?.name ?? template.study_id)})</>
      ) : null}
      {displayVisibility === 'module' && template.module_id ? (
        <> ({(hierarchy.flatMap((t) => t.studies ?? []).flatMap((s) => s.course_modules ?? []).find((m) => String(m.id) === String(template.module_id))?.name ?? template.module_id)})</>
      ) : null}
      {displayVisibility === 'team' && (template.team?.name || template.team_id) ? (
        <> ({template.team?.name ?? template.team_id})</>
      ) : null}
      {' · '}
      Fecha límite de validación: {formatCalendarDateForBrowser(displayDeadline)}
      {' · '}
      Última edición: {formatCalendarDateForBrowser(displayUpdatedAt)}
    </p>
  ) : null;

  return (
    <>
      <PaperPreviewLayout
        title={displayTitle ?? template?.name ?? 'Plantilla'}
        subtitle={processLabel}
        onBack={handleBack}
        backLabel={selectionMode ? 'Seleccionar plantilla' : 'Volver'}
        metaInfo={headerMeta}
        actions={headerToolbar}
        viewMode={viewMode}
        sidebar={activeView && (() => {
          const block = blocks.find((b) => b.id === activeView.blockId);
          if (!block) return null;
          if (activeView.mode === 'comments') {
            return (
              <BlockCommentsCard
                mode={isOwner ? 'creator-edit' : 'creator-readonly'}
                blockSortOrder={(blocks.findIndex((b) => b.id === activeView.blockId) + 1) || '?'}
                blockComments={getCommentsForBlock(activeView.blockId, reviewComments)}
                allComments={reviewComments}
                commentLoading={reviewCommentsLoading}
                submitError={reviewCommentSubmitError}
                onSendMessage={handleSendMessage}
                headerRef={commentCardHeaderRef}
                onClose={() => setActiveView(null)}
                currentUserId={profile?.id}
                canDeleteAnyComment={canDeleteBlockComment(hasPermission)}
                onEditComment={handleEditComment}
                onDeleteComment={handleDeleteComment}
                onMarkAsRead={handleMarkCommentAsRead}
              />
            );
          }
          return (
            <div className="bg-ui-card dark:bg-ui-dark-card shadow-xl rounded-xl flex flex-col overflow-hidden h-full animate-in fade-in slide-in-from-right-4 duration-300">
              <ViewCardHeader
                blockSortOrder={(blocks.findIndex((b) => b.id === block.id) + 1) || '?'}
                title={t('review.blockDescriptionTitle')}
                onClose={() => setActiveView(null)}
                headerRef={commentCardHeaderRef}
              />
              <div className="flex-1 overflow-y-auto" style={{ padding: '40px 60px' }}>
                {block.description ? (
                  <BlockContentHtml content={normalizeBlockContentForEditor(block.description)} />
                ) : (
                  <p className="text-sm text-text-muted italic">Este bloque no tiene descripción.</p>
                )}
              </div>
            </div>
          );
        })()}
      >
        {actionError && (
          <p className="text-sm text-warning-dark dark:text-warning-light mb-4">{actionError}</p>
        )}

        {loading && (
          <p className="text-sm text-text-muted dark:text-text-dark-muted">Cargando plantilla…</p>
        )}
        {error && !loading && (
          <p className="text-sm text-warning-dark dark:text-warning-light">{error}</p>
        )}
        {viewMode === 'themed' && !loading && !error && template && id ? (
          <div className="h-[100vh] min-h-[600px] rounded border border-ui-border bg-white dark:border-ui-dark-border">
            <PagedThemedPreview kind="template" id={id} />
          </div>
        ) : null}
        {viewMode === 'edit' && !loading && !error && template && (
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
                  const isSelected = activeView?.blockId === block.id;
                  const infoActive = isSelected && activeView?.mode === 'info';
                  const unreadComments = countUnreadCommentsForBlock(block.id, reviewComments);

                  return (
                    <section
                      key={block.id}
                      className={[
                        'relative group rounded-lg transition-all duration-200',
                        !isPublished ? 'cursor-pointer' : '',
                        isSelected
                          ? 'ring-2 ring-odoo-purple ring-offset-8 dark:ring-offset-ui-dark-card shadow-sm'
                          : !isPublished ? 'hover:ring-1 hover:ring-ui-border dark:hover:ring-ui-dark-border hover:ring-offset-4 dark:hover:ring-offset-ui-dark-card' : '',
                        isLocked ? 'opacity-70' : '',
                      ].join(' ')}
                      onClick={(e) => { e.stopPropagation(); if (!isPublished) setActiveView({ blockId: block.id, mode: 'comments' }); }}
                    >
                      <div className="flex items-center gap-3 mb-4">
                        <h4 className="flex-1 min-w-0 text-xs font-black uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary opacity-60 truncate">
                          Bloque {(blocks.findIndex((b) => b.id === block.id) + 1)}: {block.title ?? 'Sin título'}
                        </h4>
                        <div className="flex items-center gap-2">
                          {Boolean(block.description) && (
                            <button
                              type="button"
                              onClick={(e) => { e.stopPropagation(); setActiveView({ blockId: block.id, mode: 'info' }); }}
                              className={[
                                'shrink-0 px-3 py-1.5 rounded-full border flex items-center gap-1.5 transition-all cursor-pointer text-xs font-black uppercase tracking-wider',
                                infoActive ? 'border-odoo-purple text-odoo-purple bg-odoo-purple/10 shadow-sm' : 'border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5'
                              ].join(' ')}
                              title={t('review.viewDescription')}
                            >
                              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                              </svg>
                              <span>Info</span>
                            </button>
                          )}
                          {!isPublished && (
                            <button
                              type="button"
                              onClick={(e) => { e.stopPropagation(); setActiveView({ blockId: block.id, mode: 'comments' }); }}
                              className={[
                                'shrink-0 px-3 py-1.5 rounded-full border flex items-center gap-1.5 transition-all cursor-pointer text-xs font-black uppercase tracking-wider',
                                isSelected && activeView?.mode === 'comments'
                                  ? 'border-odoo-purple text-odoo-purple bg-odoo-purple/10 shadow-sm'
                                  : 'border-ui-border dark:border-ui-dark-border text-text bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5',
                              ].join(' ')}
                              >
                              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                              </svg>
                              <span>Mensajes</span>
                              {unreadComments > 0 && (
                                <span className="ml-1 bg-odoo-purple text-text-inverse px-1.5 py-0.5 rounded-full text-2xs leading-none font-bold">
                                  {unreadComments}
                                </span>
                              )}
                            </button>
                          )}
                        </div>
                      </div>
                      {isStructuralBlockType(block.block_type) ? (
                        <StructuralBlockPreview block={block} allBlocks={blocks} />
                      ) : hasContent ? (
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
      </PaperPreviewLayout>

      {id && (
        <VersionHistoryPanel
          open={showHistory}
          entityType="template"
          entityId={id}
          onClose={() => setShowHistory(false)}
          canStartNewVersion={canStartNewVersion}
          onNewVersion={() => setShowNewVersionConfirm(true)}
        />
      )}

      <ConfirmDialog
        open={showNewVersionConfirm}
        title={t('preview.createNewVersionTitle')}
        description="Se creará un nuevo borrador editable a partir de la plantilla publicada actual. Podrás modificarla y volver a enviarla a validar."
        confirmLabel="Crear nueva versión"
        cancelLabel="Cancelar"
        loading={newVersionLoading}
        error={newVersionError}
        onConfirm={() => void handleStartNewVersion()}
        onCancel={() => { setShowNewVersionConfirm(false); setNewVersionError(null); }}
      />

      <ConfirmDialog
        open={draftBlockedBy !== null}
        variant="teal"
        title={t('preview.draftAlreadyExistsTitle')}
        icon="🔒"
        description={draftBlockedBy ?? ''}
        confirmLabel="Entendido"
        onConfirm={() => setDraftBlockedBy(null)}
        onCancel={() => setDraftBlockedBy(null)}
      />

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
        title={t('preview.discardNewVersionTitle')}
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

      <VersionChangelogModal
        open={showChangelogModal}
        title={t('modals.sendValidation')}
        initialValue={template?.submission_changelog}
        confirmLabel={actionLoading ? 'Enviando…' : 'Confirmar envío'}
        loading={actionLoading}
        error={changelogModalError}
        onCancel={() => {
          setShowChangelogModal(false);
          setChangelogModalError(null);
        }}
        onConfirm={handleConfirmChangelogSubmit}
      />
    </>
  );
}
