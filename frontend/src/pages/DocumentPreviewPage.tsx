import React, { useEffect, useMemo, useRef, useState } from 'react';
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
import { fetchTemplate } from '../api/templates';
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
import type { BlockComment } from '../features/templates/components/BlockCommentsCard';
import { BlockContentHtml } from '../features/templates/components/BlockContentHtml';
import { computeChangedBlocks } from '../features/documents/components/DocumentDiffModal';
import { DocumentDiffPanel } from '../features/documents/components/DocumentDiffPanel';
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
  const [showDiffPanel, setShowDiffPanel] = useState(isValidateMode);
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
  const [validateCommentLoading, setValidateCommentLoading] = useState(false);
  const [validateCommentError, setValidateCommentError] = useState<string | null>(null);
  const validateBlockRefs = useRef<Map<string, HTMLElement>>(new Map());
  const validateViewHeaderRef = useRef<HTMLDivElement>(null);

  // Creator preview-mode comment state (mirrors TemplatePreviewPage)
  const [reviewComments, setReviewComments] = useState<BlockComment[]>([]);
  const [reviewCommentsLoading, setReviewCommentsLoading] = useState(false);
  const [selectedReviewView, setSelectedReviewView] = useState<{ blockId: string; mode: 'comments' | 'info' } | null>(null);
  const pageHeaderRef = useRef<HTMLDivElement>(null);

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
  const changedBlocks = useMemo(
    () => (detail ? computeChangedBlocks(detail.blocks) : []),
    [detail],
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
        const [reviews, meRes] = await Promise.all([
          fetchDocumentReviews(detail.id),
          fetchMe(),
        ]);
        if (cancelled) return;
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


  const openValidateView = (blockId: string, mode: 'comments' | 'info') => {
    setValidateActiveView((prev: any) =>
      prev?.blockId === blockId && prev?.mode === mode ? null : { blockId, mode },
    );
  };

  // Dynamic top for fixed creator-preview comment panel (below page header)
  // No longer needed with PaperPreviewLayout

  // Load creator-preview review comments when document has unresolved comments
  useEffect(() => {
    if (isValidateMode || !documentId || !detail?.has_review_comments) return;
    let cancelled = false;
    void apiFetchJson<{ data: BlockComment[] }>(`documents/${documentId}/comments`)
      .then((res) => { if (!cancelled) setReviewComments(res.data); })
      .catch(() => {});
    return () => { cancelled = true; };
  }, [isValidateMode, documentId, detail?.has_review_comments]);

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
      setReviewComments(prev => [...prev, res.data]);
    } catch (e) {
      console.error('Error sending message', e);
    } finally {
      setReviewCommentsLoading(false);
    }
  };

  // Comment helpers that include replies in the count
  const getCommentsForBlock = (docBlockId: string | null, allComments: BlockComment[]) => {
    if (!docBlockId) return [];
    
    // Recursive function to get all replies to a comment
    const getReplies = (parentId: string): BlockComment[] => {
      const replies = allComments.filter(c => c.parent_id === parentId);
      return [...replies, ...replies.flatMap(r => getReplies(r.id))];
    };

    // Get all root comments for this block
    const roots = allComments.filter(c => c.blockable_id === docBlockId && !c.parent_id);
    
    // Combine roots and all their recursive replies
    const allForBlock = [...roots, ...roots.flatMap(r => getReplies(r.id))];

    // Deduplicate by ID to be safe
    const uniqueIds = Array.from(new Set(allForBlock.map(c => c.id)));
    return uniqueIds.map(id => allForBlock.find(c => c.id === id) as BlockComment);
  };

  const handleValidateSendMessage = async (parentId: string | null, body: string) => {
    if (!documentId) return;
    setValidateCommentLoading(true);
    setValidateCommentError(null);
    try {
      const parent = parentId ? validateComments.find(c => c.id === parentId) : null;
      const blockableId = parentId
        ? (parent?.blockable_id ?? null)
        : (detail?.blocks.find(b => b.template_block_id === validateActiveBlockId)?.document_block_id ?? null);
      const res = await apiFetchJson<{ data: BlockComment }>(`documents/${documentId}/comments`, {
        method: 'POST',
        body: { body, parent_id: parentId, blockable_id: blockableId },
      });
      setValidateComments(prev => [...prev, res.data]);
    } catch {
      setValidateCommentError('No se pudo guardar el comentario.');
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

    const norm = (v: unknown) => JSON.stringify(normalizeBlockContentForEditor(v));
    const unmodifiedModifiable = detail.blocks.filter((b: DocumentDisplayBlock) => {
      if (b.block_state !== 'modifiable' || b.is_deleted) return false;
      return norm(b.content) === norm(b.default_content);
    });
    if (unmodifiedModifiable.length > 0) {
      const names = unmodifiedModifiable
        .map((b: DocumentDisplayBlock) => b.title ?? 'Sin título')
        .join(', ');
      setActionError(
        `Debes editar todos los bloques modificables antes de enviar a revisión. Bloques sin cambios: ${names}.`,
      );
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
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => { setSelectedReviewView(null); setShowDiffPanel(true); }}
        >
          Ver cambios
        </Button>
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
    const validateBlockComments = validateSelectedBlock
      ? getCommentsForBlock(validateSelectedBlock.document_block_id, validateComments)
      : [];

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
              {changedBlocks.length > 0 && (
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => { setValidateActiveView(null); setShowDiffPanel(true); }}
                  className="text-xs font-black uppercase tracking-wider"
                >
                  Ver cambios
                </Button>
              )}
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
          }
          sidebar={
            showDiffPanel && !validateActiveView
              ? <DocumentDiffPanel blocks={validateBlocks} onClose={() => setShowDiffPanel(false)} />
              : validateActiveView && validateSelectedBlock
                ? (
                    validateActiveView.mode === 'comments' ? (
                      <BlockCommentsCard
                        mode="validator"
                        blockSortOrder={(validateBlocks.indexOf(validateSelectedBlock) + 1) || '?'}
                        blockComments={validateBlockComments}
                        allComments={validateComments}
                        onSendMessage={handleValidateSendMessage}
                        commentLoading={validateCommentLoading}
                        canAddComments={!!actionableReviewId}
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
                : undefined
          }
        >
          {validationSetupError && !validationReviewLoading && (
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
                          <h4 className="flex-1 min-w-0 text-xs font-black uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary opacity-60 truncate">
                            Bloque {(detail.blocks.findIndex((b: any) => b.template_block_id === block.template_block_id) + 1)}: {block.title ?? 'Sin título'}
                          </h4>
                          <div className="flex items-center gap-2">
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
        sidebar={
          showDiffPanel && !selectedReviewView
            ? <DocumentDiffPanel blocks={detail?.blocks ?? []} onClose={() => setShowDiffPanel(false)} />
            : selectedReviewView && (() => {
          const block = detail?.blocks?.find(b => (b.document_block_id || b.template_block_id) === selectedReviewView.blockId);
          if (!block) return null;

          const isCreator = profile?.id && detail?.created_by === profile.id;
          const isOwner = profile?.id && detail?.owner_id === profile.id;
          const commentMode = (isCreator || isOwner) ? 'creator-edit' : 'creator-readonly';

          if (selectedReviewView.mode === 'comments') {
            return (
              <BlockCommentsCard
                mode={commentMode}
                blockSortOrder={(detail?.blocks?.indexOf(block) + 1) || '?'}
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
                blockSortOrder={(detail?.blocks?.indexOf(block) + 1) || '?'}
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
                {detail.blocks.filter((block: DocumentDisplayBlock) => !block.is_deleted).map((block: any) => {
                  const blockId = block.document_block_id || block.template_block_id;
                  const isSelected = selectedReviewView?.blockId === blockId;
                  const commentsActive = isSelected && selectedReviewView?.mode === 'comments';
                  const infoActive = isSelected && selectedReviewView?.mode === 'info';
                  const totalComments = getCommentsForBlock(block.document_block_id, reviewComments);
                  const hasDescription = !!block.description;
                  const nodes = blockContentForPreview(block);

                  return (
                    <section
                      key={blockId}
                      className={[
                        'relative group rounded-lg transition-all duration-200 cursor-pointer',
                        isSelected
                          ? 'ring-2 ring-odoo-purple ring-offset-8 dark:ring-offset-ui-dark-card shadow-sm'
                          : 'hover:ring-1 hover:ring-ui-border dark:hover:ring-ui-dark-border hover:ring-offset-4 dark:hover:ring-offset-ui-dark-card',
                      ].join(' ')}
                      onClick={(e) => { e.stopPropagation(); if (block.document_block_id) setSelectedReviewView({ blockId, mode: 'comments' }); }}
                    >
                      <div className={['absolute -left-12 top-0 text-xs font-black uppercase tracking-tighter transition-opacity duration-200', isSelected ? 'opacity-100 text-odoo-purple' : 'opacity-0 group-hover:opacity-40 text-text-muted'].join(' ')}>
                        #{block.sort_order ?? '?'}
                      </div>

                      <div className="flex items-center gap-3 mb-4">
                        <h4 className="flex-1 min-w-0 text-xs font-black uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary opacity-60 truncate">
                          Bloque {(detail.blocks.findIndex((b: any) => b.template_block_id === block.template_block_id) + 1)}: {block.title ?? 'Sin título'}
                        </h4>
                        <div className="flex items-center gap-2">
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
                          <button
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
