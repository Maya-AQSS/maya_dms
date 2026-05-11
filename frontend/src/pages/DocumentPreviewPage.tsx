import React, { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { Link, useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import {
  fetchDocument,
  fetchDocumentReviews,
  fetchDocumentVersionSummaries,
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
import { fetchTemplate, resolveComment } from '../api/templates';
import { fetchProcesses } from '../api/processes';
import { fetchMe } from '../api/users';
import { normalizeBlockContentForEditor } from '../features/documents/lib/normalizeBlockContent';
import type { BlockState } from '../types/blocks';
import type { DocumentDetail, DocumentDisplayBlock } from '../types/documents';
import { visibilityLabel } from '../features/templates/constants';
import { Button, ConfirmDialog, TextArea, statusBadgeClass } from '@maya/shared-ui-react';
import { FavoriteButton } from '../components/FavoriteButton';
import { VersionHistoryPanel } from '../components/VersionHistoryPanel';
import { useUserProfile } from '../features/user-profile';
import { PaperPreviewLayout } from '../features/documents/components/PaperPreviewLayout';
import { PaperBlocksArticle, type PaperArticleBlock } from '../features/documents/components/PaperBlocksArticle';
import { BlockCommentsCard, ViewCardHeader } from '../features/templates/components/BlockCommentsCard';
import type { BlockComment, CommentMode } from '../features/templates/components/BlockCommentsCard';
import { BlockContentHtml } from '../features/templates/components/BlockContentHtml';
import { apiFetchJson } from '../api/http';
import type { Process } from '../types/processes';

// Estado: clases en `statusBadgeClass` (módulo `@maya/shared-ui-react/badges`).

const STATUS_LABEL: Record<string, string> = {
  draft: 'Borrador',
  in_review: 'En revisión',
  published: 'Publicado',
};

function blockContentForPreview(block: DocumentDisplayBlock): unknown[] {
  const fromContent = normalizeBlockContentForEditor(block.content);
  if (fromContent.length > 0) return fromContent;
  return normalizeBlockContentForEditor(block.default_content);
}

function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—';
  return iso.slice(0, 10);
}

const DOCUMENT_REJECT_REASON_MIN_LEN = 5;

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
  const [validateConfirm, setValidateConfirm] = useState<null | 'approve' | 'reject'>(null);
  const [validationActionLoading, setValidationActionLoading] = useState(false);
  const [validationModalError, setValidationModalError] = useState<string | null>(null);
  const [rejectReason, setRejectReason] = useState('');
  const [processLabel, setProcessLabel] = useState<string | null>(null);
  const [publishedDocumentVersionCount, setPublishedDocumentVersionCount] = useState<number | null>(null);

  // Validate-mode comment + info state (mirrors TemplateReviewView)
  type ValidateActiveView = { blockId: string; mode: 'comments' | 'info' } | null;
  const [validateComments, setValidateComments] = useState<BlockComment[]>([]);
  const [validateActiveView, setValidateActiveView] = useState<ValidateActiveView>(null);
  const validateActiveBlockId = validateActiveView?.blockId ?? null;
  const [validateNewCommentBody, setValidateNewCommentBody] = useState('');
  const [validateCommentLoading, setValidateCommentLoading] = useState(false);
  const [validateCommentError, setValidateCommentError] = useState<string | null>(null);
  const [validateViewPaddingTop, setValidateViewPaddingTop] = useState(0);
  const [validateConnectorGeom, setValidateConnectorGeom] = useState<{ top: number; left: number; width: number } | null>(null);
  const validateScrollRef = useRef<HTMLDivElement>(null);
  const validateArticleRef = useRef<HTMLElement>(null);
  const validateBlockRefs = useRef<Map<string, HTMLElement>>(new Map());
  const validateViewColRef = useRef<HTMLDivElement>(null);
  const validateViewHeaderRef = useRef<HTMLDivElement>(null);

  // Creator preview-mode comment state (mirrors TemplatePreviewPage)
  const [reviewComments, setReviewComments] = useState<BlockComment[]>([]);
  const [selectedReviewBlockId, setSelectedReviewBlockId] = useState<string | null>(null);
  const [commentPanelTop, setCommentPanelTop] = useState(80);
  const pageHeaderRef = useRef<HTMLDivElement>(null);
  const commentCardHeaderRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!documentId) {
      setPublishedDocumentVersionCount(null);
      return;
    }
    let cancelled = false;
    void fetchDocumentVersionSummaries(documentId)
      .then((rows) => {
        if (!cancelled) setPublishedDocumentVersionCount(rows.length);
      })
      .catch(() => {
        if (!cancelled) setPublishedDocumentVersionCount(null);
      });
    return () => {
      cancelled = true;
    };
  }, [documentId]);

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
    if (isValidateMode) {
      if (window.history.length > 1) {
        navigate(-1);
        return;
      }
      navigate(backTo);
      return;
    }
    if (cameFromSummary && documentId) {
      if (cameFromValidate) {
        navigate(`/documents/${documentId}/validate`);
      } else {
        navigate(`/documents/${documentId}/editor`, { state: { step: 'summary' } });
      }
      return;
    }
    if (previewState?.forceBackTo) {
      navigate(backTo);
      return;
    }
    if (window.history.length > 1) {
      navigate(-1);
      return;
    }
    if (previewState?.backTo) {
      navigate(previewState.backTo);
      return;
    }
    navigate('/dashboard');
  };

  const isDraft = detail?.status === 'draft';
  const isPublished = detail?.status === 'published';
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
    (detail?.status === 'draft' || detail?.status === 'in_review') &&
    canMutatePublished &&
    !!detail?.latest_published_version_id &&
    !!detail?.working_version_id;

  useEffect(() => {
    if (!isValidateMode) {
      setValidationReviewLoading(false);
      setValidationSetupError(null);
      setActionableReviewId(null);
      return;
    }
    if (!detail || detail.status !== 'in_review') return;

    let cancelled = false;
    setValidationReviewLoading(true);
    setValidationSetupError(null);
    setActionableReviewId(null);

    void (async () => {
      try {
        const [reviews, meRes, templateResp] = await Promise.all([
          fetchDocumentReviews(detail.id),
          fetchMe(),
          fetchTemplate(detail.template_id),
        ]);
        if (cancelled) return;
        const reviewMode = templateResp.data.review_mode === 'sequential' ? 'sequential' : 'parallel';
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
      } catch (e) {
        if (!cancelled) {
          setValidationSetupError(
            e instanceof Error ? e.message : 'No se pudo cargar la información de validación.',
          );
          setActionableReviewId(null);
        }
      } finally {
        if (!cancelled) setValidationReviewLoading(false);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [isValidateMode, detail?.id, detail?.status, detail?.template_id]);

  useEffect(() => {
    if (!detail?.template_id) {
      setProcessLabel(null);
      return;
    }
    let cancelled = false;
    void (async () => {
      try {
        const [templateResp, processesResp] = await Promise.all([
          fetchTemplate(detail.template_id),
          fetchProcesses(),
        ]);
        if (cancelled) return;
        const processId = templateResp.data.process_id;
        if (!processId) {
          setProcessLabel(null);
          return;
        }
        const process = processesResp.data.find((p: Process) => p.id === processId) ?? null;
        if (!process) {
          setProcessLabel(null);
          return;
        }
        setProcessLabel(`Proceso: ${process.code} — ${process.name}`);
      } catch {
        if (!cancelled) setProcessLabel(null);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [detail?.template_id]);

  // Load document comments in validate mode
  useEffect(() => {
    if (!isValidateMode || !documentId || !detail) return;
    let cancelled = false;
    void apiFetchJson<{ data: BlockComment[] }>(`documents/${documentId}/comments`)
      .then((res) => { if (!cancelled) setValidateComments(res.data); })
      .catch((e) => {
        if (!cancelled) setValidateCommentError(e instanceof Error ? e.message : 'No se pudieron cargar los comentarios.');
      });
    return () => { cancelled = true; };
  }, [isValidateMode, documentId, detail?.id]);

  // Recalculate panel position when active view changes (validate mode)
  useEffect(() => {
    if (!validateActiveView) {
      setValidateConnectorGeom(null);
      setValidateViewPaddingTop(0);
      return;
    }
    const raf = requestAnimationFrame(() => {
      const blockEl = validateBlockRefs.current.get(validateActiveView.blockId);
      const scrollEl = validateScrollRef.current;
      const artEl = validateArticleRef.current;
      if (!blockEl || !scrollEl || !artEl) return;
      const scrollRect = scrollEl.getBoundingClientRect();
      const blockRect = blockEl.getBoundingClientRect();
      const blockTopInScroll = blockRect.top - scrollRect.top + scrollEl.scrollTop;
      setValidateViewPaddingTop(blockTopInScroll);
      const viewHeaderH = validateViewHeaderRef.current?.offsetHeight ?? 44;
      const connectorTop = blockTopInScroll + viewHeaderH / 2;
      const artRightX = artEl.getBoundingClientRect().right - scrollRect.left;
      const viewColRect = validateViewColRef.current?.getBoundingClientRect();
      if (viewColRect) {
        const viewColLeftX = viewColRect.left - scrollRect.left;
        const connectorLeft = artRightX + 6;
        setValidateConnectorGeom({
          top: connectorTop,
          left: connectorLeft,
          width: Math.max(0, viewColLeftX - 6 - connectorLeft),
        });
      }
    });
    return () => cancelAnimationFrame(raf);
  }, [validateActiveView]);

  const openValidateView = (blockId: string, mode: 'comments' | 'info') => {
    setValidateActiveView(prev =>
      prev?.blockId === blockId && prev?.mode === mode ? null : { blockId, mode },
    );
    if (mode === 'comments') setValidateNewCommentBody('');
  };

  // Dynamic top for fixed creator-preview comment panel (below page header)
  useLayoutEffect(() => {
    if (isValidateMode) return;
    const updateTop = () => {
      if (!pageHeaderRef.current) return;
      const bottom = pageHeaderRef.current.getBoundingClientRect().bottom;
      setCommentPanelTop(Math.max(8, bottom + 8));
    };
    updateTop();
    const ro = new ResizeObserver(updateTop);
    if (pageHeaderRef.current) ro.observe(pageHeaderRef.current);
    window.addEventListener('scroll', updateTop, { passive: true, capture: true });
    return () => {
      ro.disconnect();
      window.removeEventListener('scroll', updateTop, { capture: true });
    };
  }, [isValidateMode]);

  // Load creator-preview review comments when document has unresolved comments
  useEffect(() => {
    if (isValidateMode || !documentId || !detail?.has_review_comments) return;
    let cancelled = false;
    void apiFetchJson<{ data: BlockComment[] }>(`documents/${documentId}/comments`)
      .then((res) => { if (!cancelled) setReviewComments(res.data); })
      .catch(() => {});
    return () => { cancelled = true; };
  }, [isValidateMode, documentId, detail?.has_review_comments]);

  // Creator-preview comment helpers
  const blockPendingComments = (docBlockId: string | null) =>
    docBlockId ? reviewComments.filter(c => c.blockable_id === docBlockId && !c.parent_id && !c.resolved) : [];

  const blockAllComments = (docBlockId: string | null) =>
    docBlockId ? reviewComments.filter(c => c.blockable_id === docBlockId || reviewComments.some(r => r.id === c.parent_id && r.blockable_id === docBlockId)) : [];

  const handleResolveReviewComment = async (commentId: string) => {
    const res = await resolveComment(commentId);
    setReviewComments(prev => prev.map(c => c.id === commentId ? { ...c, ...res.data } : c));
  };

  const handleValidateAddComment = async () => {
    if (!validateNewCommentBody.trim() || !documentId || !validateActiveBlockId) return;
    const block = detail?.blocks.find(b => b.template_block_id === validateActiveBlockId);
    const blockableId = block?.document_block_id ?? null;
    setValidateCommentLoading(true);
    try {
      const res = await apiFetchJson<{ data: BlockComment }>(`documents/${documentId}/comments`, {
        method: 'POST',
        body: { body: validateNewCommentBody, blockable_id: blockableId },
      });
      setValidateComments(prev => [...prev, res.data]);
      setValidateNewCommentBody('');
    } catch {
      setValidateCommentError('No se pudo guardar el comentario.');
    } finally {
      setValidateCommentLoading(false);
    }
  };

  const handleValidateReply = async (parentCommentId: string, body: string) => {
    if (!documentId) return;
    const parent = validateComments.find(c => c.id === parentCommentId);
    const res = await apiFetchJson<{ data: BlockComment }>(`documents/${documentId}/comments`, {
      method: 'POST',
      body: { body, parent_id: parentCommentId, blockable_id: parent?.blockable_id ?? null },
    });
    setValidateComments(prev => [...prev, res.data]);
  };

  const handleValidateResolve = async (commentId: string) => {
    const res = await resolveComment(commentId);
    setValidateComments(prev => prev.map(c => c.id === commentId ? { ...c, ...res.data } : c));
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
    setActionLoading(true);
    setActionError(null);
    try {
      const data = await startDocumentNewVersion(documentId);
      setDetail(data);
      navigate(`/documents/${documentId}/editor`, { state: { step: 'properties' } });
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'No se pudo abrir una nueva versión.');
    } finally {
      setActionLoading(false);
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
      navigate('/dashboard', {
        state: { documentValidationBanner: validationSuccessBannerMessage(updated, 'approve') },
      });
    } catch (e) {
      setValidationModalError(e instanceof Error ? e.message : 'No se pudo aprobar la revisión.');
    } finally {
      setValidationActionLoading(false);
    }
  };

  const handleRejectValidation = async () => {
    if (!documentId || !actionableReviewId) {
      setValidationModalError('Faltan datos críticos para procesar la revisión.');
      return;
    }
    const reason = rejectReason.trim();
    if (reason.length < DOCUMENT_REJECT_REASON_MIN_LEN) {
      setValidationModalError(
        `Indica un motivo de rechazo de al menos ${DOCUMENT_REJECT_REASON_MIN_LEN} caracteres (obligatorio).`,
      );
      return;
    }
    setValidationModalError(null);
    setValidationActionLoading(true);
    try {
      const updated = await rejectDocumentReview(documentId, actionableReviewId, reason);
      setValidateConfirm(null);
      setRejectReason('');
      navigate('/dashboard', {
        state: { documentValidationBanner: validationSuccessBannerMessage(updated, 'reject') },
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
          <span className="text-xs font-mono bg-ui-body dark:bg-ui-dark-bg border border-ui-border dark:border-ui-dark-border px-2 py-0.5 rounded-full text-text-secondary dark:text-text-dark-secondary">
            v{detail.current_version}
          </span>
        </>
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
          loading={actionLoading}
          onClick={() => void handleStartNewVersion()}
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
      Fecha límite: {formatDate(detail.delivery_deadline)}
      {' · '}
      Última edición: {formatDate(versionSnapshot?.createdAt ?? detail.updated_at)}
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
    const validateBlockComments = (() => {
      if (!validateActiveBlockId || !validateSelectedBlock) return [];
      const rootIds = validateComments
        .filter(c => c.blockable_id === validateSelectedBlock.document_block_id && !c.parent_id)
        .map(c => c.id);
      return validateComments.filter(
        c => (c.blockable_id === validateSelectedBlock.document_block_id && !c.parent_id)
          || (c.parent_id !== null && rootIds.includes(c.parent_id)),
      );
    })();

    return (
      <>
        <div className="flex flex-col h-full bg-ui-preview-bg dark:bg-ui-dark-bg/50">
          {/* ── Page header ── */}
          <div className="shrink-0 px-6 py-3 bg-white dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between shadow-md z-20">
            <div className="flex items-center gap-3 min-w-0">
              <button
                onClick={handleBack}
                aria-label="Volver"
                className="w-8 h-8 rounded-full flex items-center justify-center hover:bg-ui-body dark:hover:bg-ui-dark-bg text-text-secondary transition-colors"
              >
                ←
              </button>
              <div className="min-w-0">
                <h2 className="text-sm font-bold text-text-primary dark:text-text-dark-primary">
                  Validación de Documento
                </h2>
                <p className="text-xs text-text-muted uppercase tracking-widest font-black truncate max-w-[320px]">
                  {detail?.title ?? 'Documento'}
                </p>
                {processLabel && (
                  <p className="text-[11px] text-text-muted mt-0.5 truncate max-w-[420px]">
                    {processLabel}
                  </p>
                )}
              </div>
            </div>
            <div className="flex items-center gap-2">
              <Button
                type="button"
                variant="outlineWarning"
                size="sm"
                disabled={!actionableReviewId || validationReviewLoading}
                onClick={() => { setValidationModalError(null); setValidateConfirm('reject'); }}
                className="text-xs font-black uppercase tracking-wider"
              >
                Rechazar validación
              </Button>
              <Button
                type="button"
                variant="primary"
                size="sm"
                disabled={!actionableReviewId || validationReviewLoading}
                onClick={() => { setValidationModalError(null); setValidateConfirm('approve'); }}
                className="text-xs font-black uppercase tracking-wider px-6"
              >
                Validar y aprobar
              </Button>
            </div>
          </div>

          {validationSetupError && !validationReviewLoading && (
            <div className="mx-6 mt-4 p-3 rounded-lg border border-danger/30 bg-danger/5 text-xs text-danger-dark font-bold">
              ⚠ {validationSetupError}
            </div>
          )}
          {validationReviewLoading && (
            <div className="mx-6 mt-4 p-3 rounded-lg border border-ui-border dark:border-ui-dark-border text-xs text-text-muted">
              Cargando datos de validación…
            </div>
          )}
          {(error && !loading) && (
            <div className="mx-6 mt-4 p-3 rounded-lg border border-danger/30 bg-danger/5 text-xs text-danger-dark font-bold">
              ⚠ {error}
            </div>
          )}
          {validateCommentError && (
            <div className="mx-6 mt-2 p-3 rounded-lg border border-danger/30 bg-danger/5 text-xs text-danger-dark font-bold">
              ⚠ {validateCommentError}
            </div>
          )}

          {/* ── Work area — both columns scroll together ── */}
          <div
            ref={validateScrollRef}
            className="flex-1 overflow-y-auto scroll-smooth custom-scrollbar relative"
          >
            {/* Connector line */}
            {validateActiveBlockId && validateConnectorGeom && (
              <div
                aria-hidden="true"
                className="bg-odoo-purple pointer-events-none"
                style={{
                  position: 'absolute',
                  top: validateConnectorGeom.top,
                  left: validateConnectorGeom.left,
                  width: validateConnectorGeom.width,
                  height: 1.5,
                  zIndex: 10,
                  transform: 'translateY(-50%)',
                }}
              />
            )}

            <div className="flex min-h-full">
              {/* Document column */}
              <div className="flex-1 p-8">
                <article
                  ref={validateArticleRef as React.RefObject<HTMLElement>}
                  className="mx-auto bg-ui-card dark:bg-ui-dark-card shadow-xl preview-content rounded-sm transition-all duration-300 animate-in fade-in slide-in-from-bottom-4"
                  style={{ maxWidth: '850px', minHeight: '100%', padding: '60px 70px' }}
                >
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
                            const hasComments = validateComments.some(c => c.blockable_id === block.document_block_id);
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
                                  <h3 className="flex-1 min-w-0 text-xs font-black uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary opacity-60 truncate">
                                    {block.title ?? 'Bloque sin título'}
                                  </h3>
                                  <div className="flex items-center gap-2">
                                    {/* Info button */}
                                    {hasDescription && (
                                      <button
                                        type="button"
                                        aria-label={`Ver descripción del bloque ${block.sort_order}`}
                                        onClick={(e) => { e.stopPropagation(); openValidateView(blockId, 'info'); }}
                                        className={`${btnBase} ${infoActive ? btnActive : btnIdle}`}
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
                                        `${btnBase}`,
                                        !block.document_block_id ? 'cursor-not-allowed opacity-40 border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/30' : commentsActive ? btnActive : btnIdle,
                                      ].join(' ')}
                                    >
                                      <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                      </svg>
                                      <span>Mensajes</span>
                                      {hasComments && (
                                        <span className="ml-1 bg-odoo-purple text-white px-1.5 py-0.5 rounded-full text-xs leading-none">
                                          {validateComments.filter(c => c.blockable_id === block.document_block_id && !c.parent_id).length}
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
                </article>
              </div>

              {/* Side panel: comments or info */}
              {validateActiveView && validateSelectedBlock && (
                <div
                  ref={validateViewColRef}
                  className={validateActiveView.mode === 'comments' ? 'shrink-0 pr-6' : 'flex-1 pr-6'}
                  style={{
                    ...(validateActiveView.mode === 'comments' ? { width: 408 } : {}),
                    paddingTop: validateViewPaddingTop,
                  }}
                >
                  {validateActiveView.mode === 'comments' ? (
                    <BlockCommentsCard
                      mode="validator"
                      blockSortOrder={validateSelectedBlock.sort_order ?? '?'}
                      blockComments={validateBlockComments}
                      allComments={validateComments}
                      newCommentBody={validateNewCommentBody}
                      onNewCommentBodyChange={setValidateNewCommentBody}
                      onAddComment={() => void handleValidateAddComment()}
                      commentLoading={validateCommentLoading}
                      canAddComments={!!actionableReviewId}
                      onReply={handleValidateReply}
                      onResolve={handleValidateResolve}
                      headerRef={validateViewHeaderRef}
                      onClose={() => { setValidateActiveView(null); setValidateNewCommentBody(''); }}
                    />
                  ) : (
                    <div className="bg-ui-card dark:bg-ui-dark-card shadow-xl rounded-sm flex flex-col overflow-hidden animate-in fade-in slide-in-from-right-4 duration-300">
                      <ViewCardHeader
                        blockSortOrder={validateSelectedBlock.sort_order ?? '?'}
                        title="Descripción del Bloque"
                        onClose={() => setValidateActiveView(null)}
                        headerRef={validateViewHeaderRef}
                      />
                      <div style={{ padding: '40px 60px' }}>
                        {validateSelectedBlock.description ? (
                          <BlockContentHtml content={normalizeBlockContentForEditor(validateSelectedBlock.description)} />
                        ) : (
                          <p className="text-sm text-text-muted italic">Este bloque no tiene descripción.</p>
                        )}
                      </div>
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>

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
          title="Confirmar rechazo"
          description={
            <div className="space-y-2 text-left">
              <p className="text-sm text-text-secondary dark:text-text-dark-secondary">
                El documento volverá a borrador para que el titular pueda corregirlo. El resto de validadores dejarán
                de tener esta revisión asignada.
              </p>
              <TextArea
                fieldSize="comfortable"
                rows={3}
                value={rejectReason}
                onChange={(e) => setRejectReason(e.target.value)}
                placeholder={`Motivo del rechazo (obligatorio, mín. ${DOCUMENT_REJECT_REASON_MIN_LEN} caracteres)`}
              />
            </div>
          }
          confirmLabel="Rechazar"
          variant="danger"
          error={validationModalError}
          loading={validationActionLoading}
          onCancel={() => { setValidateConfirm(null); setValidationModalError(null); setRejectReason(''); }}
          onConfirm={() => void handleRejectValidation()}
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
          reviewComments.length > 0 && !isHistoricalSnapshot ? (
            // Render blocks individually to support per-block comment selection
            <>
              <h1 className="text-2xl font-black text-text-primary dark:text-text-dark-primary mb-8 leading-tight">
                {previewTitle}
              </h1>
              <div className="space-y-8">
                {detail.blocks.map((block) => {
                  const pendingCount = blockPendingComments(block.document_block_id).length;
                  const isSelected = selectedReviewBlockId === block.template_block_id;
                  const clickable = pendingCount > 0;
                  return (
                    <section
                      key={block.template_block_id}
                      onClick={clickable ? () => setSelectedReviewBlockId(isSelected ? null : block.template_block_id) : undefined}
                      className={[
                        'relative rounded-lg transition-all duration-150',
                        clickable ? 'cursor-pointer' : '',
                        isSelected ? 'ring-2 ring-danger/40 ring-offset-4' : clickable ? 'hover:ring-1 hover:ring-danger/30 hover:ring-offset-2' : '',
                      ].join(' ')}
                    >
                      <div className="flex flex-wrap items-baseline gap-2 mb-2">
                        {block.title && <h4 className="text-sm font-bold text-text-secondary dark:text-text-dark-secondary">{block.title}</h4>}
                        {pendingCount > 0 && (
                          <span className="inline-flex items-center gap-1 text-xs font-black uppercase tracking-widest px-2 py-0.5 rounded-full bg-danger/10 text-danger-dark dark:text-danger border border-danger/20" title="Comentarios de revisión pendientes">
                            ⚠ {pendingCount} {pendingCount === 1 ? 'comentario' : 'comentarios'}
                          </span>
                        )}
                      </div>
                      {(() => {
                        const nodes = blockContentForPreview(block);
                        return nodes.length > 0 ? <BlockContentHtml content={nodes} /> : <p className="text-sm text-text-muted italic">Sin contenido.</p>;
                      })()}
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

      {/* Creator review comments panel (fixed, like TemplatePreviewPage) */}
      {!isHistoricalSnapshot && selectedReviewBlockId && (() => {
        const block = detail?.blocks.find(b => b.template_block_id === selectedReviewBlockId);
        const mode: CommentMode = (isDraft && isOwner) ? 'creator-edit' : 'creator-readonly';
        return (
          <div className="fixed right-6 w-[384px] z-30" style={{ top: commentPanelTop }}>
            <BlockCommentsCard
              mode={mode}
              blockSortOrder={block?.sort_order ?? '?'}
              blockComments={blockAllComments(block?.document_block_id ?? null)}
              allComments={reviewComments}
              headerRef={commentCardHeaderRef}
              onResolve={mode === 'creator-edit' ? handleResolveReviewComment : undefined}
              onClose={() => setSelectedReviewBlockId(null)}
            />
          </div>
        );
      })()}

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
