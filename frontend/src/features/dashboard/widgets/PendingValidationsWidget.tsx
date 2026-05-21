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
    <div className="w-full h-full rounded-xl bg-gradient-to-br from-odoo-purple/15 via-odoo-purple/5 to-transparent dark:from-odoo-dark-teal/25 dark:via-odoo-dark-teal/10 dark:to-transparent p-4">
      <div className="flex items-baseline gap-2">
        <span className="text-xs uppercase tracking-wide font-medium text-text-secondary dark:text-text-dark-secondary">
          Pendientes de validar
        </span>
        <button
          type="button"
          onClick={() => emitFilter('all')}
          disabled={loading}
          className="text-4xl font-bold tabular-nums bg-gradient-to-br from-odoo-purple to-odoo-purple-d dark:from-odoo-dark-teal dark:to-odoo-dark-teal-d bg-clip-text text-transparent leading-none enabled:cursor-pointer disabled:opacity-70"
          aria-label="Mostrar todos los pendientes de validar"
          title="Mostrar todos"
        >
          {loading ? '…' : error ? '—' : (count ?? 0)}
        </button>
      </div>
      {error ? (
        <span className="mt-1 text-xs text-text-muted dark:text-text-dark-muted">
          Datos no disponibles
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
            Plantillas: {loading ? '…' : templateCount ?? 0}
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
            Documentos: {loading ? '…' : documentCount ?? 0}
          </button>
        </div>
      )}
    </div>
  );
}
