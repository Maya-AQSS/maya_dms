import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { fetchTemplate } from '../api/templates';
import { fetchBlocks } from '../api/blocks';
import { normalizeBlockContentForEditor } from '../features/documents/lib/normalizeBlockContent';
import { BlockContentHtml } from '../features/templates/components/BlockContentHtml';
import { visibilityLabel } from '../features/templates/constants';
import type { Template } from '../types/templates';
import type { TemplateBlock } from '../types/blocks';
import { Button } from '../ui';
import { VersionHistoryPanel } from '../components/VersionHistoryPanel';

const STATUS_BADGE: Record<string, string> = {
  published: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
};

const VISIBILITY_BADGE: Record<string, string> = {
  personal:   'bg-ui-border text-text-secondary dark:bg-ui-dark-border dark:text-text-dark-secondary',
  global:     'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
  study_type: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
  study:      'bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-300',
  module:     'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
  team:       'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300',
};

function blockContentNodes(block: TemplateBlock): unknown[] {
  return normalizeBlockContentForEditor(block.default_content);
}

function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—';
  return iso.slice(0, 10);
}

export function NuevaProgramacionPreviewPage() {
  const { templateId } = useParams<{ templateId: string }>();
  const navigate = useNavigate();

  const [template, setTemplate] = useState<Template | null>(null);
  const [blocks, setBlocks] = useState<TemplateBlock[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showHistory, setShowHistory] = useState(false);

  useEffect(() => {
    if (!templateId) {
      setLoading(false);
      setError('Identificador de plantilla no válido.');
      return;
    }
    let cancelled = false;
    const load = async () => {
      try {
        setLoading(true);
        setError(null);
        const [tRes, bRes] = await Promise.all([fetchTemplate(templateId), fetchBlocks(templateId)]);
        if (!cancelled) {
          setTemplate(tRes.data);
          setBlocks(bRes.data.slice().sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)));
        }
      } catch (e) {
        if (!cancelled) setError(e instanceof Error ? e.message : 'No se pudo cargar la plantilla.');
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    void load();
    return () => { cancelled = true; };
  }, [templateId]);

  return (
    <div className="min-h-full overflow-y-auto bg-ui-preview-bg dark:bg-ui-dark-bg">
      <header className="sticky top-0 z-10 bg-ui-card dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border flex items-center gap-3 px-6 h-[52px]">
        <button
          type="button"
          onClick={() => navigate('/nueva-programacion')}
          className="shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold text-text-secondary dark:text-text-dark-secondary bg-ui-body dark:bg-ui-dark-bg hover:bg-ui-border dark:hover:bg-ui-dark-border transition-colors cursor-pointer"
        >
          ← Seleccionar plantilla
        </button>
        <span className="flex-1 text-xs font-semibold text-text-muted dark:text-text-dark-muted truncate">
          {template?.name ?? 'Plantilla'} — Vista previa
        </span>
        <div className="flex items-center gap-2 shrink-0">
          {template && (
            <>
              <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${VISIBILITY_BADGE[template.visibility_level] ?? ''}`}>
                {visibilityLabel(template.visibility_level)}
              </span>
              <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${STATUS_BADGE[template.status] ?? ''}`}>
                Publicada
              </span>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setShowHistory(true)}
              >
                Versiones de la plantilla
              </Button>
              <Button
                type="button"
                variant="primary"
                size="sm"
                onClick={() => navigate(`/nueva-programacion/${templateId}/wizard`)}
              >
                Usar plantilla
              </Button>
            </>
          )}
        </div>
      </header>

      {template && (
        <div className="max-w-[960px] mx-auto px-6 py-2 border-b border-ui-border/50 dark:border-ui-dark-border/50">
          <p className="text-xs text-text-muted dark:text-text-dark-muted">
            {template.author_name ?? 'Autor desconocido'}
            {' · '}
            {visibilityLabel(template.visibility_level)}
            {' · '}
            Fecha límite de validación: {formatDate(template.delivery_deadline)}
            {' · '}
            Última edición: {formatDate(template.updated_at)}
          </p>
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
                          <span className="text-xs font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                            Obligatorio
                          </span>
                        )}
                        {isLocked && (
                          <span className="text-xs font-medium uppercase tracking-wide px-1.5 py-0.5 rounded bg-ui-border/60 dark:bg-ui-dark-border text-text-muted dark:text-text-dark-muted">
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

      {templateId && (
        <VersionHistoryPanel
          open={showHistory}
          entityType="template"
          entityId={templateId}
          onClose={() => setShowHistory(false)}
        />
      )}
    </div>
  );
}
