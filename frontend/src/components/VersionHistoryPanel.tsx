import { useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { Button } from '@ceedcv-maya/shared-ui-react';
import { ChangelogHtmlContent } from './ChangelogHtmlContent';
import { VersionComparePanel, type CompareVersionOption } from './VersionComparePanel';
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
  showNewVersionButton?: boolean;
  onNewVersion?: () => void;
};

const TRIGGER_EVENT_LABELS: Record<string, string> = {
  published: 'Publicación manual',
  auto_published: 'Publicación automática',
  manual_publish: 'Publicación manual',
  new_version: 'Nueva versión',
  review_approved: 'Aprobado por validador',
};

function formatTriggerEvent(raw: string): string {
  return TRIGGER_EVENT_LABELS[raw] ?? raw;
}

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

function IconPerson() {
  return (
    <svg className="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
    </svg>
  );
}

function IconCheck() {
  return (
    <svg className="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
  );
}

function IconCalendar() {
  return (
    <svg className="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
    </svg>
  );
}

function VersionBadge({ version }: { version: number }) {
  return (
    <span className="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-0.5 rounded-full bg-primary/10 text-primary-dark dark:text-primary-light border border-primary/20">
      v{version}
    </span>
  );
}

function MetaRow({ icon, label, value }: { icon: ReactNode; label: string; value: string }) {
  return (
    <div className="flex items-start gap-1.5 text-[11px] text-text-muted dark:text-text-dark-muted">
      <span className="mt-px text-text-secondary/60 dark:text-text-dark-secondary/60">{icon}</span>
      <span>
        <span className="font-semibold text-text-secondary dark:text-text-dark-secondary">{label}: </span>
        {value}
      </span>
    </div>
  );
}

