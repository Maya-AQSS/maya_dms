import { useEffect, useState } from 'react';
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom';
import { fetchDocument, submitDocumentForReview, deleteDocument } from '../api/documents';
import { normalizeBlockContentForEditor } from '../features/documents/lib/normalizeBlockContent';
import type { DocumentDetail, DocumentDisplayBlock } from '../types/documents';
import { visibilityLabel } from '../features/templates/constants';
import { Button, ConfirmDialog, statusBadgeClass } from '@maya/shared-ui-react';
import { FavoriteButton } from '../components/FavoriteButton';
import { VersionHistoryPanel } from '../components/VersionHistoryPanel';
import { useUserProfile } from '../features/user-profile';
import { PaperPreviewLayout } from '../features/documents/components/PaperPreviewLayout';
import { PaperBlocksArticle, type PaperArticleBlock } from '../features/documents/components/PaperBlocksArticle';

// Estado: clases en `statusBadgeClass` (módulo `@maya/shared-ui-react/badges`).

const STATUS_LABEL: Record<string, string> = {
  draft: 'Borrador',
  in_review: 'En revisión',
  published: 'Aprobado',
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

export function DocumentPreviewPage() {
  const { documentId } = useParams<{ documentId: string }>();
  const navigate = useNavigate();
  const location = useLocation();
  const { profile } = useUserProfile();

  const [detail, setDetail] = useState<DocumentDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [showHistory, setShowHistory] = useState(false);
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

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

  const previewState = location.state as { returnToStep?: string; returnToValidate?: boolean } | null;
  const cameFromSummary = previewState?.returnToStep === 'summary';
  const cameFromValidate = previewState?.returnToValidate === true;

  const backLabel = cameFromSummary
    ? cameFromValidate ? 'Volver a validar' : 'Volver al resumen'
    : 'Volver';

  const handleBack = () => {
    if (cameFromSummary && documentId) {
      if (cameFromValidate) {
        navigate(`/documents/${documentId}/validate`);
      } else {
        navigate(`/documents/${documentId}/editor`, { state: { step: 'summary' } });
      }
      return;
    }
    navigate('/procesos', { state: { tab: 'documents' } });
  };

  const isDraft = detail?.status === 'draft';
  const isOwner = profile?.id === detail?.owner_id || profile?.id === detail?.created_by;

  const handleDelete = async () => {
    if (!documentId) return;
    setDeleteLoading(true);
    setDeleteError(null);
    try {
      await deleteDocument(documentId);
      navigate('/procesos', { state: { tab: 'documents' } });
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
      {isDraft && isOwner && (
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
      {isDraft && isOwner && documentId && (
        <Link to={`/documents/${documentId}/editor`}>
          <Button type="button" size="sm" variant="outline">
            Editar
          </Button>
        </Link>
      )}
      {isDraft && isOwner && (
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
    </>
  ) : null;

  const headerMetaInfo = detail ? (
    <p className="text-xs text-text-muted dark:text-text-dark-muted text-center">
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
