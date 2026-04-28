import { useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { Button } from '../ui';
import { ErrorBoundary } from '../components/ErrorBoundary';
import { TemplatesTable } from '../features/templates/components/TemplatesTable';
import { DocumentsTable } from '../features/documents/components/DocumentsTable';

type Tab = 'templates' | 'documents';

export function ProcesosPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const locationState = location.state as { tab?: Tab } | null;
  const [activeTab, setActiveTab] = useState<Tab>(locationState?.tab ?? 'templates');

  return (
    <div className="p-6 space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
            Procesos
          </h2>
          <p className="text-xs text-text-muted dark:text-text-dark-muted mt-1">
            Plantillas normativas y programaciones didácticas.
          </p>
        </div>
        {activeTab === 'templates' ? (
          <Button
            type="button"
            variant="primary"
            size="sm"
            onClick={() => navigate('/templates/new')}
          >
            Nueva Plantilla
          </Button>
        ) : (
          <Button
            type="button"
            variant="primary"
            size="sm"
            onClick={() => navigate('/documents')}
          >
            Nueva Programación
          </Button>
        )}
      </div>

      <div className="flex gap-1 border-b border-ui-border dark:border-ui-dark-border">
        <button
          type="button"
          onClick={() => setActiveTab('templates')}
          className={[
            'px-4 py-2 text-xs font-semibold transition-colors border-b-2 -mb-px cursor-pointer',
            activeTab === 'templates'
              ? 'border-odoo-purple text-odoo-purple dark:border-odoo-dark-purple dark:text-odoo-dark-purple'
              : 'border-transparent text-text-muted dark:text-text-dark-muted hover:text-text-primary dark:hover:text-text-dark-primary',
          ].join(' ')}
        >
          Plantillas
        </button>
        <button
          type="button"
          onClick={() => setActiveTab('documents')}
          className={[
            'px-4 py-2 text-xs font-semibold transition-colors border-b-2 -mb-px cursor-pointer',
            activeTab === 'documents'
              ? 'border-odoo-purple text-odoo-purple dark:border-odoo-dark-purple dark:text-odoo-dark-purple'
              : 'border-transparent text-text-muted dark:text-text-dark-muted hover:text-text-primary dark:hover:text-text-dark-primary',
          ].join(' ')}
        >
          Documentos
        </button>
      </div>

      <ErrorBoundary key={activeTab}>
        {activeTab === 'templates' ? <TemplatesTable /> : <DocumentsTable />}
      </ErrorBoundary>
    </div>
  );
}
