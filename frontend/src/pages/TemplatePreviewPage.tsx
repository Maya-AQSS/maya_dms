import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { fetchTemplate, submitTemplateForReview, deleteTemplate } from '../api/templates';
import { fetchBlocks } from '../api/blocks';
import { normalizeBlockContentForEditor } from '../features/documents/lib/normalizeBlockContent';
import { BlockContentHtml } from '../features/templates/components/BlockContentHtml';
import { visibilityLabel } from '../features/templates/constants';
import type { Template } from '../types/templates';
import type { TemplateBlock } from '../types/blocks';
import { Button, ConfirmDialog } from '../ui';
import { FavoriteButton } from '../components/FavoriteButton';
import { VersionHistoryPanel } from '../components/VersionHistoryPanel';
import { useUserProfile } from '../features/user-profile';

const STATUS_BADGE: Record<string, string> = {
  draft: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
  in_review: 'bg-amber-200 text-amber-900 dark:bg-amber-800/40 dark:text-amber-200',
  published: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
  archived: 'bg-ui-border text-text-secondary dark:bg-ui-dark-border dark:text-text-dark-secondary',
};

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

export function TemplatePreviewPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { profile } = useUserProfile();

  const [template, setTemplate] = useState<Template | null>(null);
  const [blocks, setBlocks] = useState<TemplateBlock[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [showHistory, setShowHistory] = useState(false);
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

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
        const [tRes, bRes] = await Promise.all([
          fetchTemplate(id),
          fetchBlocks(id),
        ]);
        if (!cancelled) {
          setTemplate(tRes.data);
          setBlocks(bRes.data.slice().sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)));
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
  }, [id]);

  const isDraft = template?.status === 'draft';
  const isOwner = profile?.id === template?.created_by;

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

  const handleDelete = async () => {
    if (!id) return;
    setDeleteLoading(true);
    setDeleteError(null);
    try {
      await deleteTemplate(id);
      navigate('/procesos');
    } catch (e) {
      setDeleteError(e instanceof Error ? e.message : 'No se pudo eliminar la plantilla.');
      setDeleteLoading(false);
    }
  };

  return (
    <div className="min-h-full overflow-y-auto bg-ui-preview-bg dark:bg-ui-dark-bg">
      <header className="sticky top-0 z-10 bg-ui-card dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border flex items-center gap-3 px-6 h-[52px]">
        <button
          type="button"
          onClick={() => navigate('/procesos')}
          className="shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold text-text-secondary dark:text-text-dark-secondary bg-ui-body dark:bg-ui-dark-bg hover:bg-ui-border dark:hover:bg-ui-dark-border transition-colors cursor-pointer"
        >
          ← Volver
        </button>
        <span className="flex-1 text-xs font-semibold text-text-muted dark:text-text-dark-muted truncate">
          {template?.name ?? 'Plantilla'} — Previsualización
        </span>
        <div className="flex items-center gap-2 shrink-0">
          {template && (
            <>
              <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${STATUS_BADGE[template.status] ?? ''}`}>
                {STATUS_LABEL[template.status] ?? template.status}
              </span>
              <span className="text-xs font-mono bg-ui-body dark:bg-ui-dark-bg border border-ui-border dark:border-ui-dark-border px-2 py-0.5 rounded-full text-text-secondary dark:text-text-dark-secondary">
                v{template.version}
              </span>
              {id && <FavoriteButton entityType="template" entityId={id} />}
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setShowHistory(true)}
              >
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
              {template.status === 'draft' && isOwner && (
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => navigate(`/templates/${id}/edit`)}
                >
                  Editar
                </Button>
              )}
              {isDraft && isOwner && (
                <Button
                  type="button"
                  variant="primary"
                  size="sm"
                  loading={actionLoading}
                  onClick={() => void handleSubmitForReview()}
                >
                  Enviar a validar
                </Button>
              )}
            </>
          )}
        </div>
      </header>

      {template && (
        <div className="max-w-[960px] mx-auto px-6 py-2 border-b border-ui-border/50 dark:border-ui-dark-border/50">
          <p className="text-[11px] text-text-muted dark:text-text-dark-muted">
            {template.author_name ?? 'Autor desconocido'}
            {' · '}
            {visibilityLabel(template.visibility_level)}
            {' · '}
            Fecha límite: {formatDate(template.delivery_deadline)}
            {' · '}
            Última edición: {formatDate(template.updated_at)}
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
          <p className="text-sm text-text-muted dark:text-text-dark-muted">Cargando plantilla…</p>
        )}
        {error && !loading && (
          <p className="text-sm text-warning-dark dark:text-warning-light">{error}</p>
        )}
        {!loading && !error && template && (
          <>
            <h1 className="text-2xl font-bold text-text-primary dark:text-text-dark-primary pb-4 mb-6 border-b border-ui-border dark:border-ui-dark-border">
              {template.name}
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

                  return (
                    <section
                      key={block.id}
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
    </div>
  );
}
