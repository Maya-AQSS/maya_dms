import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useUserProfile } from '../../user-profile';
import { DMS_PERMISSIONS } from '../../../permissions';
import { useDmsDashboard } from '../hooks/useDmsDashboard';

/** Widget StatCard: nº de documentos pendientes de validación del usuario. */
export default function PendingValidationsWidget() {
  const { t } = useTranslation('common');
  const { hasPermission } = useUserProfile();
  const canViewDashboard = hasPermission(DMS_PERMISSIONS.index);
  const [activeFilter, setActiveFilter] = useState<'all' | 'template' | 'document'>('all');
  const state = useDmsDashboard();

  const templateCount =
    state.status === 'ready' ? (state.data.template_review_inbox?.length ?? 0) : null;
  const documentCount =
    state.status === 'ready' ? (state.data.document_review_inbox?.length ?? 0) : null;
  const count =
    templateCount != null && documentCount != null ? templateCount + documentCount : null;
  const loading = state.status === 'loading';
  const error = state.status === 'error';

  const emitFilter = (filter: 'all' | 'template' | 'document') => {
    if (!canViewDashboard) {
      return;
    }
    setActiveFilter(filter);
    window.dispatchEvent(
      new CustomEvent('maya:dms:pending-validations-filter-change', {
        detail: { filter },
      }),
    );
  };

  if (!canViewDashboard) {
    return (
      <p className="text-sm text-text-secondary dark:text-text-dark-secondary py-4 text-center">
        {t('dashboard.noIndexPermission')}
      </p>
    );
  }

  return (
    <div className="w-full h-full rounded-xl bg-card-tinted-accent p-4">
      <div className="flex items-baseline gap-2">
        <span className="text-xs uppercase tracking-wider font-semibold text-text-secondary dark:text-text-dark-secondary">
          {t('dashboard.widgets.pendingValidations')}
        </span>
        <button
          type="button"
          onClick={() => emitFilter('all')}
          disabled={loading}
          className="text-4xl font-bold tabular-nums text-gradient-primary leading-none enabled:cursor-pointer disabled:opacity-70"
          aria-label={t('dashboard.widgets.showAllPendingAria')}
          title={t('dashboard.widgets.showAllTitle')}
        >
          {loading ? '…' : error ? '—' : (count ?? 0)}
        </button>
      </div>
      {error ? (
        <span className="mt-1 text-xs text-text-muted dark:text-text-dark-muted">
          {t('dashboard.widgets.dataUnavailable')}
        </span>
      ) : (
        <div className="mt-2 flex flex-col gap-1.5 text-xs">
          <button
            type="button"
            onClick={() => emitFilter('template')}
            disabled={loading || (templateCount ?? 0) === 0}
            className={[
              'text-left disabled:opacity-60 disabled:cursor-not-allowed',
              activeFilter === 'template'
                ? 'text-text-primary dark:text-text-dark-primary font-semibold'
                : 'text-text-muted dark:text-text-dark-muted enabled:hover:text-text-primary dark:enabled:hover:text-text-dark-primary',
            ].join(' ')}
          >
            {t('dashboard.widgets.templatesLabel')}: {loading ? '…' : templateCount ?? 0}
          </button>
          <button
            type="button"
            onClick={() => emitFilter('document')}
            disabled={loading || (documentCount ?? 0) === 0}
            className={[
              'text-left disabled:opacity-60 disabled:cursor-not-allowed',
              activeFilter === 'document'
                ? 'text-text-primary dark:text-text-dark-primary font-semibold'
                : 'text-text-muted dark:text-text-dark-muted enabled:hover:text-text-primary dark:enabled:hover:text-text-dark-primary',
            ].join(' ')}
          >
            {t('dashboard.widgets.documentsLabel')}: {loading ? '…' : documentCount ?? 0}
          </button>
        </div>
      )}
    </div>
  );
}
