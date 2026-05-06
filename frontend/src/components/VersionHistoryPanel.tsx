import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button } from '@maya/shared-ui-react';
import { ApiHttpError } from '../api/http';
import { fetchDocumentVersionSummaries, type DocumentVersionSummary } from '../api/documents';
import { fetchTemplateVersionSummaries, type TemplateVersionSummary } from '../api/templates';

type Props = {
  open: boolean;
  entityType: 'template' | 'document';
  entityId: string;
  onClose: () => void;
};

function formatWhen(iso: string | null | undefined): string {
  if (!iso) return '—';
  try {
    return new Date(iso).toLocaleString('es-ES', { dateStyle: 'short', timeStyle: 'short' });
  } catch {
    return iso;
  }
}

export function VersionHistoryPanel({ open, entityType, entityId, onClose }: Props) {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [templateRows, setTemplateRows] = useState<TemplateVersionSummary[]>([]);
  const [documentRows, setDocumentRows] = useState<DocumentVersionSummary[]>([]);

  useEffect(() => {
    if (!open) return;
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [open, onClose]);

  useEffect(() => {
    if (!open || !entityId) return;

    let cancelled = false;
    setLoading(true);
    setError(null);
    setTemplateRows([]);
    setDocumentRows([]);

    void (async () => {
      try {
        if (entityType === 'template') {
          const rows = await fetchTemplateVersionSummaries(entityId);
          if (!cancelled) {
            setTemplateRows(
              [...rows].sort((a, b) => b.version_number - a.version_number),
            );
          }
        } else {
          const rows = await fetchDocumentVersionSummaries(entityId);
          if (!cancelled) {
            setDocumentRows(
              [...rows].sort((a, b) => b.version_number - a.version_number),
            );
          }
        }
      } catch (e) {
        if (!cancelled) {
          const msg =
            e instanceof ApiHttpError
              ? e.message || `Error ${e.status}`
              : 'No se pudo cargar el historial.';
          setError(msg);
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [open, entityType, entityId]);

  const empty = useMemo(() => {
    if (entityType === 'template') return templateRows.length === 0;
    return documentRows.length === 0;
  }, [entityType, templateRows.length, documentRows.length]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[300] flex justify-end" role="presentation">
      <div
        className="absolute inset-0 bg-black/30 backdrop-blur-sm"
        onClick={onClose}
        aria-hidden="true"
      />
      <aside
        role="dialog"
        aria-modal="true"
        aria-label="Historial de versiones"
        className="relative w-full max-w-sm h-full bg-ui-card dark:bg-ui-dark-card border-l border-ui-border dark:border-ui-dark-border shadow-2xl flex flex-col animate-in slide-in-from-right-4"
      >
        <div className="flex items-center justify-between px-5 py-4 border-b border-ui-border dark:border-ui-dark-border shrink-0">
          <h2 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
            Historial de versiones
          </h2>
          <Button type="button" variant="ghost" size="xs" onClick={onClose}>
            ✕
          </Button>
        </div>

        <div className="flex-1 overflow-y-auto px-4 py-3 min-h-0">
          {loading && (
            <p className="text-sm text-text-muted dark:text-text-dark-muted text-center py-8">
              Cargando historial…
            </p>
          )}
          {!loading && error && (
            <p className="text-sm text-warning-dark dark:text-warning-light text-center py-6 px-2">
              {error}
            </p>
          )}
          {!loading && !error && empty && (
            <p className="text-sm text-text-muted dark:text-text-dark-muted text-center py-8 leading-relaxed">
              {entityType === 'template'
                ? 'No hay versiones publicadas registradas para esta plantilla.'
                : 'No hay versiones publicadas registradas para este documento.'}
            </p>
          )}
          {!loading && !error && entityType === 'template' && templateRows.length > 0 && (
            <ul className="space-y-3" role="list">
              {templateRows.map((row) => (
                <li key={row.id}>
                  <button
                    type="button"
                    className="w-full text-left rounded-lg border border-ui-border dark:border-ui-dark-border bg-white/80 dark:bg-ui-dark-card/70 px-3 py-2.5 hover:bg-white dark:hover:bg-ui-dark-card hover:border-text-muted/30 transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                    onClick={() => {
                      navigate(`/templates/${encodeURIComponent(entityId)}?templateVersionId=${encodeURIComponent(row.id)}`);
                      onClose();
                    }}
                    aria-label={`Ver vista previa de la plantilla en la versión ${row.version_number}`}
                  >
                    <div className="flex items-baseline justify-between gap-2">
                      <span className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
                        v{row.version_number}
                      </span>
                      <span className="text-xs text-text-muted dark:text-text-dark-muted shrink-0">
                        {formatWhen(row.published_at)}
                      </span>
                    </div>
                    {row.changelog ? (
                      <p className="mt-1.5 text-xs text-text-secondary dark:text-text-dark-secondary leading-snug whitespace-pre-wrap">
                        {row.changelog}
                      </p>
                    ) : null}
                  </button>
                </li>
              ))}
            </ul>
          )}
          {!loading && !error && entityType === 'document' && documentRows.length > 0 && (
            <ul className="space-y-3" role="list">
              {documentRows.map((row) => (
                <li key={row.id}>
                  <button
                    type="button"
                    className="w-full text-left rounded-lg border border-ui-border dark:border-ui-dark-border bg-white/80 dark:bg-ui-dark-card/70 px-3 py-2.5 hover:bg-white dark:hover:bg-ui-dark-card hover:border-text-muted/30 transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                    onClick={() => {
                      navigate(
                        `/documents/${encodeURIComponent(entityId)}?documentVersionId=${encodeURIComponent(row.id)}`,
                      );
                      onClose();
                    }}
                    aria-label={`Ver vista previa del documento en la versión ${row.version_number}`}
                  >
                    <div className="flex items-baseline justify-between gap-2">
                      <span className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
                        v{row.version_number}
                      </span>
                      <span className="text-xs text-text-muted dark:text-text-dark-muted shrink-0">
                        {formatWhen(row.created_at)}
                      </span>
                    </div>
                    <p className="mt-1 text-xs text-text-muted dark:text-text-dark-muted">
                      {row.trigger_event}
                      {row.triggered_by ? ` · ${row.triggered_by}` : ''}
                    </p>
                    {(row.notes ?? row.changelog) ? (
                      <p className="mt-1.5 text-xs text-text-secondary dark:text-text-dark-secondary leading-snug whitespace-pre-wrap">
                        {row.notes ?? row.changelog}
                      </p>
                    ) : null}
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      </aside>
    </div>
  );
}
