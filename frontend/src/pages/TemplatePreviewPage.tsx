import { useEffect, useLayoutEffect, useRef, useState } from 'react';
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
import { BlockCommentsCard, ViewCardHeader } from '../features/templates/components/BlockCommentsCard';
import type { BlockComment } from '../features/templates/components/BlockCommentsCard';
import { PaperPreviewLayout } from '../features/documents/components/PaperPreviewLayout';

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
  const [reviewCommentsLoading, setReviewCommentsLoading] = useState(false);
  const [activeView, setActiveView] = useState<{ blockId: string; mode: 'comments' | 'info' } | null>(null);
  const [publishedVersionCount, setPublishedVersionCount] = useState<number | null>(null);

  // Ref for the comment card header.
  const commentCardHeaderRef = useRef<HTMLDivElement>(null);

  // Dynamic top position for the fixed comment panel — no longer needed with PaperPreviewLayout

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

  const handleSendMessage = async (parentId: string | null, body: string) => {
    if (!activeView?.blockId || !id) return;
    setReviewCommentsLoading(true);
    try {
      const res = await apiFetchJson<{ data: ReviewComment }>(`templates/${id}/comments`, {
        method: 'POST',
        body: { body, parent_id: parentId, blockable_id: activeView.blockId },
      });
      setReviewComments(prev => [...prev, res.data]);
    } catch (e) {
      console.error('Error sending message', e);
    } finally {
      setReviewCommentsLoading(false);
    }
  };


  // Comment helpers that include replies in the count
  const getCommentsForBlock = (bid: string | null, allComments: BlockComment[]) => {
    if (!bid) return [];
    const roots = allComments.filter(c => c.blockable_id === bid && !c.parent_id);
    const rootIds = roots.map(r => r.id);
    return allComments.filter(c =>
      c.blockable_id === bid ||
      (c.parent_id && rootIds.includes(c.parent_id)) ||
      allComments.some(r => r.id === c.parent_id && r.blockable_id === bid)
    );
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
  const showVersionHistory = publishedVersionCount !== null && publishedVersionCount > 0;

  const canEdit = isOwner && isDraft && !viewingPublishedSnapshot;
  /** Igual que `TemplatePolicy::delete` (backend): creador o `templates.delete`, cualquier estado. */
  const canDelete =
    !viewingPublishedSnapshot &&
    template != null &&
    (isOwner || hasPermission('templates.delete'));
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
    <>
      <PaperPreviewLayout
        title={displayTitle ?? template?.name ?? 'Plantilla'}
        onBack={handleBack}
        backLabel={selectionMode ? 'Seleccionar plantilla' : 'Volver'}
        metaInfo={headerMeta}
        actions={headerToolbar}
        sidebar={activeView && (() => {
          const block = blocks.find((b) => b.id === activeView.blockId);
          if (!block) return null;
          if (activeView.mode === 'comments') {
            return (
              <BlockCommentsCard
                mode={isOwner ? 'creator-edit' : 'creator-readonly'}
                blockSortOrder={(blocks.findIndex((b: any) => b.id === activeView.blockId) + 1) || '?'}
                blockComments={getCommentsForBlock(activeView.blockId, reviewComments)}
                allComments={reviewComments}
                commentLoading={reviewCommentsLoading}
                onSendMessage={handleSendMessage}
                headerRef={commentCardHeaderRef}
                onClose={() => setActiveView(null)}
              />
            );
          }
          return (
            <div className="bg-ui-card dark:bg-ui-dark-card shadow-xl rounded-xl flex flex-col overflow-hidden h-full animate-in fade-in slide-in-from-right-4 duration-300">
              <ViewCardHeader
                blockSortOrder={(blocks.findIndex((b: any) => b.id === block.id) + 1) || '?'}
                title="Descripción del Bloque"
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
                {blocks.map((block: any) => {
                  const isLocked = block.block_state === 'locked';
                  const nodes = blockContentNodes(block);
                  const hasContent = nodes.length > 0;
                  const isSelected = activeView?.blockId === block.id;
                  const infoActive = isSelected && activeView?.mode === 'info';
                  const commentsActive = isSelected && activeView?.mode === 'comments';
                  const totalComments = getCommentsForBlock(block.id, reviewComments);

                  return (
                    <section
                      key={block.id}
                      className={[
                        'relative group rounded-lg transition-all duration-200 cursor-pointer',
                        isSelected
                          ? 'ring-2 ring-odoo-purple ring-offset-8 dark:ring-offset-ui-dark-card shadow-sm'
                          : 'hover:ring-1 hover:ring-ui-border dark:hover:ring-ui-dark-border hover:ring-offset-4 dark:hover:ring-offset-ui-dark-card',
                        isLocked ? 'opacity-70' : '',
                      ].join(' ')}
                      onClick={(e) => { e.stopPropagation(); setActiveView({ blockId: block.id, mode: 'comments' }); }}
                    >
                      <div className="flex items-center gap-3 mb-4">
                        <h4 className="flex-1 min-w-0 text-xs font-black uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary opacity-60 truncate">
                          Bloque {(blocks.findIndex((b: any) => b.id === block.id) + 1)}: {block.title ?? 'Sin título'}
                        </h4>
                        <div className="flex items-center gap-2">
                          {block.description && (
                            <button
                              type="button"
                              onClick={(e) => { e.stopPropagation(); setActiveView({ blockId: block.id, mode: 'info' }); }}
                              className={[
                                'shrink-0 px-3 py-1.5 rounded-full border flex items-center gap-1.5 transition-all cursor-pointer text-xs font-black uppercase tracking-wider',
                                infoActive ? 'border-odoo-purple text-odoo-purple bg-odoo-purple/10 shadow-sm' : 'border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5'
                              ].join(' ')}
                              title="Ver descripción"
                            >
                              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                              </svg>
                              <span>Info</span>
                            </button>
                          )}
                          <button
                            type="button"
                            onClick={(e) => { e.stopPropagation(); setActiveView({ blockId: block.id, mode: 'comments' }); }}
                            className={[
                              'shrink-0 px-3 py-1.5 rounded-full border flex items-center gap-1.5 transition-all cursor-pointer text-xs font-black uppercase tracking-wider',
                              commentsActive
                                ? 'border-odoo-purple text-odoo-purple bg-odoo-purple/10 shadow-sm'
                                : 'border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5',
                            ].join(' ')}
                          >
                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                              <path strokeLinecap="round" strokeLinejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                            <span>Mensajes</span>
                            {totalComments.length > 0 && (
                              <span className="ml-1 bg-odoo-purple text-white px-1.5 py-0.5 rounded-full text-[10px] leading-none font-bold">
                                {totalComments.length}
                              </span>
                            )}
                          </button>
                        </div>
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
      </PaperPreviewLayout>

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
    </>
  );
}