export function VersionHistoryPanel({
  open,
  entityType,
  entityId,
  onClose,
  showNewVersionButton,
  onNewVersion,
}: Props) {
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

  const [view, setView] = useState<'list' | 'compare'>('list');
  useEffect(() => {
    if (!open) setView('list');
  }, [open]);

  const versionOptions = useMemo<CompareVersionOption[]>(
    () =>
      (entityType === 'template' ? templateRows : documentRows).map((r) => ({
        id: r.id,
        versionNumber: r.version_number,
      })),
    [entityType, templateRows, documentRows],
  );
  const canCompare = versionOptions.length >= 2;

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
        className="relative w-full max-w-md h-full bg-ui-card dark:bg-ui-dark-card border-l border-ui-border dark:border-ui-dark-border shadow-2xl flex flex-col animate-in slide-in-from-right-4"
      >
        <div className="flex items-center justify-between px-5 py-4 border-b border-ui-border dark:border-ui-dark-border shrink-0 gap-3">
          <div className="min-w-0">
            <h2 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary leading-none">
              {t('versionHistory.title')}
            </h2>
            {!loading && !error && (
              <p className="text-[11px] text-text-muted dark:text-text-dark-muted mt-0.5">
                {entityType === 'template' ? templateRows.length : documentRows.length} versión{(entityType === 'template' ? templateRows.length : documentRows.length) !== 1 ? 'es' : ''} publicada{(entityType === 'template' ? templateRows.length : documentRows.length) !== 1 ? 's' : ''}
              </p>
            )}
          </div>
          <div className="flex items-center gap-2 shrink-0">
            {showNewVersionButton && onNewVersion ? (
              <Button type="button" variant="primary" size="xs" onClick={() => { onNewVersion(); onClose(); }}>
                + Nueva versión
              </Button>
            ) : null}
            <Button type="button" variant="ghost" size="xs" onClick={onClose} aria-label="Cerrar">
              ✕
            </Button>
          </div>
        </div>

        {!loading && !error && canCompare && (
          <div className="shrink-0 px-4 pt-3 -mb-1">
            <div className="inline-flex rounded-lg border border-ui-border dark:border-ui-dark-border p-0.5 bg-ui-body/40 dark:bg-ui-dark-bg/40">
              <button
                type="button"
                onClick={() => setView('list')}
                className={`px-3 py-1 text-xs font-semibold rounded-md transition-colors cursor-pointer ${
                  view === 'list'
                    ? 'bg-white dark:bg-ui-dark-card text-text-primary dark:text-text-dark-primary shadow-sm'
                    : 'text-text-muted dark:text-text-dark-muted hover:text-text-primary dark:hover:text-text-dark-primary'
                }`}
                aria-pressed={view === 'list'}
              >
                {t('versionCompare.toggleList')}
              </button>
              <button
                type="button"
                onClick={() => setView('compare')}
                className={`px-3 py-1 text-xs font-semibold rounded-md transition-colors cursor-pointer ${
                  view === 'compare'
                    ? 'bg-white dark:bg-ui-dark-card text-text-primary dark:text-text-dark-primary shadow-sm'
                    : 'text-text-muted dark:text-text-dark-muted hover:text-text-primary dark:hover:text-text-dark-primary'
                }`}
                aria-pressed={view === 'compare'}
              >
                {t('versionCompare.toggleCompare')}
              </button>
            </div>
          </div>
        )}

        <div className="flex-1 overflow-y-auto px-4 py-4 min-h-0 space-y-0">
          {view === 'compare' && canCompare ? (
            <VersionComparePanel
              entityType={entityType}
              entityId={entityId}
              versions={versionOptions}
            />
          ) : (
          <>
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
            <ul className="space-y-2.5" role="list">
              {templateRows.map((row) => (
                <li key={row.id}>
                  <button
                    type="button"
                    className="w-full text-left rounded-xl border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card/80 px-4 py-3.5 hover:border-primary/40 hover:bg-primary/[0.03] dark:hover:bg-primary/5 hover:shadow-sm transition-all cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 group"
                    onClick={() => {
                      navigate(`/templates/${encodeURIComponent(entityId)}?templateVersionId=${encodeURIComponent(row.id)}`);
                      onClose();
                    }}
                    aria-label={t('versionHistory.previewTemplateAria', { n: row.version_number })}
                  >
                    <div className="flex items-center justify-between gap-2 mb-2.5">
                      <VersionBadge version={row.version_number} />
                      <span className="flex items-center gap-1 text-[11px] text-text-muted dark:text-text-dark-muted">
                        <IconCalendar />
                        {formatWhen(row.published_at, i18n.language)}
                      </span>
                    </div>

                    {row.changelog?.trim() ? (
                      <div className="mb-2.5 pb-2.5 border-b border-ui-border/50 dark:border-ui-dark-border/50">
                        <p className="text-2xs font-black uppercase tracking-widest text-text-muted dark:text-text-dark-muted mb-1">
                          {t('versionChangelog.label')}
                        </p>
                        <ChangelogHtmlContent html={row.changelog} variant="compact" />
                      </div>
                    ) : null}

                    <div className="space-y-1.5">
                      <MetaRow
                        icon={<IconPerson />}
                        label="Creador"
                        value={row.author_name?.trim() || t('status.unknown')}
                      />
                      <MetaRow
                        icon={<IconCheck />}
                        label="Publicado por"
                        value={row.published_by_name?.trim() || t('status.unknown')}
                      />
                      {row.reviewer_names && row.reviewer_names.length > 0 ? (
                        <MetaRow
                          icon={<IconPerson />}
                          label="Validadores"
                          value={row.reviewer_names.join(', ')}
                        />
                      ) : null}
                    </div>

                    <div className="mt-2.5 flex justify-end">
                      <span className="text-[10px] font-semibold text-primary/40 dark:text-primary-light/40 group-hover:text-primary dark:group-hover:text-primary-light transition-colors uppercase tracking-wider">
                        Ver versión →
                      </span>
                    </div>
                  </button>
                </li>
              ))}
            </ul>
          )}

          {!loading && !error && entityType === 'document' && documentRows.length > 0 && (
            <ul className="space-y-2.5" role="list">
              {documentRows.map((row) => (
                <li key={row.id}>
                  <button
                    type="button"
                    className="w-full text-left rounded-xl border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card/80 px-4 py-3.5 hover:border-primary/40 hover:bg-primary/[0.03] dark:hover:bg-primary/5 hover:shadow-sm transition-all cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 group"
                    onClick={() => {
                      navigate(
                        `/documents/${encodeURIComponent(entityId)}?documentVersionId=${encodeURIComponent(row.id)}`,
                      );
                      onClose();
                    }}
                    aria-label={t('versionHistory.previewDocumentAria', { n: row.version_number })}
                  >
                    <div className="flex items-center justify-between gap-2 mb-2.5">
                      <VersionBadge version={row.version_number} />
                      <span className="flex items-center gap-1 text-[11px] text-text-muted dark:text-text-dark-muted">
                        <IconCalendar />
                        {formatWhen(row.created_at, i18n.language)}
                      </span>
                    </div>

                    {row.trigger_event ? (
                      <p className="text-[11px] text-text-muted dark:text-text-dark-muted mb-2 italic">
                        {formatTriggerEvent(row.trigger_event)}
                      </p>
                    ) : null}

                    {(row.notes ?? row.changelog)?.trim() ? (
                      <div className="mb-2.5 pb-2.5 border-b border-ui-border/50 dark:border-ui-dark-border/50">
                        <p className="text-2xs font-black uppercase tracking-widest text-text-muted dark:text-text-dark-muted mb-1">
                          {t('versionChangelog.label')}
                        </p>
                        <ChangelogHtmlContent html={row.notes ?? row.changelog ?? ''} variant="compact" />
                      </div>
                    ) : null}

                    <div className="space-y-1.5">
                      <MetaRow
                        icon={<IconPerson />}
                        label="Creador"
                        value={row.author_name?.trim() || t('status.unknown')}
                      />
                      <MetaRow
                        icon={<IconCheck />}
                        label="Publicado por"
                        value={row.published_by_name?.trim() || t('status.unknown')}
                      />
                      {row.reviewer_names && row.reviewer_names.length > 0 ? (
                        <MetaRow
                          icon={<IconPerson />}
                          label="Validadores"
                          value={row.reviewer_names.join(', ')}
                        />
                      ) : null}
                    </div>

                    <div className="mt-2.5 flex justify-end">
                      <span className="text-[10px] font-semibold text-primary/40 dark:text-primary-light/40 group-hover:text-primary dark:group-hover:text-primary-light transition-colors uppercase tracking-wider">
                        Ver versión →
                      </span>
                    </div>
                  </button>
                </li>
              ))}
            </ul>
          )}
          </>
          )}
        </div>
      </aside>
    </div>
  );
}
