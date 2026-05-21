import { useEffect, useState } from 'react';
import { useLocation, useNavigate, useParams } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { Button, ErrorBoundary, PageTitle } from '@maya/shared-ui-react';
import { TemplatesTable } from '../features/templates/components/TemplatesTable';
import { DocumentsTable } from '../features/documents/components/DocumentsTable';
import { useProcessesQuery } from '../hooks/useProcesses';

type Tab = 'templates' | 'documents';

const TAB_CLASS = (active: boolean) =>
  [
    'px-4 py-2 text-xs font-semibold transition-colors border-b-2 -mb-px cursor-pointer',
    active
      ? 'border-odoo-purple text-odoo-purple dark:border-odoo-dark-purple dark:text-odoo-dark-purple'
      : 'border-transparent text-text-muted dark:text-text-dark-muted hover:text-text-primary dark:hover:text-text-dark-primary',
  ].join(' ');

export function ProcesosPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const queryClient = useQueryClient();
  const { processId } = useParams<{ processId?: string }>();
  const locationState = location.state as { tab?: Tab; documentValidationBanner?: string } | null;
  const [activeTab, setActiveTab] = useState<Tab>(locationState?.tab ?? 'templates');
  const [validationBanner, setValidationBanner] = useState<string | null>(null);

  useEffect(() => {
    const banner = locationState?.documentValidationBanner;
    if (!banner) return;
    setValidationBanner(banner);
    if (locationState?.tab) setActiveTab(locationState.tab);
    void queryClient.invalidateQueries({ queryKey: ['documents'] });
    navigate(location.pathname, { replace: true, state: {} });
  }, [location.state, location.pathname, navigate, queryClient]);

  // Resuelve el proceso activo (para mostrar nombre en el header).
  const processesQuery = useProcessesQuery(undefined, { enabled: !!processId });
  const process: Process | null =
    processesQuery.data?.data.find((p) => p.id === processId) ?? null;
  const processLoading = !!processId && processesQuery.isLoading;

  const navState = processId ? { processId } : undefined;

  return (
    <>
      <PageTitle
        title={processId ? (process?.name ?? (processLoading ? 'Cargando proceso…' : 'Proceso')) : 'Procesos'}
        subtitle={process ? process.alias || process.code : undefined}
        actions={
          activeTab === 'templates' ? (
            <Button
              type="button"
              variant="primary"
              size="sm"
              onClick={() => navigate('/templates/new', { state: navState })}
            >
              Nueva Plantilla
            </Button>
          ) : (
            <Button
              type="button"
              variant="primary"
              size="sm"
              onClick={() => navigate('/documentos/nuevo', { state: navState })}
            >
              Nuevo Documento
            </Button>
          )
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
          <TemplatesTable processId={processId} />
        ) : (
          <DocumentsTable processId={processId} />
        )}
      </ErrorBoundary>
    </>
  );
}
