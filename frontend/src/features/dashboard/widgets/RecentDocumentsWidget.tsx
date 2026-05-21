import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { useUserProfile } from '../../user-profile';
import { DMS_PERMISSIONS } from '../../../permissions';
import { useDmsDashboard } from '../hooks/useDmsDashboard';
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
  const { hasPermission } = useUserProfile();
  const canViewDashboard = hasPermission(DMS_PERMISSIONS.index);
  const canOpenFromDashboard = hasPermission(DMS_PERMISSIONS.show);
  const [filter, setFilter] = useState<'all' | 'template' | 'document'>('all');
  const state = useDmsDashboard();

  const items = useMemo((): PendingReviewItem[] => {
    if (state.status !== 'ready') {
      return [];
    }

    const templateItems: PendingReviewItem[] = (state.data.template_review_inbox ?? []).map(
      (item: TemplateReviewInboxItem) => ({
        kind: 'template',
        id: item.template_id,
        title: item.title?.trim() || 'Plantilla sin título',
        daysRemaining: item.days_remaining ?? null,
      }),
    );
    const documentItems: PendingReviewItem[] = (state.data.document_review_inbox ?? []).map(
      (item: DocumentReviewInboxItem) => ({
        kind: 'document',
        id: item.document_id,
        title: item.title?.trim() || 'Documento sin título',
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
  }, [state]);

  useEffect(() => {
    const handleFilterChange = (event: Event) => {
      const customEvent = event as CustomEvent<{ filter?: 'all' | 'template' | 'document' }>;
      const nextFilter = customEvent.detail?.filter ?? 'all';
      setFilter(nextFilter);
    };
    window.addEventListener('maya:dms:pending-validations-filter-change', handleFilterChange as EventListener);
    return () => {
      window.removeEventListener('maya:dms:pending-validations-filter-change', handleFilterChange as EventListener);
    };
  }, []);

  const formatRemaining = (daysRemaining: number | null): string => {
    if (daysRemaining == null) return '—';
    if (daysRemaining < 0) return `Vencido (${Math.abs(daysRemaining)}d)`;
    if (daysRemaining === 0) return 'Vence hoy';
    return `${daysRemaining}d`;
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
        Cargando…
      </p>
    );
  }

  if (state.status === 'error') {
    return (
      <p className="text-sm text-danger py-4 text-center">
        No se pudieron cargar los documentos.
      </p>
    );
  }

  if (items.length === 0) {
    return (
      <p className="text-sm text-text-secondary dark:text-text-dark-secondary py-4 text-center">
        No tienes pendientes de validación.
      </p>
    );
  }

  const visibleItems =
    filter === 'all' ? items : items.filter((item) => item.kind === filter);

  if (visibleItems.length === 0) {
    return (
      <p className="text-sm text-text-secondary dark:text-text-dark-secondary py-4 text-center">
        No hay pendientes para ese filtro.
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
                  'shrink-0 inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide',
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
              state={{ backTo: '/dashboard' }}
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
