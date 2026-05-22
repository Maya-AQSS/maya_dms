import { useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { Button } from '@maya/shared-ui-react';
import { ApiHttpError } from '../api/http';
import type { DocumentVersionSummary } from '../api/documents';
import type { TemplateVersionSummary } from '../api/templates';
import { useDocumentVersionSummariesQuery } from '../features/documents/hooks/useDocumentVersionSummaries';
import { useTemplateVersionSummariesQuery } from '../features/templates/hooks/useTemplateVersionSummaries';

type Props = {
  open: boolean;
  entityType: 'template' | 'document';
  entityId: string;
  onClose: () => void;
};

function formatWhen(iso: string | null | undefined, locale: string): string {
  if (!iso) return '—';
  try {
    return new Date(iso).toLocaleString(locale, { dateStyle: 'short', timeStyle: 'short' });
  } catch {
    return iso;
  }
}

function fetchErrorMessage(e: unknown, fallback: string): string {
  if (e instanceof ApiHttpError) return e.message || `Error ${e.status}`;
  return fallback;
}

export function VersionHistoryPanel({ open, entityType, entityId, onClose }: Props) {
  const { t, i18n } = useTranslation('common');
  const navigate = useNavigate();

  const enabledTemplate = open && entityType === 'template' && !!entityId;
  const enabledDocument = open && entityType === 'document' && !!entityId;

  const templateQuery = useTemplateVersionSummariesQuery(entityId, {
    enabled: enabledTemplate,
  });
  const documentQuery = useDocumentVersionSummariesQuery(entityId, {
    enabled: enabledDocument,
  });

  const activeQuery = entityType === 'template' ? templateQuery : documentQuery;
  const loading = activeQuery.isLoading || activeQuery.isFetching;
  const error = activeQuery.error ? fetchErrorMessage(activeQuery.error, t('versionHistory.loadFailed')) : null;

  const templateRows = useMemo<TemplateVersionSummary[]>(
    () => (templateQuery.data ? [...templateQuery.data].sort((a, b) => b.version_number - a.version_number) : []),
    [templateQuery.data],
  );
  const documentRows = useMemo<DocumentVersionSummary[]>(
    () => (documentQuery.data ? [...documentQuery.data].sort((a, b) => b.version_number - a.version_number) : []),
    [documentQuery.data],
  );

  useEffect(() => {
    if (!open) return;
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [open, onClose]);

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
        aria-label={t('versionHistory.title')}
        className="relative w-full max-w-sm h-full bg-ui-card dark:bg-ui-dark-card border-l border-ui-border dark:border-ui-dark-border shadow-2xl flex flex-col animate-in slide-in-from-right-4"
      >
        <div className="flex items-center justify-between px-5 py-4 border-b border-ui-border dark:border-ui-dark-border shrink-0">
          <h2 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
            {t('versionHistory.title')}
          </h2>
          <Button type="button" variant="ghost" size="xs" onClick={onClose}>
            ✕
          </Button>
        </div>

        <div className="flex-1 overflow-y-auto px-4 py-3 min-h-0">
          {loading && (
            <p className="text-sm text-text-muted dark:text-text-dark-muted text-center py-8">
              {t('versionHistory.loading')}
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
                ? t('versionHistory.emptyTemplate')
                : t('versionHistory.emptyDocument')}
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
                    aria-label={t('versionHistory.previewTemplateAria', { n: row.version_number })}
                  >
                    <div className="flex items-baseline justify-between gap-2">
                      <span className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
                        v{row.version_number}
                      </span>
                      <span className="text-xs text-text-muted dark:text-text-dark-muted shrink-0">
                        {formatWhen(row.published_at, i18n.language)}
                      </span>
                    </div>
                    {row.changelog ? (
                      <p className="mt-1.5 text-xs text-text-secondary dark:text-text-dark-secondary leading-snug whitespace-pre-wrap">
                        {row.changelog}
                      </p>
                    ) : null}
                    <p className="mt-1 text-xs text-text-muted dark:text-text-dark-muted">
                      {t('versionHistory.publishedBy')}: {row.published_by_name ?? t('status.unknown')}
                    </p>
                    <p className="text-xs text-text-muted dark:text-text-dark-muted">
                      {t('versionHistory.versionAuthor')}: {row.author_name ?? t('status.unknown')}
                    </p>
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
                    aria-label={t('versionHistory.previewDocumentAria', { n: row.version_number })}
                  >
                    <div className="flex items-baseline justify-between gap-2">
                      <span className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
                        v{row.version_number}
                      </span>
                      <span className="text-xs text-text-muted dark:text-text-dark-muted shrink-0">
                        {formatWhen(row.created_at, i18n.language)}
                      </span>
                    </div>
                    <p className="mt-1 text-xs text-text-muted dark:text-text-dark-muted">
                      {row.trigger_event}
                    </p>
                    <p className="mt-1 text-xs text-text-muted dark:text-text-dark-muted">
                      {t('versionHistory.publishedBy')}: {row.published_by_name ?? t('status.unknown')}
                    </p>
                    <p className="text-xs text-text-muted dark:text-text-dark-muted">
                      {t('versionHistory.versionAuthor')}: {row.author_name ?? t('status.unknown')}
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
