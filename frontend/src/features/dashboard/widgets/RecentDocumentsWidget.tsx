import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useLocation } from 'react-router-dom';
import { buildBackState } from '@ceedcv-maya/shared-hooks-react';
import { useUserProfile } from '../../user-profile';
import { DMS_PERMISSIONS } from '../../../permissions';
import { useDmsDashboard } from '../hooks/useDmsDashboard';
import { useDmsDashboardFilter } from '../hooks/useDmsDashboardFilter';
import type { DocumentReviewInboxItem, TemplateReviewInboxItem } from '../../../api/dashboard';

type PendingReviewItem =
  | {
      kind: 'template';
      id: string;
      title: string;
      daysRemaining: number | null;
    }
  | {
      kind: 'document';
      id: string;
      title: string;
      daysRemaining: number | null;
    };

/** Widget compacto: pendientes de validar (plantillas + documentos). */
export default function RecentDocumentsWidget() {
  const { t } = useTranslation('common');
  const location = useLocation();
  const { hasPermission } = useUserProfile();
  const canViewDashboard = hasPermission(DMS_PERMISSIONS.index);
  const canOpenFromDashboard = hasPermission(DMS_PERMISSIONS.show);
  const { filter } = useDmsDashboardFilter();
  const state = useDmsDashboard();

  const templateInbox = state.status === 'ready' ? state.data.template_review_inbox : undefined;
  const documentInbox = state.status === 'ready' ? state.data.document_review_inbox : undefined;

  const items = useMemo((): PendingReviewItem[] => {
    if (!templateInbox || !documentInbox) return [];

    const templateItems: PendingReviewItem[] = (templateInbox ?? []).map(
      (item: TemplateReviewInboxItem) => ({
        kind: 'template',
        id: item.template_id,
        title: item.title?.trim() || t('dashboard.widgets.templateUntitled', { defaultValue: 'Plantilla sin título' }),
        daysRemaining: item.days_remaining ?? null,
      }),
    );
    const documentItems: PendingReviewItem[] = (documentInbox ?? []).map(
      (item: DocumentReviewInboxItem) => ({
        kind: 'document',
        id: item.document_id,
        title: item.title?.trim() || t('dashboard.widgets.documentUntitled', { defaultValue: 'Documento sin título' }),
        daysRemaining: item.days_remaining ?? null,
      }),
    );

    return [...templateItems, ...documentItems].sort((a, b) => {
      const da = a.daysRemaining;
      const db = b.daysRemaining;
      if (da == null && db == null) return a.title.localeCompare(b.title);
      if (da == null) return 1;
      if (db == null) return -1;
      return da - db;
    });
  }, [templateInbox, documentInbox, t]);

  const formatRemaining = (daysRemaining: number | null): string => {
    if (daysRemaining == null) return '—';
    if (daysRemaining < 0)
      return t('dashboard.widgets.overdue', {
        count: Math.abs(daysRemaining),
        defaultValue: `Vencido (${Math.abs(daysRemaining)}d)`,
      });
    if (daysRemaining === 0)
      return t('dashboard.widgets.dueToday', { defaultValue: 'Vence hoy' });
    return t('dashboard.widgets.daysLeft', { count: daysRemaining, defaultValue: `${daysRemaining}d` });
  };

  if (!canViewDashboard) {
    return (
      <p className="text-sm text-text-secondary dark:text-text-dark-secondary py-4 text-center">
        {t('dashboard.noIndexPermission')}
      </p>
    );
  }

  if (state.status === 'loading') {
    return (
      <p className="text-sm text-text-secondary dark:text-text-dark-secondary py-4 text-center">
        {t('loading')}
      </p>
    );
  }

  if (state.status === 'error') {
    return (
      <p className="text-sm text-danger py-4 text-center">
        {t('dashboard.widgets.loadError', { defaultValue: 'No se pudieron cargar los documentos.' })}
      </p>
    );
  }

  if (items.length === 0) {
    return (
      <p className="text-sm text-text-secondary dark:text-text-dark-secondary py-4 text-center">
        {t('dashboard.widgets.noPending', { defaultValue: 'No tienes pendientes de validación.' })}
      </p>
    );
  }

  const visibleItems =
    filter === 'all' ? items : items.filter((item) => item.kind === filter);

  if (visibleItems.length === 0) {
    return (
      <p className="text-sm text-text-secondary dark:text-text-dark-secondary py-4 text-center">
        {t('dashboard.widgets.noFilteredPending', { defaultValue: 'No hay pendientes para ese filtro.' })}
      </p>
    );
  }

  return (
    <ul className="divide-y divide-ui-border-l dark:divide-ui-dark-border">
      {visibleItems.map((item) => {
        const target =
          item.kind === 'template'
            ? `/templates/${item.id}/review`
            : `/documents/${item.id}/validate`;
        const rowClass =
          'flex items-center justify-between gap-3 py-2 px-1 rounded transition-colors';
        const content = (
          <>
            <span className="min-w-0 flex items-center gap-2">
              <span
                className={[
                  'shrink-0 inline-flex items-center rounded px-1.5 py-0.5 text-2xs font-bold uppercase tracking-wide',
                  item.kind === 'template'
                    ? 'bg-odoo-purple/10 text-odoo-purple'
                    : 'bg-odoo-teal/10 text-odoo-teal',
                ].join(' ')}
              >
                {item.kind === 'template' ? 'TPL' : 'DOC'}
              </span>
              <span className="text-sm font-medium text-text-primary dark:text-text-dark-primary truncate">
                {item.title}
              </span>
            </span>
            <span className="text-xs text-text-muted dark:text-text-dark-muted shrink-0 tabular-nums">
              {formatRemaining(item.daysRemaining)}
            </span>
          </>
        );

        if (!canOpenFromDashboard) {
          return (
            <li
              key={`${item.kind}:${item.id}`}
              className={`${rowClass} opacity-60 cursor-not-allowed`}
              title={t('dashboard.noShowPermission')}
            >
              {content}
            </li>
          );
        }

        return (
          <li key={`${item.kind}:${item.id}`}>
            <Link
              to={target}
              state={buildBackState(location)}
              className={`${rowClass} hover:bg-ui-body dark:hover:bg-ui-dark-bg`}
            >
              {content}
            </Link>
          </li>
        );
      })}
    </ul>
  );
}
