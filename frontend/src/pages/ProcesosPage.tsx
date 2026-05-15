import { useState } from 'react';
import { useLocation, useNavigate, useParams } from 'react-router-dom';
import { Button, ErrorBoundary, PageTitle } from '@maya/shared-ui-react';
import { createDataHook } from '@maya/shared-auth-react';
import { TemplatesTable } from '../features/templates/components/TemplatesTable';
import { DocumentsTable } from '../features/documents/components/DocumentsTable';
import { fetchProcesses } from '../api/processes';
import type { Process } from '../types/processes';

const useProcessesQuery = createDataHook<void, { data: Process[] }>({
  queryKey: () => ['processes'],
  fetcher: () => fetchProcesses(),
  defaultOptions: { staleTime: 60_000 },
});

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
  const { processId } = useParams<{ processId?: string }>();
  const locationState = location.state as { tab?: Tab } | null;
  const [activeTab, setActiveTab] = useState<Tab>(locationState?.tab ?? 'templates');

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
