import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { Link, useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useBackNavigation } from '@ceedcv-maya/shared-hooks-react';
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
  fetchTemplateVersionStatus,
  discardDocumentWorkingVersion,
  type DocumentReview,
} from '../api/documents';
import { fetchTemplate } from '../api/templates';
import { useDocumentCommentsQuery, documentCommentsKey } from '../features/documents/hooks/useDocumentComments';
import {
  DOCUMENT_STATUS_LABELS,
  effectiveDocumentReviewMode,
  pickActionableDocumentReview,
  validationSuccessBannerMessage,
} from '../features/documents/components/documentWizardUtils';
import { useTemplateQuery } from '../features/templates/hooks/useTemplate';
import { useProcessesQuery } from '../hooks/useProcesses';
import { normalizeBlockContentForEditor } from '../features/documents/lib/normalizeBlockContent';
import { mapSnapshotDocumentBlocks } from '../features/documents/lib/mapSnapshotBlocks';
import type { DocumentDetail, DocumentDisplayBlock } from '../types/documents';
import { visibilityLabel } from '../features/templates/constants';
import { Button, ConfirmDialog, statusBadgeClass } from '@ceedcv-maya/shared-ui-react';
import { SubmissionChangelogReadonly, VersionChangelogModal } from '../components/VersionChangelogModal';
import { FavoriteButton } from '../components/FavoriteButton';
import { VersionHistoryPanel } from '../components/VersionHistoryPanel';
import { refreshDmsDashboardQuery } from '../features/dashboard/hooks/useDmsDashboard';
import { useUserProfile } from '../features/user-profile';
import { canCommentOnDocument, canCreateBlockComment, canDeleteBlockComment, DMS_PERMISSIONS } from '../permissions';
import { useDocumentVersionSummariesQuery } from '../features/documents/hooks/useDocumentVersionSummaries';
import {
  canDeleteUnpublishedEntity,
  canShowVersionHistoryButton,
  isDiscardWorkingVersionAllowed,
} from '../utils/versionableEntityActions';
import { useNewVersionFlow } from '../features/versioning/hooks/useNewVersionFlow';
import { PaperPreviewLayout } from '../features/documents/components/PaperPreviewLayout';
import { PagedThemedPreview } from '../features/documents/components/PagedThemedPreview';
import { useDocumentPdfExport } from '../features/documents/hooks/useDocumentPdfExport';
import { PaperBlocksArticle, type PaperArticleBlock } from '../features/documents/components/PaperBlocksArticle';
import { BlockCommentsCard, ViewCardHeader } from '../features/templates/components/BlockCommentsCard';
import type { BlockComment } from '../features/templates/components/BlockCommentsCard';
import { BlockContentHtml } from '../features/templates/components/BlockContentHtml';
import { StructuralBlockPreview } from '../features/documents/components/StructuralBlockPreview';
import { computeChangedBlocks } from '../features/documents/components/DocumentDiffModal';
import { listUnresolvedEditableBlockTitles } from '../features/documents/lib/blockContentEquals';
import { BlockChangesPanel } from '../features/documents/components/BlockChangesPanel';
import { apiFetchJson } from '../api/http';
import type { Process } from '../types/processes';
import { formatCalendarDateForBrowser } from '../utils/formatCalendarDate';
import { getCommentsForBlock, countUnreadCommentsForBlock, resolveCommentBlockableId } from '../utils/blockComments';
import { addCommentToCache, markCommentAsReadInDocumentCache, markCommentDeletedInDocumentCache, markBlockCommentsAsReadInDocumentCache, patchDocumentCommentCache } from '../features/comments/commentCache';
import { SequentialValidatorBadge } from '../features/documents/components/SequentialValidatorBadge';

// Estado: clases en `statusBadgeClass` (módulo `@ceedcv-maya/shared-ui-react/badges`);
// etiquetas en `DOCUMENT_STATUS_LABELS` (documentWizardUtils).

function blockContentForPreview(block: DocumentDisplayBlock): unknown[] {
  const fromContent = normalizeBlockContentForEditor(block.content);
  if (fromContent.length > 0) return fromContent;
  return normalizeBlockContentForEditor(block.default_content);
}

/** Los bloques estructurales (portada, índice, hoja en blanco) no llevan cuerpo
 *  Tiptap: se previsualizan con `StructuralBlockPreview`, no con el editor. */
function isStructuralBlock(block: DocumentDisplayBlock): boolean {
  const t = block.block_type ?? 'content';
  return t === 'cover' || t === 'index' || t === 'blank';
}

