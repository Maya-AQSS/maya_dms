import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocation, useNavigate, useParams } from 'react-router-dom';
import { Alert, Button, ErrorBoundary, PageTitle } from '@maya/shared-ui-react';
import { TemplatesTable } from '../features/templates/components/TemplatesTable';
import { DocumentsTable } from '../features/documents/components/DocumentsTable';
import { useUserProfile } from '../features/user-profile';
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
  const { processId } = useParams<{ processId?: string }>();
  const { hasPermission } = useUserProfile();
  const canIndex = hasPermission(DMS_PERMISSIONS.processIndex);
  const canShow = hasPermission(DMS_PERMISSIONS.processShow);
  const locationState = location.state as { tab?: Tab } | null;
  const [activeTab, setActiveTab] = useState<Tab>(locationState?.tab ?? 'templates');

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

      {processId && !canShow && (
        <Alert tone="warning" className="mb-4">
          {t('processes.noShowPermission')}
        </Alert>
      )}

      <ErrorBoundary key={`${activeTab}:${processId ?? 'all'}`}>
        {activeTab === 'templates' ? (
          <TemplatesTable processId={canShow ? processId : undefined} />
        ) : (
          <DocumentsTable processId={canShow ? processId : undefined} />
        )}
      </ErrorBoundary>
    </>
  );
}
