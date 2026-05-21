import { useEffect, useMemo, useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { Link, useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import {
  fetchDocument,
  fetchDocumentReviews,
  fetchDocumentVersionDetail,
  submitDocumentForReview,
  deleteDocument,
  approveDocumentReview,
  rejectDocumentReview,
  startDocumentNewVersion,
  cloneDocument,
  discardDocumentWorkingVersion,
  type DocumentReview,
} from '../api/documents';
import { fetchMe } from '../api/users';
import { useDocumentVersionSummariesQuery } from '../features/documents/hooks/useDocumentVersionSummaries';
import { useDocumentCommentsQuery } from '../features/documents/hooks/useDocumentComments';
import { useTemplateQuery } from '../features/templates/hooks/useTemplate';
import { useProcessesQuery } from '../hooks/useProcesses';
import { normalizeBlockContentForEditor } from '../features/documents/lib/normalizeBlockContent';
import type { BlockState } from '../types/blocks';
import type { DocumentDetail, DocumentDisplayBlock } from '../types/documents';
import { visibilityLabel } from '../features/templates/constants';
import { Button, ConfirmDialog, statusBadgeClass } from '@maya/shared-ui-react';
import { FavoriteButton } from '../components/FavoriteButton';
import { VersionHistoryPanel } from '../components/VersionHistoryPanel';
import { useUserProfile } from '../features/user-profile';
import { PaperPreviewLayout } from '../features/documents/components/PaperPreviewLayout';
import { PaperBlocksArticle, type PaperArticleBlock } from '../features/documents/components/PaperBlocksArticle';
import { BlockCommentsCard, ViewCardHeader } from '../features/templates/components/BlockCommentsCard';
import type { BlockComment } from '../features/templates/components/BlockCommentsCard';
import { BlockContentHtml } from '../features/templates/components/BlockContentHtml';
import { computeChangedBlocks } from '../features/documents/components/DocumentDiffModal';
import { DocumentDiffPanel } from '../features/documents/components/DocumentDiffPanel';
import { DocumentBlockHistoryPanel } from '../features/documents/components/DocumentBlockHistoryPanel';
import { apiFetchJson, ApiHttpError } from '../api/http';
import type { Process } from '../types/processes';
import { formatCalendarDateForBrowser } from '../utils/formatCalendarDate';
import { getCommentsForBlock } from '../utils/blockComments';
import { SequentialValidatorBadge } from '../features/documents/components/SequentialValidatorBadge';

// Estado: clases en `statusBadgeClass` (módulo `@maya/shared-ui-react/badges`).

const STATUS_LABEL: Record<string, string> = {
  draft: 'Borrador',
  in_review: 'En revisión',
  published: 'Publicado',
  rejected: 'Rechazado',
};

function blockContentForPreview(block: DocumentDisplayBlock): unknown[] {
  const fromContent = normalizeBlockContentForEditor(block.content);
  if (fromContent.length > 0) return fromContent;
  return normalizeBlockContentForEditor(block.default_content);
}

function mapSnapshotDocumentBlocks(raw: unknown): DocumentDisplayBlock[] {
  if (!Array.isArray(raw)) return [];
  const out: DocumentDisplayBlock[] = [];
  for (let idx = 0; idx < raw.length; idx++) {
    const item = raw[idx];
    if (!item || typeof item !== 'object') continue;
    const o = item as Record<string, unknown>;
    const blockState = (typeof o.block_state === 'string' ? o.block_state : 'locked') as BlockState;
    out.push({
      document_block_id: typeof o.document_block_id === 'string' ? o.document_block_id : null,
      template_block_id: String(o.template_block_id ?? o.id ?? ''),
      type: typeof o.type === 'string' ? o.type : 'text',
      title: o.title != null ? String(o.title) : null,
      description: o.description,
      default_content: o.default_content ?? null,
      block_state: blockState,
      mandatory: Boolean(o.mandatory),
      sort_order: typeof o.sort_order === 'number' ? o.sort_order : idx,
      content: o.content ?? null,
      is_filled: Boolean(o.is_filled),
      is_deleted: Boolean(o.is_deleted),
    });
  }
  return out;
}

function snapshotDocumentTitle(snapshotData: Record<string, unknown>): string | undefined {
  const doc = snapshotData.document;
  if (!doc || typeof doc !== 'object') return undefined;
  const t = (doc as Record<string, unknown>).title;
  return typeof t === 'string' ? t : undefined;
}

function pickActionableDocumentReview(
  reviews: DocumentReview[],
  reviewerUserId: string,
  reviewMode: 'sequential' | 'parallel',
): DocumentReview | null {
  const pending = reviews.filter((r) => r.status === 'pending');
  if (pending.length === 0) return null;
  const mine = pending.filter((r) => r.reviewer_id === reviewerUserId);
  if (mine.length === 0) return null;
  if (reviewMode !== 'sequential') return mine[0] ?? null;
  const minStage = Math.min(...pending.map((r) => r.stage));
  return mine.find((r) => r.stage === minStage) ?? null;
}

function validationSuccessBannerMessage(
  updated: { title: string; status: string },
  action: 'approve' | 'reject',
): string {
  if (action === 'reject') {
    return 'Rechazo registrado. El documento ha vuelto a borrador para que el titular pueda corregirlo.';
  }
  if (updated.status === 'published') {
    return `Validación realizada. El documento «${updated.title}» ha sido publicado.`;
  }
  return 'Validación realizada. Este documento se ha pasado al siguiente validador.';
}

type Props = {
  mode?: 'preview' | 'validate';
};

export function DocumentPreviewPage({ mode = 'preview' }: Props = {}) {
  const { documentId } = useParams<{ documentId: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [searchParams] = useSearchParams();
  const documentVersionId = searchParams.get('documentVersionId');
  const location = useLocation();
  const { profile, hasPermission } = useUserProfile();
  const isValidateMode = mode === 'validate';

  const [detail, setDetail] = useState<DocumentDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [diffBlockId, setDiffBlockId] = useState<string | null>(null);
  const [historyBlockId, setHistoryBlockId] = useState<string | null>(null);
  const [showHistory, setShowHistory] = useState(false);
  const [versionSnapshot, setVersionSnapshot] = useState<{
    versionNumber: number;
    blocks: DocumentDisplayBlock[];
    title?: string;
    authorName?: string | null;
    ownerName?: string | null;
    createdAt?: string | null;
  } | null>(null);
  const [versionPreviewError, setVersionPreviewError] = useState<string | null>(null);
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
  const [autoPublishBanner, setAutoPublishBanner] = useState(false);
  const [validationReviewLoading, setValidationReviewLoading] = useState(false);
  const [validationSetupError, setValidationSetupError] = useState<string | null>(null);
  const [actionableReviewId, setActionableReviewId] = useState<string | null>(null);
  const [allReviews, setAllReviews] = useState<DocumentReview[]>([]);
  const [validateConfirm, setValidateConfirm] = useState<null | 'approve' | 'reject'>(null);
  const [validationActionLoading, setValidationActionLoading] = useState(false);
  const [validationModalError, setValidationModalError] = useState<string | null>(null);
  // processLabel + publishedDocumentVersionCount derive from createDataHook queries below.

  // Validate-mode comment + info state (mirrors TemplateReviewView)
  type ValidateActiveView = { blockId: string; mode: 'comments' | 'info' } | null;
  const [validateActiveView, setValidateActiveView] = useState<ValidateActiveView>(null);
  const validateActiveBlockId = validateActiveView?.blockId ?? null;
  const [validateCommentLoading, setValidateCommentLoading] = useState(false);
  const [validateCommentSubmitError, setValidateCommentSubmitError] = useState<string | null>(null);
  const validateBlockRefs = useRef<Map<string, HTMLElement>>(new Map());
  const validateViewHeaderRef = useRef<HTMLDivElement>(null);

  // Creator preview-mode comment state (mirrors TemplatePreviewPage)
  const [reviewCommentsLoading, setReviewCommentsLoading] = useState(false);
  const [selectedReviewView, setSelectedReviewView] = useState<{ blockId: string; mode: 'comments' | 'info' } | null>(null);
  const pageHeaderRef = useRef<HTMLDivElement>(null);

  const versionSummariesQuery = useDocumentVersionSummariesQuery(documentId ?? '', {
    enabled: !!documentId,
  });
  const publishedDocumentVersionCount =
    versionSummariesQuery.data?.length ?? null;

  useEffect(() => {
    if (!documentId) {
      setLoading(false);
      setError('Identificador de documento no válido.');
      return;
    }

    let cancelled = false;
    const load = async () => {
      try {
        setLoading(true);
        setError(null);
        setVersionSnapshot(null);
        setVersionPreviewError(null);

        const data = await fetchDocument(documentId);
        if (cancelled) return;
        setDetail(data);

        if (documentVersionId) {
          try {
            const v = await fetchDocumentVersionDetail(documentId, documentVersionId);
            if (cancelled) return;
            const snap = v.snapshot_data && typeof v.snapshot_data === 'object' ? v.snapshot_data : {};
            const blocks = mapSnapshotDocumentBlocks(snap.blocks);
            setVersionSnapshot({
              versionNumber: v.version_number,
              blocks,
              title: snapshotDocumentTitle(snap as Record<string, unknown>),
              authorName: v.author_name ?? null,
              ownerName: v.owner_name ?? null,
              createdAt: v.created_at ?? null,
            });
          } catch (ve) {
            if (!cancelled) {
              setVersionPreviewError(ve instanceof Error ? ve.message : 'No se pudo cargar esta versión.');
            }
          }
        }
      } catch (e) {
        if (!cancelled) {
          setError(e instanceof Error ? e.message : 'No se pudo cargar el documento.');
          setDetail(null);
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    };

    void load();
    return () => { cancelled = true; };
  }, [documentId, documentVersionId]);

  const previewState = location.state as {
    returnToStep?: string;
    returnToValidate?: boolean;
    backTo?: string;
    forceBackTo?: boolean;
  } | null;
  const cameFromSummary = previewState?.returnToStep === 'summary';
  const cameFromValidate = previewState?.returnToValidate === true;
  const backTo = previewState?.backTo ?? '/dashboard';

  const backLabel = cameFromSummary
    ? cameFromValidate ? 'Volver a validar' : 'Volver al resumen'
    : 'Volver';

  const handleBack = () => {
    if (window.history.length <= 1) {
      navigate("/dashboard");
    } else {
      navigate(-1);
    }
  };

  const isDraft = detail?.status === 'draft' || detail?.status === 'rejected';
  const isPublished = detail?.status === 'published';
  const isDocumentReviewer = allReviews.some((r) => r.reviewer_id === profile?.id);
  const myDocumentReview = allReviews.find((r) => r.reviewer_id === profile?.id) ?? null;
  const changedBlocks = useMemo(
    () => (detail ? computeChangedBlocks(detail.blocks) : []),
    [detail],
  );
  const diffPanelBlocks = useMemo(
    () => (diffBlockId ? changedBlocks.filter((b) => b.template_block_id === diffBlockId) : []),
    [changedBlocks, diffBlockId],
  );
  const isOwner = profile?.id === detail?.owner_id || profile?.id === detail?.created_by;
  const uid = profile?.id;
  /** Paridad con `DocumentPolicy::update` para poder mutar un publicado. */
  const canMutatePublished =
    !!detail &&
    !!uid &&
    (detail.owner_id === uid ||
      detail.created_by === uid ||
      detail.share_permission === 'edit' ||
      hasPermission('documents.update'));
  const isHistoricalSnapshot = versionSnapshot !== null;
  const showVersionHistory =
    publishedDocumentVersionCount !== null && publishedDocumentVersionCount > 0;
  const canStartNewVersion =
    !isValidateMode && isPublished && canMutatePublished && !isHistoricalSnapshot;
  const canClone =
    !isValidateMode &&
    !isHistoricalSnapshot &&
    detail?.can_clone === true;
  const canDiscardWorkingVersion =
    !isValidateMode &&
    !isHistoricalSnapshot &&
    (detail?.status === 'draft' || detail?.status === 'in_review' || detail?.status === 'rejected') &&
    canMutatePublished &&
    !!detail?.latest_published_version_id &&
    !!detail?.working_version_id;

  useEffect(() => {
    if (!isValidateMode) {
      setValidationReviewLoading(false);
      setValidationSetupError(null);
      setActionableReviewId(null);
    }
    if (!detail || detail.status !== 'in_review') {
      setAllReviews([]);
      return;
    }

    let cancelled = false;
    if (isValidateMode) {
      setValidationReviewLoading(true);
      setValidationSetupError(null);
      setActionableReviewId(null);
    }

    void (async () => {
      try {
        const [reviews, meRes] = await Promise.all([
          fetchDocumentReviews(detail.id),
          fetchMe(),
        ]);
        if (cancelled) return;
        setAllReviews(reviews);
        if (isValidateMode) {
          const reviewMode = detail.review_mode === 'sequential' ? 'sequential' : 'parallel';
          const actionable = pickActionableDocumentReview(reviews, meRes.data.id, reviewMode);
          if (!actionable) {
            setValidationSetupError(
              'No tienes una revisión pendiente que puedas tramitar para este documento.',
            );
            setActionableReviewId(null);
          } else {
            setActionableReviewId(actionable.id);
            setValidationSetupError(null);
          }
        }
      } catch (e) {
        if (!cancelled) {
          if (isValidateMode) {
            setValidationSetupError(
              e instanceof Error ? e.message : 'No se pudo cargar la información de validación.',
            );
          }
          setActionableReviewId(null);
        }
      } finally {
        if (!cancelled && isValidateMode) setValidationReviewLoading(false);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [isValidateMode, detail?.id, detail?.status, detail?.template_id]);

  const templateForLabelQuery = useTemplateQuery(detail?.template_id ?? '', {
    enabled: !!detail?.template_id,
  });
  const processesQuery = useProcessesQuery(undefined, {
    enabled: !!detail?.template_id,
  });
  const processLabel = useMemo<string | null>(() => {
    const processId = templateForLabelQuery.data?.data.process_id;
    if (!processId) return null;
    const process = processesQuery.data?.data.find((p: Process) => p.id === processId) ?? null;
    if (!process) return null;
    return `Proceso: ${process.code} — ${process.name}`;
  }, [templateForLabelQuery.data, processesQuery.data]);

  // Document comments — used for validate-mode panel and preview-mode review banner.
  const validateCommentsEnabled = isValidateMode && !!documentId && !!detail;
  const previewCommentsEnabled =
    !isValidateMode && !!documentId && !!detail;
  const documentCommentsQuery = useDocumentCommentsQuery(documentId ?? '', {
    enabled: validateCommentsEnabled || previewCommentsEnabled,
  });
  const validateComments: BlockComment[] = validateCommentsEnabled
    ? documentCommentsQuery.data?.data ?? []
    : [];
  const reviewComments: BlockComment[] = previewCommentsEnabled
    ? documentCommentsQuery.data?.data ?? []
    : [];
  const validateCommentLoadError =
    validateCommentsEnabled && documentCommentsQuery.error
      ? documentCommentsQuery.error.message ?? 'No se pudieron cargar los comentarios.'
      : null;
  const validateCommentError = validateCommentSubmitError ?? validateCommentLoadError;

  const openValidateView = (blockId: string, mode: 'comments' | 'info') => {
    setValidateActiveView((prev) =>
      prev?.blockId === blockId && prev?.mode === mode ? null : { blockId, mode },
    );
  };

  const appendCommentToCache = (id: string, comment: BlockComment) => {
    queryClient.setQueryData<{ data: BlockComment[] }>(
      ['documents', id, 'comments'],
      (prev) => ({ data: [...(prev?.data ?? []), comment] }),
    );
  };

  const handlePreviewSendMessage = async (parentId: string | null, body: string) => {
    if (!documentId || !selectedReviewView?.blockId) return;
    setReviewCommentsLoading(true);
    try {
      const res = await apiFetchJson<{ data: BlockComment }>(`documents/${documentId}/comments`, {
        method: 'POST',
        body: {
          body,
          parent_id: parentId,
          blockable_id: selectedReviewView.blockId,
          document_version_id: detail?.working_version_id || null
        },
      });
      appendCommentToCache(documentId, res.data);
    } catch (e) {
      console.error('Error sending message', e);
    } finally {
      setReviewCommentsLoading(false);
    }
  };

  const handleValidateSendMessage = async (parentId: string | null, body: string) => {
    if (!documentId) return;
    setValidateCommentLoading(true);
    setValidateCommentSubmitError(null);
    try {
      const parent = parentId ? validateComments.find(c => c.id === parentId) : null;
      const blockableId = parentId
        ? (parent?.blockable_id ?? null)
        : (detail?.blocks.find(b => b.template_block_id === validateActiveBlockId)?.document_block_id ?? null);
      const res = await apiFetchJson<{ data: BlockComment }>(`documents/${documentId}/comments`, {
        method: 'POST',
        body: { body, parent_id: parentId, blockable_id: blockableId },
      });
      appendCommentToCache(documentId, res.data);
    } catch {
      setValidateCommentSubmitError('No se pudo guardar el comentario.');
    } finally {
      setValidateCommentLoading(false);
    }
  };

  const handleDelete = async () => {
    if (!documentId) return;
    setDeleteLoading(true);
    setDeleteError(null);
    try {
      await deleteDocument(documentId);
      navigate(backTo);
    } catch (e) {
      setDeleteError(e instanceof Error ? e.message : 'No se pudo eliminar el documento.');
      setDeleteLoading(false);
    }
  };

  const handleDiscardWorkingVersion = async () => {
    if (!documentId || !detail?.working_version_id) return;
    setDiscardVersionLoading(true);
    setDiscardVersionError(null);
    try {
      const restored = await discardDocumentWorkingVersion(documentId, detail.working_version_id);
      setDetail(restored);
      setVersionSnapshot(null);
      setShowDiscardVersionModal(false);
      setActionError(null);
    } catch (e) {
      setDiscardVersionError(e instanceof Error ? e.message : 'No se pudo descartar la versión en curso.');
    } finally {
      setDiscardVersionLoading(false);
    }
  };

  const handleSubmit = async () => {
    if (!documentId || !detail) return;

    const emptyEditable = detail.blocks.filter((b: DocumentDisplayBlock) =>
      b.block_state === 'editable' && !b.is_filled && !b.is_deleted,
    );
    if (emptyEditable.length > 0) {
      const names = emptyEditable.map((b: DocumentDisplayBlock) => b.title ?? 'Sin título').join(', ');
      setActionError(`Debes rellenar todos los bloques editables antes de enviar a revisión. Pendientes: ${names}.`);
      return;
    }

    setActionLoading(true);
    setActionError(null);
    try {
      const res = await submitDocumentForReview(documentId);
      if (res.status === 'published') setAutoPublishBanner(true);
      setDetail((prev) => prev ? ({ ...prev, status: res.status, submitted_at: res.submitted_at } as typeof prev) : prev);
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'No se pudo enviar a validar.');
    } finally {
      setActionLoading(false);
    }
  };

  const handleStartNewVersion = async () => {
    if (!documentId) return;
    setNewVersionLoading(true);
    setNewVersionError(null);
    try {
      const data = await startDocumentNewVersion(documentId);
      setDetail(data);
      setShowNewVersionConfirm(false);
      navigate(`/documents/${documentId}/editor`, { state: { step: 'properties' } });
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

  const handleClone = async () => {
    if (!documentId) return;
    setActionLoading(true);
    setActionError(null);
    try {
      const cloned = await cloneDocument(documentId);
      navigate(`/documents/${cloned.id}/editor`);
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'No se pudo clonar el documento.');
    } finally {
      setActionLoading(false);
    }
  };

  const handleApproveValidation = async () => {
    if (!documentId || !actionableReviewId) {
      setValidationModalError('Faltan datos críticos para procesar la revisión.');
      return;
    }
    setValidationModalError(null);
    setValidationActionLoading(true);
    try {
      const updated = await approveDocumentReview(documentId, actionableReviewId, null);
      setValidateConfirm(null);
      navigate(backTo, {
        state: { documentValidationBanner: validationSuccessBannerMessage(updated, 'approve'), tab: 'documents' },
      });
    } catch (e) {
      setValidationModalError(e instanceof Error ? e.message : 'No se pudo aprobar la revisión.');
    } finally {
      setValidationActionLoading(false);
    }
  };

  const validatorHasCommented = uid ? validateComments.some(c => c.author_id === uid) : false;

  const handleRejectValidation = async () => {
    if (!documentId || !actionableReviewId) {
      setValidationModalError('Faltan datos críticos para procesar la revisión.');
      return;
    }
    setValidationModalError(null);
    setValidationActionLoading(true);
    try {
      const updated = await rejectDocumentReview(documentId, actionableReviewId, null);
      setValidateConfirm(null);
      navigate(backTo, {
        state: { documentValidationBanner: validationSuccessBannerMessage(updated, 'reject'), tab: 'documents' },
      });
    } catch (e) {
      setValidationModalError(e instanceof Error ? e.message : 'No se pudo rechazar la revisión.');
    } finally {
      setValidationActionLoading(false);
    }
  };

  const headerActions = detail ? (
    <>
      {isHistoricalSnapshot && !isValidateMode ? (
        <>
          <span className="text-xs font-medium px-2 py-0.5 rounded-full bg-primary/15 text-primary-dark dark:text-primary-light border border-primary/25">
            Versión publicada v{versionSnapshot.versionNumber}
          </span>
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => documentId && navigate(`/documents/${documentId}`, { replace: true })}
          >
            Versión actual
          </Button>
        </>
      ) : (
        <>
          <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${statusBadgeClass(detail.status)}`}>
            {STATUS_LABEL[detail.status] ?? detail.status}
          </span>
          {detail.status !== 'draft' && (
          <span className="text-xs font-mono bg-ui-body dark:bg-ui-dark-bg border border-ui-border dark:border-ui-dark-border px-2 py-0.5 rounded-full text-text-secondary dark:text-text-dark-secondary">
            v{detail.current_version}
          </span>
          )}
        </>
      )}
      {!isValidateMode && !isHistoricalSnapshot && detail.status === 'in_review' && allReviews.length > 0 && (
        <SequentialValidatorBadge
          reviewMode={detail.review_mode}
          reviewers={allReviews.map((r) => ({ stage: r.stage, status: r.status, name: r.reviewer_name }))}
        />
      )}
      {documentId && !isHistoricalSnapshot ? <FavoriteButton entityType="document" entityId={documentId} /> : null}
      {showVersionHistory ? (
        <Button type="button" variant="outline" size="sm" onClick={() => setShowHistory(true)}>
          Historial
        </Button>
      ) : null}
      {!isValidateMode && !isHistoricalSnapshot && isDraft && isOwner && !detail.latest_published_version_id && (
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
      {!isValidateMode && !isHistoricalSnapshot && isDraft && isOwner && documentId && (
        <Link to={`/documents/${documentId}/editor`}>
          <Button type="button" size="sm" variant="outline">
            Editar
          </Button>
        </Link>
      )}
      {!isValidateMode && !isHistoricalSnapshot && isDraft && isOwner && (
        detail.has_review_comments ? (
          <span
            title="No puedes enviar a validar mientras haya comentarios de revisión sin resolver"
            className="inline-flex"
          >
            <Button
              type="button"
              variant="primary"
              size="sm"
              disabled
              aria-disabled="true"
            >
              Enviar a validar
            </Button>
          </span>
        ) : (
          <Button
            type="button"
            variant="primary"
            size="sm"
            loading={actionLoading}
            onClick={() => void handleSubmit()}
          >
            Enviar a validar
          </Button>
        )
      )}
      {!isValidateMode && canStartNewVersion && (
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => setShowNewVersionConfirm(true)}
        >
          Nueva versión
        </Button>
      )}
      {!isValidateMode && canClone && (
        <Button
          type="button"
          variant="outline"
          size="sm"
          loading={actionLoading}
          onClick={() => void handleClone()}
        >
          Clonar
        </Button>
      )}
      {!isValidateMode && canDiscardWorkingVersion && (
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
      {isValidateMode && (
        <>
          <Button
            type="button"
            variant="outlineWarning"
            size="sm"
            disabled={!actionableReviewId || validationReviewLoading}
            onClick={() => {
              setValidationModalError(null);
              setValidateConfirm('reject');
            }}
            className="text-xs font-black uppercase tracking-wider"
          >
            Rechazar validación
          </Button>
          <Button
            type="button"
            variant="primary"
            size="sm"
            disabled={!actionableReviewId || validationReviewLoading}
            onClick={() => {
              setValidationModalError(null);
              setValidateConfirm('approve');
            }}
          >
            Aprobar
          </Button>
        </>
      )}
    </>
  ) : null;

  const headerMetaInfo = detail ? (
    <p className="text-xs text-text-muted dark:text-text-dark-muted text-center">
      {processLabel ? (
        <>
          {processLabel}
          {' · '}
        </>
      ) : null}
      {(versionSnapshot?.ownerName ?? versionSnapshot?.authorName ?? detail.owner_name) ?? 'Autor desconocido'}
      {' · '}
      {detail.visibility_level ? visibilityLabel(detail.visibility_level) : (detail.is_shared_with_me ? 'Compartida' : 'Personal')}
      {' · '}
      Fecha límite: {formatCalendarDateForBrowser(detail.delivery_deadline)}
      {' · '}
      Última edición: {formatCalendarDateForBrowser(versionSnapshot?.createdAt ?? detail.updated_at)}
    </p>
  ) : null;

  const previewTitle = versionSnapshot?.title ?? detail?.title ?? 'Programación';
  const blocksForArticle = versionSnapshot?.blocks ?? detail?.blocks ?? [];
  const articleBlocks: PaperArticleBlock[] = blocksForArticle.map((b) => ({
    id: b.template_block_id,
    title: b.title,
    mandatory: b.mandatory,
    isLocked: b.block_state === 'locked',
    nodes: blockContentForPreview(b),
  }));

  if (isValidateMode) {
    const validateBlocks = detail?.blocks ?? [];
    const validateSelectedBlock = validateBlocks.find(b => b.template_block_id === validateActiveBlockId);
    const validateBlockComments = validateSelectedBlock
      ? getCommentsForBlock(validateSelectedBlock.document_block_id, validateComments)
      : [];
    const historySelectedBlock = historyBlockId
      ? (validateBlocks.find(b => b.template_block_id === historyBlockId) ?? null)
      : null;
    const historyDocBlockId = historySelectedBlock?.document_block_id ?? null;
    const historyBlockNumber = historySelectedBlock
      ? validateBlocks.indexOf(historySelectedBlock) + 1
      : '?';
    const hasReviewHistory = (detail?.review_history?.length ?? 0) > 0;

    return (
      <>
        <PaperPreviewLayout
          title="Validación de Documento"
          onBack={handleBack}
          backLabel="Volver"
          metaInfo={
            <div className="flex flex-col items-center">
              <p className="text-xs text-text-muted uppercase tracking-widest font-black truncate max-w-[320px]">
                {detail?.title ?? 'Documento'}
              </p>
              {processLabel && (
                <p className="text-[11px] text-text-muted mt-0.5 truncate max-w-[420px]">
                  {processLabel}
                </p>
              )}
            </div>
          }
          actions={
            <div className="flex items-center gap-2">
              <SequentialValidatorBadge
                reviewMode={detail?.review_mode}
                reviewers={allReviews.map((r) => ({ stage: r.stage, status: r.status, name: r.reviewer_name }))}
              />
              {actionableReviewId ? (
                <>
                  <Button
                    type="button"
                    variant="outlineWarning"
                    size="sm"
                    disabled={validationReviewLoading}
                    onClick={() => { setValidationModalError(null); setValidateConfirm('reject'); }}
                    className="text-xs font-black uppercase tracking-wider"
                  >
                    Rechazar validación
                  </Button>
                  <Button
                    type="button"
                    variant="primary"
                    size="sm"
                    disabled={validationReviewLoading}
                    onClick={() => { setValidationModalError(null); setValidateConfirm('approve'); }}
                    className="text-xs font-black uppercase tracking-wider px-6"
                  >
                    Validar y aprobar
                  </Button>
                </>
              ) : myDocumentReview?.status === 'approved' ? (
                <div className="flex items-center gap-2 px-4 py-1.5 rounded-full bg-success/10 border border-success/20">
                  <span className="text-success-dark text-xs font-black uppercase tracking-widest">
                    ✓ Aprobaste esta programación
                  </span>
                </div>
              ) : myDocumentReview?.status === 'rejected' ? (
                <div className="flex items-center gap-2 px-4 py-1.5 rounded-full bg-warning/10 border border-warning/20">
                  <span className="text-warning-dark dark:text-warning-light text-xs font-black uppercase tracking-widest">
                    ✗ Rechazaste esta programación
                  </span>
                </div>
              ) : myDocumentReview?.status === 'pending' ? (
                <div className="flex items-center gap-2 px-4 py-1.5 rounded-full bg-ui-body dark:bg-ui-dark-border border border-ui-border dark:border-ui-dark-border">
                  <span className="text-text-muted dark:text-text-dark-muted text-xs font-black uppercase tracking-widest">
                    Esperando etapas anteriores
                  </span>
                </div>
              ) : (
                <div className="flex items-center gap-2 px-4 py-1.5 rounded-full bg-ui-body dark:bg-ui-dark-border border border-ui-border dark:border-ui-dark-border">
                  <span className="text-text-muted dark:text-text-dark-muted text-xs font-black uppercase tracking-widest">
                    Vista de seguimiento
                  </span>
                </div>
              )}
            </div>
          }
          sidebar={
            validateActiveView && validateSelectedBlock
                ? (
                    validateActiveView.mode === 'comments' ? (
                      <BlockCommentsCard
                        mode="validator"
                        blockSortOrder={(validateBlocks.indexOf(validateSelectedBlock) + 1) || '?'}
                        blockComments={validateBlockComments}
                        allComments={validateComments}
                        onSendMessage={handleValidateSendMessage}
                        commentLoading={validateCommentLoading}
                        canAddComments={isDocumentReviewer && !isPublished}
                        headerRef={validateViewHeaderRef}
                        onClose={() => setValidateActiveView(null)}
                      />
                    ) : (
                      <div className="bg-ui-card dark:bg-ui-dark-card shadow-xl rounded-xl flex flex-col overflow-hidden h-full animate-in fade-in slide-in-from-right-4 duration-300">
                        <ViewCardHeader
                          blockSortOrder={(validateBlocks.indexOf(validateSelectedBlock) + 1) || '?'}
                          title="Descripción del Bloque"
                          onClose={() => setValidateActiveView(null)}
                          headerRef={validateViewHeaderRef}
                        />
                        <div className="flex-1 overflow-y-auto" style={{ padding: '40px 60px' }}>
                          {validateSelectedBlock.description ? (
                            <BlockContentHtml content={normalizeBlockContentForEditor(validateSelectedBlock.description)} />
                          ) : (
                            <p className="text-sm text-text-muted italic">Este bloque no tiene descripción.</p>
                          )}
                        </div>
                      </div>
                    )
                  )
              : historyBlockId !== null && historyDocBlockId
                ? (
                    <DocumentBlockHistoryPanel
                      blockId={historyDocBlockId}
                      blockNumber={historyBlockNumber}
                      history={detail?.review_history ?? []}
                      onClose={() => setHistoryBlockId(null)}
                    />
                  )
                : diffBlockId !== null
                  ? <DocumentDiffPanel blocks={diffPanelBlocks} onClose={() => setDiffBlockId(null)} />
                  : undefined
          }
        >
          {validationSetupError && !validationReviewLoading && !myDocumentReview?.status && (
            <div className="p-3 mb-4 rounded-lg border border-danger/30 bg-danger/5 text-xs text-danger-dark font-bold">
              ⚠ {validationSetupError}
            </div>
          )}
          {validationReviewLoading && (
            <div className="p-3 mb-4 rounded-lg border border-ui-border dark:border-ui-dark-border text-xs text-text-muted">
              Cargando datos de validación…
            </div>
          )}
          {(error && !loading) && (
            <div className="p-3 mb-4 rounded-lg border border-danger/30 bg-danger/5 text-xs text-danger-dark font-bold">
              ⚠ {error}
            </div>
          )}
          {validateCommentError && (
            <div className="p-3 mb-4 rounded-lg border border-danger/30 bg-danger/5 text-xs text-danger-dark font-bold">
              ⚠ {validateCommentError}
            </div>
          )}

          {loading && (
            <p className="text-sm text-text-muted dark:text-text-dark-muted">Cargando documento…</p>
          )}
          {!loading && !error && detail && (
            <>
              <header className="mb-12 border-b border-ui-border dark:border-ui-dark-border pb-8">
                <h1 className="text-3xl font-black text-text-primary dark:text-text-dark-primary mb-4 leading-tight">
                  {previewTitle}
                </h1>
              </header>
              {validateBlocks.length === 0 ? (
                <div className="py-20 text-center border-2 border-dashed border-ui-border dark:border-ui-dark-border rounded-xl">
                  <p className="text-sm text-text-muted italic">Este documento no tiene bloques.</p>
                </div>
              ) : (
                <div className="space-y-12">
                  {validateBlocks.map((block) => {
                    const blockId = block.template_block_id;
                    const isSelected = validateActiveView?.blockId === blockId;
                    const commentsActive = isSelected && validateActiveView?.mode === 'comments';
                    const infoActive = isSelected && validateActiveView?.mode === 'info';
                    const nodes = normalizeBlockContentForEditor(block.content ?? block.default_content);
                    const hasDescription = !!block.description;
                    const btnBase = 'shrink-0 px-3 py-1.5 rounded-full border flex items-center gap-1.5 transition-all cursor-pointer text-xs font-black uppercase tracking-wider';
                    const btnActive = 'border-odoo-purple text-odoo-purple bg-odoo-purple/10 shadow-sm';
                    const btnIdle = 'border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5';

                    return (
                      <section
                        key={blockId}
                        ref={(el) => {
                          if (el) validateBlockRefs.current.set(blockId, el);
                          else validateBlockRefs.current.delete(blockId);
                        }}
                        onClick={(e) => { e.stopPropagation(); if (block.document_block_id) openValidateView(blockId, 'comments'); }}
                        className={[
                          'relative group rounded-lg transition-all duration-200',
                          block.document_block_id || hasDescription ? 'cursor-pointer' : '',
                          isSelected
                            ? 'ring-2 ring-odoo-purple ring-offset-8 dark:ring-offset-ui-dark-card shadow-sm'
                            : (block.document_block_id || hasDescription) ? 'hover:ring-1 hover:ring-ui-border dark:hover:ring-ui-dark-border hover:ring-offset-4 dark:hover:ring-offset-ui-dark-card' : '',
                        ].join(' ')}
                      >
                        <div className={['absolute -left-12 top-0 text-xs font-black uppercase tracking-tighter transition-opacity duration-200', isSelected ? 'opacity-100 text-odoo-purple' : 'opacity-0 group-hover:opacity-40 text-text-muted'].join(' ')}>
                          #{block.sort_order ?? '?'}
                        </div>

                        <div className="flex items-center gap-3 mb-4">
                          <h4 className="flex-1 min-w-0 text-xs font-black uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary opacity-60 truncate">
                            Bloque {(detail.blocks.findIndex((b) => b.template_block_id === block.template_block_id) + 1)}: {block.title ?? 'Sin título'}
                          </h4>
                          <div className="flex items-center gap-2">
                            {/* Diff button */}
                            {changedBlocks.some(b => b.template_block_id === blockId) && (
                              <button
                                type="button"
                                aria-label={`Ver cambios del bloque ${block.sort_order}`}
                                onClick={(e) => { e.stopPropagation(); setValidateActiveView(null); setHistoryBlockId(null); setDiffBlockId(prev => prev === blockId ? null : blockId); }}
                                className={[btnBase, diffBlockId === blockId ? btnActive : btnIdle].join(' ')}
                                title="Ver cambios de este bloque"
                              >
                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                  <path strokeLinecap="round" strokeLinejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                                <span>Ver cambios</span>
                              </button>
                            )}
                            {/* History button */}
                            {hasReviewHistory && !!block.document_block_id && (
                              <button
                                type="button"
                                aria-label={`Ver historial de cambios del bloque ${block.sort_order}`}
                                onClick={(e) => { e.stopPropagation(); setValidateActiveView(null); setDiffBlockId(null); setHistoryBlockId(prev => prev === blockId ? null : blockId); }}
                                className={[btnBase, historyBlockId === blockId ? btnActive : btnIdle].join(' ')}
                                title="Ver historial de cambios de este bloque"
                              >
                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                  <path strokeLinecap="round" strokeLinejoin="round" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4" />
                                </svg>
                                <span>Historial</span>
                              </button>
                            )}
                            {/* Info button */}
                            {hasDescription && (
                              <button
                                type="button"
                                aria-label={`Ver descripción del bloque ${block.sort_order}`}
                                onClick={(e) => { e.stopPropagation(); openValidateView(blockId, 'info'); }}
                                className={[
                                  'shrink-0 px-3 py-1.5 rounded-full border flex items-center gap-1.5 transition-all cursor-pointer text-xs font-black uppercase tracking-wider',
                                  infoActive ? 'border-odoo-purple text-odoo-purple bg-odoo-purple/10 shadow-sm' : 'border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5'
                                ].join(' ')}
                                title="Ver descripción del bloque"
                              >
                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                  <path strokeLinecap="round" strokeLinejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Info</span>
                              </button>
                            )}
                            {/* Messages button */}
                            <button
                              type="button"
                              aria-label={`Ver comentarios del bloque ${block.sort_order}`}
                              disabled={!block.document_block_id}
                              title={!block.document_block_id ? 'Los bloques bloqueados no admiten comentarios' : undefined}
                              onClick={(e) => { e.stopPropagation(); if (block.document_block_id) openValidateView(blockId, 'comments'); }}
                              className={[
                                'shrink-0 px-3 py-1.5 rounded-full border flex items-center gap-1.5 transition-all cursor-pointer text-xs font-black uppercase tracking-wider',
                                !block.document_block_id ? 'cursor-not-allowed opacity-40 border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/30' : commentsActive ? 'border-odoo-purple text-odoo-purple bg-odoo-purple/10 shadow-sm' : 'border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5',
                              ].join(' ')}
                            >
                              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                              </svg>
                              <span>Mensajes</span>
                              {getCommentsForBlock(block.document_block_id, validateComments).length > 0 && (
                                <span className="ml-1 bg-odoo-purple text-white px-1.5 py-0.5 rounded-full text-[10px] leading-none font-bold">
                                  {getCommentsForBlock(block.document_block_id, validateComments).length}
                                </span>
                              )}
                            </button>
                          </div>
                        </div>

                        <div>
                          {nodes.length > 0 ? <BlockContentHtml content={nodes} /> : <p className="text-xs text-text-muted italic">Sin contenido.</p>}
                        </div>
                      </section>
                    );
                  })}
                </div>
              )}
            </>
          )}
        </PaperPreviewLayout>

        <ConfirmDialog
          open={validateConfirm === 'approve'}
          title="Confirmar aprobación"
          description="Se registrará tu aprobación. Si eres el último validador pendiente, el documento pasará a publicado."
          confirmLabel="Aprobar"
          error={validationModalError}
          loading={validationActionLoading}
          onCancel={() => { setValidateConfirm(null); setValidationModalError(null); }}
          onConfirm={() => void handleApproveValidation()}
        />
        <ConfirmDialog
          open={validateConfirm === 'reject'}
          title={validatorHasCommented ? 'Confirmar rechazo' : 'Comentario requerido'}
          description={
            validatorHasCommented ? (
              <p className="text-sm text-text-secondary dark:text-text-dark-secondary">
                El documento volverá a borrador para que el titular pueda corregirlo.
                Tus comentarios en los bloques quedarán registrados como motivo del rechazo.
              </p>
            ) : (
              <p className="text-sm text-text-secondary dark:text-text-dark-secondary">
                Para rechazar la validación debes dejar al menos un comentario en un bloque del documento explicando
                el motivo del rechazo. El comentario queda registrado para el titular.
              </p>
            )
          }
          confirmLabel={validatorHasCommented ? 'Rechazar' : 'Entendido'}
          variant={validatorHasCommented ? 'danger' : 'primary'}
          error={validationModalError}
          loading={validationActionLoading}
          onCancel={() => { setValidateConfirm(null); setValidationModalError(null); }}
          onConfirm={validatorHasCommented
            ? () => void handleRejectValidation()
            : () => { setValidateConfirm(null); setValidationModalError(null); }}
        />
      </>
    );
  }

  return (
    <>
      <PaperPreviewLayout
        title={previewTitle}
        onBack={handleBack}
        backLabel={backLabel}
        metaInfo={headerMetaInfo}
        actions={headerActions}
        headerRef={pageHeaderRef}
        sidebar={
          diffBlockId !== null && !selectedReviewView
            ? <DocumentDiffPanel blocks={diffPanelBlocks} onClose={() => setDiffBlockId(null)} />
            : selectedReviewView && (() => {
          const block = detail?.blocks?.find(b => (b.document_block_id || b.template_block_id) === selectedReviewView.blockId);
          if (!block) return null;

          const isCreator = profile?.id && detail?.created_by === profile.id;
          const isOwner = profile?.id && detail?.owner_id === profile.id;
          const commentMode = (isDocumentReviewer && detail?.status === 'in_review') ? 'validator' : (isCreator || isOwner) ? 'creator-edit' : 'creator-readonly';

          if (selectedReviewView.mode === 'comments') {
            return (
              <BlockCommentsCard
                mode={commentMode}
                blockSortOrder={((detail?.blocks?.indexOf(block) ?? -1) + 1) || '?'}
                blockComments={getCommentsForBlock(block.document_block_id, reviewComments)}
                allComments={reviewComments}
                commentLoading={reviewCommentsLoading}
                onClose={() => setSelectedReviewView(null)}
                onSendMessage={handlePreviewSendMessage}
                headerRef={pageHeaderRef}
                canAddComments={!isHistoricalSnapshot && !isPublished}
              />
            );
          }
          return (
            <div className="bg-ui-card dark:bg-ui-dark-card shadow-xl rounded-xl flex flex-col overflow-hidden h-full animate-in fade-in slide-in-from-right-4 duration-300">
              <ViewCardHeader
                blockSortOrder={((detail?.blocks?.indexOf(block) ?? -1) + 1) || '?'}
                title="Descripción del Bloque"
                onClose={() => setSelectedReviewView(null)}
                headerRef={pageHeaderRef}
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
        })()
        }
      >
        {loading && (
          <p className="text-sm text-text-muted dark:text-text-dark-muted">Cargando documento…</p>
        )}
        {error && !loading && (
          <p className="text-sm text-warning-dark dark:text-warning-light">{error}</p>
        )}
        {versionPreviewError && (
          <p className="text-sm text-warning-dark dark:text-warning-light mb-4">
            {versionPreviewError}{' '}
            <button
              type="button"
              className="underline font-medium"
              onClick={() => documentId && navigate(`/documents/${documentId}`, { replace: true })}
            >
              Quitar filtro de versión
            </button>
          </p>
        )}
        {actionError && (
          <p className="text-sm text-warning-dark dark:text-warning-light mb-4">{actionError}</p>
        )}
        {autoPublishBanner && (
          <p className="text-sm text-success-dark dark:text-success mb-4 font-bold">
            Documento publicado directamente (sin validadores asignados).
          </p>
        )}
        {!loading && !error && detail && (
          !isHistoricalSnapshot ? (
            // Render blocks individually to support per-block comment selection
            <>
              <h1 className="text-2xl font-black text-text-primary dark:text-text-dark-primary mb-8 leading-tight">
                {previewTitle}
              </h1>
              <div className="space-y-12">
                {detail.blocks.filter((block: DocumentDisplayBlock) => !block.is_deleted).map((block) => {
                  const blockId = block.document_block_id || block.template_block_id;
                  const isSelected = selectedReviewView?.blockId === blockId;
                  const commentsActive = isSelected && selectedReviewView?.mode === 'comments';
                  const infoActive = isSelected && selectedReviewView?.mode === 'info';
                  const hasDescription = !!block.description;
                  const nodes = blockContentForPreview(block);

                  return (
                    <section
                      key={blockId}
                      className={[
                        'relative group rounded-lg transition-all duration-200',
                        !isPublished ? 'cursor-pointer' : '',
                        isSelected
                          ? 'ring-2 ring-odoo-purple ring-offset-8 dark:ring-offset-ui-dark-card shadow-sm'
                          : !isPublished ? 'hover:ring-1 hover:ring-ui-border dark:hover:ring-ui-dark-border hover:ring-offset-4 dark:hover:ring-offset-ui-dark-card' : '',
                      ].join(' ')}
                      onClick={(e) => { e.stopPropagation(); if (block.document_block_id && !isPublished) setSelectedReviewView({ blockId, mode: 'comments' }); }}
                    >
                      <div className={['absolute -left-12 top-0 text-xs font-black uppercase tracking-tighter transition-opacity duration-200', isSelected ? 'opacity-100 text-odoo-purple' : 'opacity-0 group-hover:opacity-40 text-text-muted'].join(' ')}>
                        #{block.sort_order ?? '?'}
                      </div>

                      <div className="flex items-center gap-3 mb-4">
                        <h4 className="flex-1 min-w-0 text-xs font-black uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary opacity-60 truncate">
                          Bloque {(detail.blocks.findIndex((b) => b.template_block_id === block.template_block_id) + 1)}: {block.title ?? 'Sin título'}
                        </h4>
                        <div className="flex items-center gap-2">
                          {changedBlocks.some(b => b.template_block_id === block.template_block_id) && (
                            <button
                              type="button"
                              onClick={(e) => { e.stopPropagation(); setSelectedReviewView(null); setDiffBlockId(prev => prev === block.template_block_id ? null : block.template_block_id); }}
                              className={[
                                'shrink-0 px-3 py-1.5 rounded-full border flex items-center gap-1.5 transition-all cursor-pointer text-xs font-black uppercase tracking-wider',
                                diffBlockId === block.template_block_id ? 'border-odoo-purple text-odoo-purple bg-odoo-purple/10 shadow-sm' : 'border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5'
                              ].join(' ')}
                              title="Ver cambios de este bloque"
                            >
                              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                              </svg>
                              <span>Ver cambios</span>
                            </button>
                          )}
                          {hasDescription && (
                            <button
                              type="button"
                              onClick={(e) => { e.stopPropagation(); setSelectedReviewView({ blockId, mode: 'info' }); }}
                              className={[
                                'shrink-0 px-3 py-1.5 rounded-full border flex items-center gap-1.5 transition-all cursor-pointer text-xs font-black uppercase tracking-wider',
                                infoActive ? 'border-odoo-purple text-odoo-purple bg-odoo-purple/10 shadow-sm' : 'border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5'
                              ].join(' ')}
                              title="Ver descripción del bloque"
                            >
                              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                              </svg>
                              <span>Info</span>
                            </button>
                          )}
                          {!isPublished && <button
                            type="button"
                            disabled={!block.document_block_id}
                            onClick={(e) => { e.stopPropagation(); if (block.document_block_id) setSelectedReviewView({ blockId, mode: 'comments' }); }}
                            className={[
                              'shrink-0 px-3 py-1.5 rounded-full border flex items-center gap-1.5 transition-all cursor-pointer text-xs font-black uppercase tracking-wider',
                              !block.document_block_id ? 'cursor-not-allowed opacity-40 border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/30' : commentsActive ? 'border-odoo-purple text-odoo-purple bg-odoo-purple/10 shadow-sm' : 'border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5'
                            ].join(' ')}
                          >
                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                              <path strokeLinecap="round" strokeLinejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                            <span>Mensajes</span>
                            {getCommentsForBlock(block.document_block_id, reviewComments).length > 0 && (
                              <span className="ml-1 bg-odoo-purple text-white px-1.5 py-0.5 rounded-full text-[10px] leading-none font-bold">
                                {getCommentsForBlock(block.document_block_id, reviewComments).length}
                              </span>
                            )}
                          </button>}
                        </div>
                      </div>
                      <div>
                        {nodes.length > 0 ? <BlockContentHtml content={nodes} /> : <p className="text-xs text-text-muted italic">Sin contenido.</p>}
                      </div>
                    </section>
                  );
                })}
              </div>
            </>
          ) : (
            <PaperBlocksArticle
              title={previewTitle}
              blocks={articleBlocks}
              emptyMessage="Este documento no tiene bloques."
            />
          )
        )}
      </PaperPreviewLayout>


      {documentId && (
        <VersionHistoryPanel
          open={showHistory}
          entityType="document"
          entityId={documentId}
          onClose={() => setShowHistory(false)}
        />
      )}

      <ConfirmDialog
        open={showDeleteModal}
        variant="danger"
        title="¿Eliminar este documento?"
        description="Estás a punto de eliminar este elemento. Esta acción es irreversible y no se puede deshacer."
        confirmLabel="Eliminar"
        cancelLabel="Cancelar"
        loading={deleteLoading}
        error={deleteError}
        onConfirm={() => void handleDelete()}
        onCancel={() => { setShowDeleteModal(false); setDeleteError(null); }}
      />

      <ConfirmDialog
        open={showNewVersionConfirm}
        title="¿Crear nueva versión?"
        description="Se creará un nuevo borrador editable a partir del documento publicado actual. Podrás modificarlo y volver a enviarlo a validar."
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
        title="Ya existe una versión en borrador"
        icon="🔒"
        description={draftBlockedBy ?? ''}
        confirmLabel="Entendido"
        onConfirm={() => setDraftBlockedBy(null)}
        onCancel={() => setDraftBlockedBy(null)}
      />

      <ConfirmDialog
        open={showDiscardVersionModal}
        variant="danger"
        title="¿Descartar nueva versión?"
        description={
          <div className="space-y-2 text-left">
            <p className="text-sm text-text-secondary dark:text-text-dark-secondary">
              Se descartarán los cambios en borrador/en revisión y se restaurará la última versión publicada del documento.
            </p>
            {detail?.status === 'in_review' && (
              <p className="text-sm font-bold text-warning-dark dark:text-warning-light">
                ⚠ Esta versión está en revisión. Los validadores asignados perderán su trabajo si la descartas.
              </p>
            )}
          </div>
        }
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