function snapshotDocumentTitle(snapshotData: Record<string, unknown>): string | undefined {
  const doc = snapshotData.document;
  if (!doc || typeof doc !== 'object') return undefined;
  const t = (doc as Record<string, unknown>).title;
  return typeof t === 'string' ? t : undefined;
}

type Props = {
  mode?: 'preview' | 'validate';
};

export function DocumentPreviewPage({ mode = 'preview' }: Props = {}) {
  const { t } = useTranslation('documents');
  const { documentId } = useParams<{ documentId: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [searchParams] = useSearchParams();
  const documentVersionId = searchParams.get('documentVersionId');
  const location = useLocation();
  const { profile, hasPermission } = useUserProfile();
  const isValidateMode = mode === 'validate';
  const locationState = location.state as { moduleId?: string; processId?: string } | null;
  const selectedProcessId = locationState?.processId;
  const [detail, setDetail] = useState<DocumentDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  /**
   * `edit` = vista actual con comentarios/diffs/history por bloque.
   * `themed` = iframe themed paginado en hojas A4 (paged.js).
   */
  const [viewMode, setViewMode] = useState<'edit' | 'themed'>('edit');
  const [diffBlockId, setDiffBlockId] = useState<string | null>(null);
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
  const [autoPublishBanner, setAutoPublishBanner] = useState(false);
  const [validationReviewLoading, setValidationReviewLoading] = useState(false);
  const [validationSetupError, setValidationSetupError] = useState<string | null>(null);
  const [actionableReviewId, setActionableReviewId] = useState<string | null>(null);
  const [allReviews, setAllReviews] = useState<DocumentReview[]>([]);
  const [validateConfirm, setValidateConfirm] = useState<null | 'approve' | 'reject'>(null);
  const [validationActionLoading, setValidationActionLoading] = useState(false);
  const [validationModalError, setValidationModalError] = useState<string | null>(null);
  const [showChangelogModal, setShowChangelogModal] = useState(false);
  const [changelogModalError, setChangelogModalError] = useState<string | null>(null);
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
  const [previewCommentSubmitError, setPreviewCommentSubmitError] = useState<string | null>(null);
  const [selectedReviewView, setSelectedReviewView] = useState<{ blockId: string; mode: 'comments' | 'info' } | null>(null);
  const pageHeaderRef = useRef<HTMLDivElement>(null);


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
    backTo?: string | string[];
    forceBackTo?: boolean;
  } | null;
  const cameFromSummary = previewState?.returnToStep === 'summary';
  const cameFromValidate = previewState?.returnToValidate === true;

  const backLabel = cameFromSummary
    ? cameFromValidate
      ? t('common:navigation.backToValidate')
      : t('common:navigation.backToSummary')
    : t('common:actions.back');

  const { goBack, backTarget, hasBackState } = useBackNavigation({
    fallback: selectedProcessId ? `/processes/${selectedProcessId}` : '/processes',
  });

  const handleBack = () => {
    if (hasBackState) {
      goBack();
      return;
    }
    // Sin pila de retorno (acceso directo): destino canónico con la pestaña activa.
    navigate(selectedProcessId ? `/processes/${selectedProcessId}` : '/processes', {
      state: { tab: 'documents' },
    });
  };

  const isDraft = detail?.status === 'draft' || detail?.status === 'rejected';
  const isPublished = detail?.status === 'published';
  /**
   * Versión mostrada en pantalla, para excluirla del Historial (que lista solo
   * anteriores): el snapshot si se está viendo uno; la última publicada si el
   * documento está publicado; ninguna con borrador en pantalla.
   */
  const displayedVersionId =
    documentVersionId ?? (isPublished ? detail?.latest_published_version_id ?? null : null);
  // Hook que orquesta el flujo de descarga PDF/UA (POST → poll → blob → save-as).
  const pdfExport = useDocumentPdfExport(documentId, detail?.title, documentVersionId ?? undefined);
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
  // Datos del panel unificado «Ver cambios» (pestañas Cambiado / Histórico) para el bloque activo.
  const reviewHistory = useMemo(() => detail?.review_history ?? [], [detail]);
  const hasReviewHistory = reviewHistory.length > 0;
  const changesBlock = useMemo(
    () => (diffBlockId ? (detail?.blocks?.find((b) => b.template_block_id === diffBlockId) ?? null) : null),
    [diffBlockId, detail],
  );
  const changesDocBlockId = changesBlock?.document_block_id ?? null;
  const changesBlockNumber = changesBlock ? ((detail?.blocks?.indexOf(changesBlock) ?? -1) + 1) || '?' : '?';
  const changesHasDiff = !!diffBlockId && changedBlocks.some((b) => b.template_block_id === diffBlockId);
  const changesHasHistory = hasReviewHistory && !!changesDocBlockId;
  const isOwner = profile?.id === detail?.owner_id || profile?.id === detail?.created_by;
  const uid = profile?.id;
  /** Paridad con `DocumentPolicy::update`. */
  const canUpdate =
    !!detail &&
    !!uid &&
    (detail.owner_id === uid ||
      detail.created_by === uid ||
      detail.share_permission === 'edit' ||
      hasPermission(DMS_PERMISSIONS.documentUpdate));
  const canMutatePublished = canUpdate;
  const canReviewDocument = hasPermission(DMS_PERMISSIONS.documentReview);
  const isHistoricalSnapshot = versionSnapshot !== null;
  /** Paridad con plantillas: eliminar solo si nunca hubo versión publicada. */
  const canDelete =
    !!detail &&
    !isHistoricalSnapshot &&
    canDeleteUnpublishedEntity(
      detail.latest_published_version_id,
      isOwner || hasPermission(DMS_PERMISSIONS.documentDelete),
    );
  const canEditDraft = isDraft && canUpdate;
  const versionHistoryGatesOpen = !isValidateMode && !isHistoricalSnapshot;
  const canCreateNewVersion =
    versionHistoryGatesOpen && detail?.can_create_new_version === true;
  const versionSummariesQuery = useDocumentVersionSummariesQuery(documentId ?? '', {
    enabled:
      !!documentId
      && detail?.can_view_history === true
      && !canCreateNewVersion,
  });
  const publishedVersionCount = versionSummariesQuery.data?.length ?? null;
  const showVersionHistory = canShowVersionHistoryButton(
    detail,
    publishedVersionCount,
    canCreateNewVersion,
  );
  const canClone =
    !isValidateMode &&
    detail?.can_clone === true;
  const canDiscardWorkingVersion =
    !isValidateMode &&
    !isHistoricalSnapshot &&
    canMutatePublished &&
    isDiscardWorkingVersionAllowed(
      detail?.latest_published_version_id,
      detail?.working_version_id,
      detail?.status,
      ['draft', 'in_review', 'rejected'],
    );
  const newVersionFlow = useNewVersionFlow({
    t,
    entity: detail,
    entityId: documentId,
    gatesOpen: !isValidateMode && !isHistoricalSnapshot,
    startNewVersion: startDocumentNewVersion,
    onSuccess: async (result) => {
      const data = result as Awaited<ReturnType<typeof startDocumentNewVersion>>;
      setDetail(data);

      let hasUpdate = false;
      try {
        const status = await fetchTemplateVersionStatus(documentId!);
        hasUpdate = status.has_update === true;
      } catch {
        hasUpdate = false;
      }

      navigate(`/documents/${documentId}/editor`, {
        state: hasUpdate ? { step: 'properties', migrationMode: 'upgrade' } : { step: 'properties' },
      });
    },
  });

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

    // En modo validación el usuario sale del perfil compartido (useUserProfile);
    // si aún no se ha resuelto, el efecto se relanza cuando llegue (dep `uid`).
    if (isValidateMode && !uid) return;

    let cancelled = false;
    if (isValidateMode) {
      setValidationReviewLoading(true);
      setValidationSetupError(null);
      setActionableReviewId(null);
    }

    void (async () => {
      try {
        const reviews = await fetchDocumentReviews(detail.id);
        if (cancelled) return;
        setAllReviews(reviews);
        if (isValidateMode && uid) {
          // Mismo origen que el wizard: el modo efectivo sale de la plantilla
          // (document_review_mode ?? review_mode), no del review_mode pelado.
          const templateResp = await fetchTemplate(detail.template_id);
          if (cancelled) return;
          const reviewMode = effectiveDocumentReviewMode(templateResp);
          const actionable = pickActionableDocumentReview(reviews, uid, reviewMode);
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
  }, [isValidateMode, detail?.id, detail?.status, detail?.template_id, uid]);

  const templateForLabelQuery = useTemplateQuery(detail?.template_id ?? '', {
    enabled: !!detail?.template_id,
  });
  const processesQuery = useProcessesQuery(undefined, {
    enabled: !!detail?.template_id,
  });
  const processLabel = useMemo<string | null>(() => {
    const processId = templateForLabelQuery.data?.process_id;
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
  const validateCommentError = validateCommentLoadError;

  const openValidateView = (blockId: string, mode: 'comments' | 'info') => {
    setValidateActiveView((prev) =>
      prev?.blockId === blockId && prev?.mode === mode ? null : { blockId, mode },
    );
  };

  const handlePreviewSendMessage = async (parentId: string | null, body: string) => {
    if (!documentId || !selectedReviewView?.blockId) return;
    setPreviewCommentSubmitError(null);
    setReviewCommentsLoading(true);
    try {
      const block = detail?.blocks?.find(
        (b) => (b.document_block_id || b.template_block_id) === selectedReviewView.blockId,
      );
      const blockableId = resolveCommentBlockableId(
        parentId,
        reviewComments,
        block?.document_block_id ?? null,
      );
      const res = await apiFetchJson<{ data: BlockComment }>(`documents/${documentId}/comments`, {
        method: 'POST',
        body: {
          body,
          parent_id: parentId,
          blockable_id: blockableId,
        },
      });
      addCommentToCache(queryClient, documentCommentsKey(documentId), res.data);
    } catch {
      setPreviewCommentSubmitError('No se pudo guardar el comentario.');
      throw new Error('comment-send-failed');
    } finally {
      setReviewCommentsLoading(false);
    }
  };

  const handleEditComment = async (commentId: string, newBody: string) => {
    if (!documentId) return;
    const res = await apiFetchJson<{ data: BlockComment }>(`comments/${commentId}`, {
      method: 'PATCH',
      body: { body: newBody },
    });
    patchDocumentCommentCache(queryClient, documentId, (comments) =>
      comments.map((c) => (c.id === commentId ? res.data : c)),
    );
  };

  const handleDeleteComment = async (commentId: string) => {
    if (!documentId) return;
    await apiFetchJson(`comments/${commentId}`, { method: 'DELETE' });
    markCommentDeletedInDocumentCache(queryClient, documentId, commentId, profile?.name);
  };

  const handleMarkCommentAsRead = async (commentId: string) => {
    if (!documentId) return;
    await markCommentAsReadInDocumentCache(queryClient, documentId, commentId);
  };

  const handleMarkAllValidateBlockCommentsAsRead = async () => {
    if (!documentId || !validateActiveBlockId) return;
    const block = detail?.blocks?.find((b) => b.template_block_id === validateActiveBlockId);
    if (!block?.document_block_id) return;
    await markBlockCommentsAsReadInDocumentCache(
      queryClient,
      documentId,
      block.document_block_id,
    );
  };

  const handleMarkAllPreviewBlockCommentsAsRead = async () => {
    if (!documentId || !selectedReviewView?.blockId) return;
    const block = detail?.blocks?.find(
      (b) => (b.document_block_id || b.template_block_id) === selectedReviewView.blockId,
    );
    if (!block?.document_block_id) return;
    await markBlockCommentsAsReadInDocumentCache(
      queryClient,
      documentId,
      block.document_block_id,
    );
  };

  const handleValidateSendMessage = async (parentId: string | null, body: string) => {
    if (!documentId) return;
    setValidateCommentLoading(true);
    setValidateCommentSubmitError(null);
    try {
      const blockableId = resolveCommentBlockableId(
        parentId,
        validateComments,
        detail?.blocks.find((b) => b.template_block_id === validateActiveBlockId)?.document_block_id ?? null,
      );
      const res = await apiFetchJson<{ data: BlockComment }>(`documents/${documentId}/comments`, {
        method: 'POST',
        body: { body, parent_id: parentId, blockable_id: blockableId },
      });
      addCommentToCache(queryClient, documentCommentsKey(documentId), res.data);
    } catch {
      setValidateCommentSubmitError('No se pudo guardar el comentario.');
      throw new Error('comment-send-failed');
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
      goBack({ replace: true });
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

  const handleOpenSubmitChangelogModal = () => {
    if (!documentId || !detail) return;

    const unresolvedEditable = listUnresolvedEditableBlockTitles(detail.blocks);
    if (unresolvedEditable.length > 0) {
      setActionError(
        `Debes rellenar todos los bloques editables antes de enviar a revisión. Pendientes: ${unresolvedEditable.join(', ')}.`,
      );
      return;
    }

    setChangelogModalError(null);
    setShowChangelogModal(true);
  };

  const handleConfirmChangelogSubmit = async (changelog: string) => {
    if (!documentId || !detail) return false;

    setActionLoading(true);
    setActionError(null);
    setChangelogModalError(null);
    try {
      const res = await submitDocumentForReview(documentId, changelog);
      if (res.status === 'published') setAutoPublishBanner(true);
      setDetail((prev) => (prev ? ({ ...prev, status: res.status, submitted_at: res.submitted_at } as typeof prev) : prev));
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
    if (!documentId) return;
    setActionLoading(true);
    setActionError(null);
    try {
      // Si la plantilla tiene una versión más nueva, continuar pasa por el wizard
      // con un paso de migración; si no, clon directo (camino rápido).
      let hasUpdate = false;
      try {
        const status = await fetchTemplateVersionStatus(documentId);
        hasUpdate = status.has_update === true;
      } catch {
        hasUpdate = false;
      }

      if (hasUpdate && detail?.template_id) {
        navigate(`/documents/new/${detail.template_id}/wizard`, {
          state: { sourceDocumentId: documentId },
        });
        return;
      }

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
      return false;
    }
    setValidationModalError(null);
    setValidationActionLoading(true);
    try {
      const updated = await approveDocumentReview(documentId, actionableReviewId, null);
      await queryClient.invalidateQueries({ queryKey: ['documents'] });
      await refreshDmsDashboardQuery(queryClient);
      setValidateConfirm(null);
      navigate(backTarget, {
        state: { documentValidationBanner: validationSuccessBannerMessage(updated, 'approve'), tab: 'documents' },
      });
    } catch (e) {
      setValidationModalError(e instanceof Error ? e.message : 'No se pudo aprobar la revisión.');
      return false;
    } finally {
      setValidationActionLoading(false);
    }
  };

  const validatorHasCommented = uid ? validateComments.some(c => c.author_id === uid) : false;

  const handleRejectValidation = async () => {
    if (!documentId || !actionableReviewId) {
      setValidationModalError('Faltan datos críticos para procesar la revisión.');
      return false;
    }
    setValidationModalError(null);
    setValidationActionLoading(true);
    try {
      const updated = await rejectDocumentReview(documentId, actionableReviewId, null);
      await queryClient.invalidateQueries({ queryKey: ['documents'] });
      await refreshDmsDashboardQuery(queryClient);
      setValidateConfirm(null);
      navigate(backTarget, {
        state: { documentValidationBanner: validationSuccessBannerMessage(updated, 'reject'), tab: 'documents' },
      });
    } catch (e) {
      setValidationModalError(e instanceof Error ? e.message : 'No se pudo rechazar la revisión.');
      return false;
    } finally {
      setValidationActionLoading(false);
    }
  };

  const viewToggle = detail && documentId && !isValidateMode ? (
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

  const headerActions = detail ? (
    <>
      {viewToggle}
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
            {DOCUMENT_STATUS_LABELS[detail.status] ?? detail.status}
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
      {!isValidateMode && !isHistoricalSnapshot && canDelete && (
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
      {!isValidateMode && !isHistoricalSnapshot && canEditDraft && documentId && (
        <Link to={`/documents/${documentId}/editor`}>
          <Button type="button" size="sm" variant="outline">
            Editar
          </Button>
        </Link>
      )}
      {!isValidateMode && !isHistoricalSnapshot && isDraft && isOwner && (
        detail.has_review_comments ? (
          <span
            title={t('preview.unresolvedCommentsBlocked')}
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
            onClick={handleOpenSubmitChangelogModal}
          >
            Enviar a validar
          </Button>
        )
      )}
      {!isValidateMode && (isPublished || isHistoricalSnapshot) && (
        <Button
          type="button"
          variant="outline"
          size="sm"
          loading={pdfExport.state === 'downloading'}
          onClick={() => void pdfExport.start()}
          title={pdfExport.error ?? (isHistoricalSnapshot ? 'Generar y descargar el PDF de esta versión' : 'Generar y descargar el PDF firmable del documento')}
        >
          {pdfExport.state === 'downloading' ? 'Descargando…' : 'Descargar PDF'}
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
            disabled={!canReviewDocument || !actionableReviewId || validationReviewLoading}
            onClick={() => {
              setValidationModalError(null);
              setValidateConfirm('reject');
            }}
            className="text-xs font-black uppercase tracking-wider hover:text-warning"
          >
            Rechazar validación
          </Button>
          <Button
            type="button"
            variant="primary"
            size="sm"
            disabled={!canReviewDocument || !actionableReviewId || validationReviewLoading}
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

  // Subtítulo del header: plantilla con su versión de creación + proceso.
  const headerSubtitle = detail ? (() => {
    const parts: string[] = [];
    if (detail.template_name) {
      const v = detail.template_version_number ? ` · v${detail.template_version_number}` : '';
      parts.push(`Plantilla: ${detail.template_name}${v}`);
    }
    if (processLabel) parts.push(processLabel);
    return parts.length > 0 ? parts.join(' · ') : null;
  })() : null;

  const headerMetaInfo = detail ? (
    <p className="text-xs text-text-muted dark:text-text-dark-muted text-center">
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
  // Los bloques estructurales (portada, índice, hoja en blanco) no llevan cuerpo
  // Tiptap: se pintan vía el hook `renderBlockBody` de PaperBlocksArticle.
  //
  // Los snapshots de versión guardan solo `content` por bloque (no `block_type`
  // ni la geometría `default_content`, que viven en la plantilla), así que
  // enriquecemos cada bloque con esos campos desde `detail.blocks` (datos en
  // vivo, que el backend ya mezcla con la plantilla) emparejando por plantilla.
  const detailByTemplateId = new Map((detail?.blocks ?? []).map((b) => [b.template_block_id, b]));
  const resolveStructural = (b: DocumentDisplayBlock): DocumentDisplayBlock => {
    const ref = detailByTemplateId.get(b.template_block_id);
    if (!ref) return b;
    return {
      ...b,
      block_type: b.block_type ?? ref.block_type,
      default_content: b.default_content ?? ref.default_content,
    };
  };
  const structuralByKey = new Map(blocksForArticle.map((b) => [b.template_block_id, b]));
  const structuralAllBlocks = blocksForArticle.map(resolveStructural);
  const renderStructuralBody = (ab: PaperArticleBlock) => {
    const orig = structuralByKey.get(ab.id);
    if (!orig) return undefined;
    const resolved = resolveStructural(orig);
    if (isStructuralBlock(resolved)) {
      return <StructuralBlockPreview block={resolved} allBlocks={structuralAllBlocks} />;
    }
    return undefined;
  };

  if (isValidateMode) {
    const validateBlocks = detail?.blocks ?? [];
    const validateSelectedBlock = validateBlocks.find(b => b.template_block_id === validateActiveBlockId);
    const validateBlockComments = validateSelectedBlock
      ? getCommentsForBlock(validateSelectedBlock.document_block_id, validateComments)
      : [];
    return (
      <>
        <PaperPreviewLayout
          title={t('validateTitle')}
          subtitle={headerSubtitle}
          onBack={handleBack}
          backLabel={t('common:actions.back')}
          viewMode={viewMode}
          metaInfo={
            <div className="flex flex-col items-center">
              <p className="text-xs text-text-muted uppercase tracking-widest font-black truncate max-w-[320px]">
                {detail?.title ?? 'Documento'}
              </p>
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
                    className="text-xs font-black uppercase tracking-wider hover:text-warning">
                  
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
                        submitError={validateCommentSubmitError}
                        canAddComments={isDocumentReviewer && canCommentOnDocument(detail?.status)}
                        headerRef={validateViewHeaderRef}
                        onClose={() => setValidateActiveView(null)}
                        currentUserId={profile?.id}
                        canDeleteAnyComment={canDeleteBlockComment(hasPermission)}
                        onEditComment={handleEditComment}
                        onDeleteComment={handleDeleteComment}
                        onMarkAsRead={handleMarkCommentAsRead}
                        onMarkAllBlockAsRead={handleMarkAllValidateBlockCommentsAsRead}
                      />
                    ) : (
                      <div className="bg-ui-card dark:bg-ui-dark-card shadow-xl rounded-xl flex flex-col overflow-hidden h-full animate-in fade-in slide-in-from-right-4 duration-300">
                        <ViewCardHeader
                          blockSortOrder={(validateBlocks.indexOf(validateSelectedBlock) + 1) || '?'}
                          title={t('blockDescription')}
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
              : diffBlockId !== null
                ? (
                    <BlockChangesPanel
                      key={diffBlockId}
                      diffBlocks={diffPanelBlocks}
                      allBlocks={detail?.blocks}
                      showChangedTab={changesHasDiff}
                      historyBlockId={changesDocBlockId}
                      historyBlockNumber={changesBlockNumber}
                      reviewHistory={reviewHistory}
                      showHistoryTab={changesHasHistory}
                      onClose={() => setDiffBlockId(null)}
                    />
                  )
                : undefined
          }
        >
          {isValidateMode && !canReviewDocument && (
            <div className="p-3 mb-4 rounded-lg border border-danger/30 bg-danger/5 text-xs text-danger-dark font-bold">
              ⚠ No tienes permiso para validar documentos (document.review).
            </div>
          )}
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
              {detail.submission_changelog?.trim() ? (
                <SubmissionChangelogReadonly text={detail.submission_changelog.trim()} />
              ) : null}
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
                    const btnIdle = 'border-ui-border dark:border-ui-dark-border bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5';

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
                            {/* Unified changes button (Cambiado / Histórico tabs) */}
                            {(changedBlocks.some(b => b.template_block_id === blockId) || (hasReviewHistory && !!block.document_block_id)) && (
                              <button
                                type="button"
                                aria-label={`Ver cambios del bloque ${block.sort_order}`}
                                onClick={(e) => { e.stopPropagation(); setValidateActiveView(null); setDiffBlockId(prev => prev === blockId ? null : blockId); }}
                                className={[btnBase, diffBlockId === blockId ? btnActive : btnIdle].join(' ')}
                                title={t('preview.viewBlockChangesTitle')}
                              >
                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                  <path strokeLinecap="round" strokeLinejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                                <span>Ver cambios</span>
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
                                  infoActive ? 'border-odoo-purple text-odoo-purple bg-odoo-purple/10 shadow-sm' : 'border-ui-border dark:border-ui-dark-border bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5'
                                ].join(' ')}
                                title={t('preview.viewBlockDescriptionTitle')}
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
                                !block.document_block_id ? 'cursor-not-allowed opacity-40 border-ui-border dark:border-ui-dark-border bg-ui-body/30' : commentsActive ? 'border-odoo-purple text-odoo-purple bg-odoo-purple/10 shadow-sm' : 'border-ui-border dark:border-ui-dark-border bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5',
                              ].join(' ')}
                            >
                              <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                              </svg>
                              <span>Mensajes</span>
                              {countUnreadCommentsForBlock(block.document_block_id, validateComments) > 0 && (
                                <span className="ml-1 bg-odoo-purple text-text-inverse px-1.5 py-0.5 rounded-full text-2xs leading-none font-bold">
                                  {countUnreadCommentsForBlock(block.document_block_id, validateComments)}
                                </span>
                              )}
                            </button>
                          </div>
                        </div>

                        <div>
                          {isStructuralBlock(block)
                            ? <StructuralBlockPreview block={block} allBlocks={validateBlocks} />
                            : nodes.length > 0 ? <BlockContentHtml content={nodes} /> : <p className="text-xs text-text-muted italic">Sin contenido.</p>}
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
          title={t('approveTitle')}
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
        subtitle={headerSubtitle}
        onBack={handleBack}
        backLabel={backLabel}
        metaInfo={headerMetaInfo}
        actions={headerActions}
        headerRef={pageHeaderRef}
        viewMode={viewMode}
        sidebar={
          diffBlockId !== null && !selectedReviewView
            ? (
                <BlockChangesPanel
                  key={diffBlockId}
                  diffBlocks={diffPanelBlocks}
                  allBlocks={detail?.blocks}
                  showChangedTab={changesHasDiff}
                  historyBlockId={changesDocBlockId}
                  historyBlockNumber={changesBlockNumber}
                  reviewHistory={reviewHistory}
                  showHistoryTab={changesHasHistory}
                  onClose={() => setDiffBlockId(null)}
                />
              )
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
                submitError={previewCommentSubmitError}
                onClose={() => setSelectedReviewView(null)}
                onSendMessage={handlePreviewSendMessage}
                headerRef={pageHeaderRef}
                canAddComments={
                  !isHistoricalSnapshot &&
                  canCommentOnDocument(detail?.status) &&
                  canCreateBlockComment(hasPermission) &&
                  (isCreator || isOwner || isDocumentReviewer)
                }
                currentUserId={profile?.id}
                canDeleteAnyComment={canDeleteBlockComment(hasPermission)}
                onEditComment={handleEditComment}
                onDeleteComment={handleDeleteComment}
                onMarkAsRead={handleMarkCommentAsRead}
                onMarkAllBlockAsRead={handleMarkAllPreviewBlockCommentsAsRead}
              />
            );
          }
          return (
            <div className="bg-ui-card dark:bg-ui-dark-card shadow-xl rounded-xl flex flex-col overflow-hidden h-full animate-in fade-in slide-in-from-right-4 duration-300">
              <ViewCardHeader
                blockSortOrder={((detail?.blocks?.indexOf(block) ?? -1) + 1) || '?'}
                title={t('blockDescription')}
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
        {viewMode === 'themed' && !loading && !error && detail && documentId ? (
          <div className="h-[100vh] min-h-[600px] rounded border border-ui-border bg-white dark:border-ui-dark-border">
            <PagedThemedPreview kind="document" id={documentId} />
          </div>
        ) : null}
        {viewMode === 'edit' && !loading && !error && detail && (
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
                          {(changedBlocks.some(b => b.template_block_id === block.template_block_id) || (hasReviewHistory && !!block.document_block_id)) && (
                            <button
                              type="button"
                              onClick={(e) => { e.stopPropagation(); setSelectedReviewView(null); setDiffBlockId(prev => prev === block.template_block_id ? null : block.template_block_id); }}
                              className={[
                                'shrink-0 px-3 py-1.5 rounded-full border flex items-center gap-1.5 transition-all cursor-pointer text-xs font-black uppercase tracking-wider',
                                diffBlockId === block.template_block_id ? 'border-odoo-purple text-odoo-purple bg-odoo-purple/10 shadow-sm' : 'border-ui-border dark:border-ui-dark-border bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5'
                              ].join(' ')}
                              title={t('preview.viewBlockChangesTitle')}
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
                                infoActive ? 'border-odoo-purple text-odoo-purple bg-odoo-purple/10 shadow-sm' : 'border-ui-border dark:border-ui-dark-border bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5'
                              ].join(' ')}
                              title={t('preview.viewBlockDescriptionTitle')}
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
                              !block.document_block_id ? 'cursor-not-allowed opacity-40 border-ui-border dark:border-ui-dark-border bg-ui-body/30' : commentsActive ? 'border-odoo-purple text-odoo-purple bg-odoo-purple/10 shadow-sm' : 'border-ui-border dark:border-ui-dark-border bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5'
                            ].join(' ')}
                          >
                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                              <path strokeLinecap="round" strokeLinejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                            <span>Mensajes</span>
                            {countUnreadCommentsForBlock(block.document_block_id, reviewComments) > 0 && (
                              <span className="ml-1 bg-odoo-purple text-text-inverse px-1.5 py-0.5 rounded-full text-2xs leading-none font-bold">
                                {countUnreadCommentsForBlock(block.document_block_id, reviewComments)}
                              </span>
                            )}
                          </button>}
                        </div>
                      </div>
                      <div>
                        {isStructuralBlock(block)
                          ? <StructuralBlockPreview block={block} allBlocks={detail.blocks} />
                          : nodes.length > 0 ? <BlockContentHtml content={nodes} /> : <p className="text-xs text-text-muted italic">Sin contenido.</p>}
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
              renderBlockBody={renderStructuralBody}
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
          showNewVersionButton={newVersionFlow.showNewVersionButton}
          onNewVersion={newVersionFlow.handleRequestNewVersion}
          currentVersionId={displayedVersionId}
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
        open={newVersionFlow.showConfirm}
        title={t('preview.createNewVersionTitle')}
        description="Se creará un nuevo borrador editable a partir del documento publicado actual. Podrás modificarlo y volver a enviarlo a validar."
        confirmLabel="Crear nueva versión"
        cancelLabel="Cancelar"
        loading={newVersionFlow.confirmLoading}
        error={newVersionFlow.confirmError}
        onConfirm={() => void newVersionFlow.handleConfirmNewVersion()}
        onCancel={newVersionFlow.dismissConfirm}
      />

      <ConfirmDialog
        open={newVersionFlow.draftBlockedBy !== null}
        variant="teal"
        title={t('preview.draftAlreadyExistsTitle')}
        icon="🔒"
        description={newVersionFlow.draftBlockedBy ?? ''}
        confirmLabel="Entendido"
        onConfirm={newVersionFlow.dismissBlockedModal}
        onCancel={newVersionFlow.dismissBlockedModal}
      />

      <ConfirmDialog
        open={showDiscardVersionModal}
        variant="danger"
        title={t('preview.discardNewVersionTitle')}
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

      <VersionChangelogModal
        open={showChangelogModal}
        title={t('documents:sendForReviewTitle')}
        initialValue={detail?.submission_changelog}
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
