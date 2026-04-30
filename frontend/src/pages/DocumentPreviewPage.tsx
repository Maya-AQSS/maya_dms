import { useEffect, useState } from 'react';
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom';
import { fetchDocument, submitDocumentForReview, deleteDocument } from '../api/documents';
import { normalizeBlockContentForEditor } from '../features/documents/lib/normalizeBlockContent';
import { BlockContentHtml } from '../features/templates/components/BlockContentHtml';
import type { DocumentDetail, DocumentDisplayBlock } from '../types/documents';
import { visibilityLabel } from '../features/templates/constants';
import { Button, ConfirmDialog } from '../ui';
import { FavoriteButton } from '../components/FavoriteButton';
import { VersionHistoryPanel } from '../components/VersionHistoryPanel';
import { useUserProfile } from '../features/user-profile';

const STATUS_BADGE: Record<string, string> = {
  draft: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
  in_review: 'bg-amber-200 text-amber-900 dark:bg-amber-800/40 dark:text-amber-200',
  published: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
};

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
    ? cameFromValidate ? '← Volver a validar' : '← Volver al resumen'
    : '← Volver';

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

  return (
    <div className="min-h-full overflow-y-auto bg-ui-preview-bg dark:bg-ui-dark-bg">
      <header className="sticky top-0 z-10 bg-ui-card dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border flex items-center gap-3 px-6 h-[52px]">
        <button
          type="button"
          onClick={handleBack}
          className="shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold text-text-secondary dark:text-text-dark-secondary bg-ui-body dark:bg-ui-dark-bg hover:bg-ui-border dark:hover:bg-ui-dark-border transition-colors cursor-pointer"
        >
          {backLabel}
        </button>
        <span className="flex-1 text-xs font-semibold text-text-muted dark:text-text-dark-muted truncate">
          {detail?.title ?? 'Programación'} — Previsualización
        </span>
        <div className="flex items-center gap-2 shrink-0">
          {detail && (
            <>
              <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${STATUS_BADGE[detail.status] ?? ''}`}>
                {STATUS_LABEL[detail.status] ?? detail.status}
              </span>
              <span className="text-xs font-mono bg-ui-body dark:bg-ui-dark-bg border border-ui-border dark:border-ui-dark-border px-2 py-0.5 rounded-full text-text-secondary dark:text-text-dark-secondary">
                v{detail.current_version}
              </span>
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
              {documentId && <FavoriteButton entityType="document" entityId={documentId} />}
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setShowHistory(true)}
              >
                Historial
              </Button>
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
          )}
        </div>
      </header>

      {detail && (
        <div className="max-w-[960px] mx-auto px-6 py-2 border-b border-ui-border/50 dark:border-ui-dark-border/50">
          <p className="text-[11px] text-text-muted dark:text-text-dark-muted">
            {detail.owner_name ?? 'Autor desconocido'}
            {' · '}
            {detail.visibility_level ? visibilityLabel(detail.visibility_level) : (detail.is_shared_with_me ? 'Compartida' : 'Personal')}
            {' · '}
            Fecha límite: {formatDate(detail.delivery_deadline)}
            {' · '}
            Última edición: {formatDate(detail.updated_at)}
          </p>
        </div>
      )}

      {actionError && (
        <div className="max-w-[960px] mx-auto px-6 py-2">
          <p className="text-sm text-warning-dark dark:text-warning-light">{actionError}</p>
        </div>
      )}

      <article
        className="mx-auto bg-ui-card dark:bg-ui-dark-card shadow-xl preview-content"
        style={{ maxWidth: '760px', minHeight: 'calc(100vh - 52px)', padding: '56px 72px' }}
      >
        {loading && (
          <p className="text-sm text-text-muted dark:text-text-dark-muted">Cargando documento…</p>
        )}
        {error && !loading && (
          <p className="text-sm text-warning-dark dark:text-warning-light">{error}</p>
        )}
        {!loading && !error && detail && (
          <>
            <h1 className="text-2xl font-bold text-text-primary dark:text-text-dark-primary pb-4 mb-6 border-b border-ui-border dark:border-ui-dark-border">
              {detail.title}
            </h1>
            {detail.blocks.length === 0 ? (
              <p className="text-sm text-text-muted dark:text-text-dark-muted italic">
                Este documento no tiene bloques.
              </p>
            ) : (
              <div className="space-y-10">
                {detail.blocks.map((block) => {
                  const isLocked = block.block_state === 'locked';
                  const nodes = blockContentForPreview(block);
                  const hasContent = nodes.length > 0;

                  return (
                    <section
                      key={block.template_block_id}
                      style={isLocked ? { opacity: 0.45, pointerEvents: 'none' } : undefined}
                    >
                      <div className="flex flex-wrap items-baseline gap-2 mb-2">
                        {block.title && (
                          <h4 className="text-sm font-bold text-text-secondary dark:text-text-dark-secondary">
                            {block.title}
                          </h4>
                        )}
                        {block.mandatory && (
                          <span className="text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                            Obligatorio
                          </span>
                        )}
                        {isLocked && (
                          <span className="text-[10px] font-medium uppercase tracking-wide px-1.5 py-0.5 rounded bg-ui-border/60 dark:bg-ui-dark-border text-text-muted dark:text-text-dark-muted">
                            Bloqueado
                          </span>
                        )}
                      </div>
                      {hasContent ? (
                        <BlockContentHtml content={nodes as unknown[]} />
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
    </div>
  );
}
