import { useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { Button, ErrorBoundary, PageTitle } from '@maya/shared-ui-react';
import { TemplatesTable } from '../features/templates/components/TemplatesTable';
import { DocumentsTable } from '../features/documents/components/DocumentsTable';

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
  const locationState = location.state as { tab?: Tab } | null;
  const [activeTab, setActiveTab] = useState<Tab>(locationState?.tab ?? 'templates');

  return (
    <>
      <PageTitle
        title="Procesos"
        actions={
          activeTab === 'templates' ? (
            <Button type="button" variant="primary" size="sm" onClick={() => navigate('/templates/new')}>
              Nueva Plantilla
            </Button>
          ) : (
            <Button type="button" variant="primary" size="sm" onClick={() => navigate('/nueva-programacion')}>
              Nueva Programación
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

      <ErrorBoundary key={activeTab}>
        {activeTab === 'templates' ? <TemplatesTable /> : <DocumentsTable />}
      </ErrorBoundary>
    </>
  );
}
