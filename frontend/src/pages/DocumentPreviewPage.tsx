import { useEffect, useState } from 'react';
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom';
import {
  fetchDocument,
  fetchDocumentReviews,
  submitDocumentForReview,
  deleteDocument,
  approveDocumentReview,
  rejectDocumentReview,
  type DocumentReview,
} from '../api/documents';
import { fetchTemplate } from '../api/templates';
import { fetchProcesses } from '../api/processes';
import { fetchMe } from '../api/users';
import { normalizeBlockContentForEditor } from '../features/documents/lib/normalizeBlockContent';
import type { DocumentDetail, DocumentDisplayBlock } from '../types/documents';
import { visibilityLabel } from '../features/templates/constants';
import { Button, ConfirmDialog, TextArea, statusBadgeClass } from '@maya/shared-ui-react';
import { FavoriteButton } from '../components/FavoriteButton';
import { VersionHistoryPanel } from '../components/VersionHistoryPanel';
import { useUserProfile } from '../features/user-profile';
import { PaperPreviewLayout } from '../features/documents/components/PaperPreviewLayout';
import { PaperBlocksArticle, type PaperArticleBlock } from '../features/documents/components/PaperBlocksArticle';
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
  const location = useLocation();
  const { profile } = useUserProfile();
  const isValidateMode = mode === 'validate';

  const [detail, setDetail] = useState<DocumentDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [showHistory, setShowHistory] = useState(false);
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [validationReviewLoading, setValidationReviewLoading] = useState(false);
  const [validationSetupError, setValidationSetupError] = useState<string | null>(null);
  const [actionableReviewId, setActionableReviewId] = useState<string | null>(null);
  const [validateConfirm, setValidateConfirm] = useState<null | 'approve' | 'reject'>(null);
  const [validationActionLoading, setValidationActionLoading] = useState(false);
  const [validationModalError, setValidationModalError] = useState<string | null>(null);
  const [rejectReason, setRejectReason] = useState('');
  const [processLabel, setProcessLabel] = useState<string | null>(null);

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
        const data = await fetchDocument(documentId);
        if (!cancelled) setDetail(data);
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
  }, [documentId]);

  const previewState = location.state as {
    returnToStep?: string;
    returnToValidate?: boolean;
    backTo?: string;
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
  const isOwner = profile?.id === detail?.owner_id || profile?.id === detail?.created_by;

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

  const handleSubmit = async () => {
    if (!documentId || !detail) return;
    setActionLoading(true);
    setActionError(null);
    try {
      const res = await submitDocumentForReview(documentId);
      setDetail((prev) => prev ? ({ ...prev, status: res.status, submitted_at: res.submitted_at } as typeof prev) : prev);
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'No se pudo enviar a validar.');
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
      <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${statusBadgeClass(detail.status)}`}>
        {STATUS_LABEL[detail.status] ?? detail.status}
      </span>
      <span className="text-xs font-mono bg-ui-body dark:bg-ui-dark-bg border border-ui-border dark:border-ui-dark-border px-2 py-0.5 rounded-full text-text-secondary dark:text-text-dark-secondary">
        v{detail.current_version}
      </span>
      {documentId && <FavoriteButton entityType="document" entityId={documentId} />}
      <Button type="button" variant="outline" size="sm" onClick={() => setShowHistory(true)}>
        Historial
      </Button>
      {!isValidateMode && isDraft && isOwner && (
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
      {!isValidateMode && isDraft && isOwner && documentId && (
        <Link to={`/documents/${documentId}/editor`}>
          <Button type="button" size="sm" variant="outline">
            Editar
          </Button>
        </Link>
      )}
      {!isValidateMode && isDraft && isOwner && (
        <Button
          type="button"
          variant="primary"
          size="sm"
          loading={actionLoading}
          disabled={!!detail.has_review_comments}
          onClick={() => void handleSubmit()}
        >
          Enviar a validar
        </Button>
      )}
      {isValidateMode && (
        <>
          <Button
            type="button"
            variant="secondary"
            size="sm"
            disabled={!actionableReviewId || validationReviewLoading}
            onClick={() => {
              setValidationModalError(null);
              setValidateConfirm('reject');
            }}
          >
            Rechazar
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
      {detail.owner_name ?? 'Autor desconocido'}
      {' · '}
      {detail.visibility_level ? visibilityLabel(detail.visibility_level) : (detail.is_shared_with_me ? 'Compartida' : 'Personal')}
      {' · '}
      Fecha límite: {formatDate(detail.delivery_deadline)}
      {' · '}
      Última edición: {formatDate(detail.updated_at)}
    </p>
  ) : null;

  const articleBlocks: PaperArticleBlock[] = (detail?.blocks ?? []).map((b) => ({
    id: b.template_block_id,
    title: b.title,
    mandatory: b.mandatory,
    isLocked: b.block_state === 'locked',
    nodes: blockContentForPreview(b),
  }));

  if (isValidateMode) {
    return (
      <>
        <div className="flex flex-col h-full bg-ui-preview-bg dark:bg-ui-dark-bg/50">
          <div className="shrink-0 px-6 py-3 bg-white dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between shadow-md z-20">
            <div className="flex items-center gap-3 min-w-0">
              <button
                onClick={handleBack}
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
          {error && !loading && (
            <div className="mx-6 mt-4 p-3 rounded-lg border border-danger/30 bg-danger/5 text-xs text-danger-dark font-bold">
              ⚠ {error}
            </div>
          )}

          <div className="flex-1 overflow-y-auto p-8 scroll-smooth custom-scrollbar">
            <article
              className="mx-auto bg-white dark:bg-ui-card shadow-2xl rounded-sm transition-all duration-300"
              style={{ maxWidth: '850px', minHeight: '100%', padding: '60px 70px' }}
            >
              {!loading && !error && detail ? (
                <PaperBlocksArticle
                  title={detail.title}
                  blocks={articleBlocks}
                  emptyMessage="Este documento no tiene bloques."
                />
              ) : (
                <p className="text-sm text-text-muted dark:text-text-dark-muted">Cargando documento…</p>
              )}
            </article>
          </div>
        </div>

        <ConfirmDialog
          open={validateConfirm === 'approve'}
          title="Confirmar aprobación"
          description="Se registrará tu aprobación. Si eres el último validador pendiente, el documento pasará a publicado."
          confirmLabel="Aprobar"
          error={validationModalError}
          loading={validationActionLoading}
          onCancel={() => {
            setValidateConfirm(null);
            setValidationModalError(null);
          }}
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
          onCancel={() => {
            setValidateConfirm(null);
            setValidationModalError(null);
            setRejectReason('');
          }}
          onConfirm={() => void handleRejectValidation()}
        />
      </>
    );
  }

  return (
    <>
      <PaperPreviewLayout
        title={detail?.title ?? 'Programación'}
        onBack={handleBack}
        backLabel={backLabel}
        metaInfo={headerMetaInfo}
        actions={headerActions}
      >
        {loading && (
          <p className="text-sm text-text-muted dark:text-text-dark-muted">Cargando documento…</p>
        )}
        {error && !loading && (
          <p className="text-sm text-warning-dark dark:text-warning-light">{error}</p>
        )}
        {actionError && (
          <p className="text-sm text-warning-dark dark:text-warning-light mb-4">{actionError}</p>
        )}
        {isValidateMode && validationSetupError && !validationReviewLoading && (
          <p className="text-sm text-warning-dark dark:text-warning-light mb-4">{validationSetupError}</p>
        )}
        {isValidateMode && validationReviewLoading && (
          <p className="text-sm text-text-muted dark:text-text-dark-muted mb-4">Cargando datos de validación…</p>
        )}
        {!loading && !error && detail && (
          <PaperBlocksArticle
            title={detail.title}
            blocks={articleBlocks}
            emptyMessage="Este documento no tiene bloques."
          />
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
    </>
  );
}
