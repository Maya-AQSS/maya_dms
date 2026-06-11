import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { buildBackState } from '@ceedcv-maya/shared-hooks-react';
import { useQueryClient } from '@tanstack/react-query';
import { Alert, Button, PageTitle } from '@ceedcv-maya/shared-ui-react';
import { ErrorBoundaryWrapper as ErrorBoundary } from '../components/ErrorBoundaryWrapper';
import { TemplatesTable } from '../features/templates/components/TemplatesTable';
import { TemplatesTableBoundary } from '../features/templates/components/TemplatesTableBoundary';
import { DocumentsTable } from '../features/documents/components/DocumentsTable';
import { useUserProfile } from '../features/user-profile';
import { refreshDmsDashboardQuery } from '../features/dashboard/hooks/useDmsDashboard';
import { useProcessesQuery } from '../hooks/useProcesses';
import { DMS_PERMISSIONS } from '../permissions';
import type { Process } from '../types/processes';

type Tab = 'templates' | 'documents';

const TAB_CLASS = (active: boolean) =>
  [
    'px-4 py-2 text-xs font-semibold transition-colors border-b-2 -mb-px cursor-pointer',
    active
      ? 'border-odoo-purple text-odoo-purple dark:border-odoo-dark-purple dark:text-odoo-dark-purple'
      : 'border-transparent text-text-muted dark:text-text-dark-muted hover:text-text-primary dark:hover:text-text-dark-primary',
  ].join(' ');

export function ProcesosPage() {
  const { t } = useTranslation('common');
  const navigate = useNavigate();
  const location = useLocation();
  const queryClient = useQueryClient();
  const { processId } = useParams<{ processId?: string }>();
  const { hasPermission } = useUserProfile();
  const canIndex = hasPermission(DMS_PERMISSIONS.processIndex);
  const canShow = hasPermission(DMS_PERMISSIONS.processShow);
  const canCreateDocument = hasPermission(DMS_PERMISSIONS.documentCreate);
  const locationState = location.state as { tab?: Tab; documentValidationBanner?: string } | null;
  // La pestaña activa vive en la URL (?tab=) para que el botón Volver de los
  // detalles restaure la pestaña correcta junto con los filtros del listado.
  const [searchParams, setSearchParams] = useSearchParams();
  const urlTab = searchParams.get('tab');
  const activeTab: Tab =
    urlTab === 'documents' || urlTab === 'templates'
      ? urlTab
      : (locationState?.tab ?? 'templates');
  const setActiveTab = (tab: Tab) => {
    setSearchParams(
      (prev) => {
        const next = new URLSearchParams(prev);
        next.set('tab', tab);
        return next;
      },
      { replace: true },
    );
  };
  const [validationBanner, setValidationBanner] = useState<string | null>(null);

  useEffect(() => {
    const banner = locationState?.documentValidationBanner;
    if (!banner) return;
    setValidationBanner(banner);
    void (async () => {
      await queryClient.invalidateQueries({ queryKey: ['documents'] });
      await refreshDmsDashboardQuery(queryClient);
    })();
    // Limpia el state (banner) preservando la búsqueda y fijando la pestaña pedida.
    const nextSearch = new URLSearchParams(location.search);
    if (locationState?.tab) nextSearch.set('tab', locationState.tab);
    navigate(
      { pathname: location.pathname, search: nextSearch.toString() },
      { replace: true, state: {} },
    );
  }, [location.state, location.pathname, location.search, locationState, navigate, queryClient]);
  
  const processesQuery = useProcessesQuery(undefined, { enabled: !!processId && canShow });
  const process: Process | null =
    processesQuery.data?.data.find((p) => p.id === processId) ?? null;
  const processLoading = !!processId && canShow && processesQuery.isLoading;

  useEffect(() => {
    if (!processId || canShow) {
      return;
    }
    navigate('/dashboard', { replace: true });
  }, [processId, canShow, navigate]);

  const navState = processId ? { processId } : undefined;

  if (!canIndex) {
    return (
      <Alert tone="warning">
        {t('processes.noIndexPermission')}
      </Alert>
    );
  }

  return (
    <>
      <PageTitle
        title={processId ? (process?.name ?? (processLoading ? 'Cargando proceso…' : 'Proceso')) : 'Procesos'}
        subtitle={process ? process.alias || process.code : undefined}
        actions={
          !processId ? null : activeTab === 'templates' ? (
            <Button
              type="button"
              variant="primary"
              size="sm"
              onClick={() => navigate('/templates/new', { state: navState })}
            >
              Nueva Plantilla
            </Button>
          ) : canCreateDocument ? (
            <Button
              type="button"
              variant="primary"
              size="sm"
              onClick={() => navigate('/documentos/nuevo', { state: { ...navState, ...buildBackState(location) } })}
            >
              Nuevo Documento
            </Button>
          ) : null
        }
        meta={
          <div className="flex gap-1 border-b border-ui-border dark:border-ui-dark-border">
            <button type="button" onClick={() => setActiveTab('templates')} className={TAB_CLASS(activeTab === 'templates')}>
              Plantillas
            </button>
            <button type="button" onClick={() => setActiveTab('documents')} className={TAB_CLASS(activeTab === 'documents')}>
              Documentos
            </button>
          </div>
        }
      />

      {processId && !canShow && (
        <Alert tone="warning" className="mb-4">
          {t('processes.noShowPermission')}
        </Alert>
      )}

      {validationBanner && (
        <div
          className="mb-4 flex items-center justify-between gap-3 rounded-lg border border-success/30 bg-success/10 px-4 py-3 dark:bg-success/15 dark:border-success/40"
          role="status"
        >
          <p className="text-sm font-medium text-success-dark dark:text-success">
            {validationBanner}
          </p>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="shrink-0 text-success-dark dark:text-success"
            onClick={() => setValidationBanner(null)}
          >
            Cerrar
          </Button>
        </div>
      )}

      <ErrorBoundary key={`${activeTab}:${processId ?? 'all'}`}>
        {activeTab === 'templates' ? (
          <TemplatesTableBoundary>
            <TemplatesTable processId={canShow ? processId : undefined} />
          </TemplatesTableBoundary>
        ) : (
          <DocumentsTable processId={canShow ? processId : undefined} />
        )}
      </ErrorBoundary>
    </>
  );
}
