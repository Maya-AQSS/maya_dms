import { useState } from 'react';
import { createDataHook } from '@maya/shared-auth-react';
import { fetchDashboard } from '../../../api/dashboard';
import type { DashboardPayload } from '../../../api/dashboard';

const useDashboardQuery = createDataHook<void, DashboardPayload>({
  queryKey: () => ['dashboard'],
  fetcher: () => fetchDashboard(),
  defaultOptions: { staleTime: 30_000 },
});

/** Widget StatCard: nº de documentos pendientes de validación del usuario. */
export default function PendingValidationsWidget() {
  const [activeFilter, setActiveFilter] = useState<'all' | 'template' | 'document'>('all');
  const { data, isLoading, isError } = useDashboardQuery();

  const templateCount = data?.template_review_inbox?.length ?? 0;
  const documentCount = data?.document_review_inbox?.length ?? 0;
  const count = data ? templateCount + documentCount : null;
  const loading = isLoading;
  const error = isError;

  const emitFilter = (filter: 'all' | 'template' | 'document') => {
    setActiveFilter(filter);
    window.dispatchEvent(
      new CustomEvent('maya:dms:pending-validations-filter-change', {
        detail: { filter },
      }),
    );
  };

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
            disabled={loading || templateCount === 0}
            className={[
              'text-left disabled:opacity-60 disabled:cursor-not-allowed',
              activeFilter === 'template'
                ? 'text-text-primary dark:text-text-dark-primary font-semibold'
                : 'text-text-muted dark:text-text-dark-muted enabled:hover:text-text-primary dark:enabled:hover:text-text-dark-primary',
            ].join(' ')}
          >
            Plantillas: {loading ? '…' : templateCount}
          </button>
          <button
            type="button"
            onClick={() => emitFilter('document')}
            disabled={loading || documentCount === 0}
            className={[
              'text-left disabled:opacity-60 disabled:cursor-not-allowed',
              activeFilter === 'document'
                ? 'text-text-primary dark:text-text-dark-primary font-semibold'
                : 'text-text-muted dark:text-text-dark-muted enabled:hover:text-text-primary dark:enabled:hover:text-text-dark-primary',
            ].join(' ')}
          >
            Documentos: {loading ? '…' : documentCount}
          </button>
        </div>
      )}
    </div>
  );
}
