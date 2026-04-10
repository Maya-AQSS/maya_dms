import { useState, useTransition } from 'react';
import { CascadeFilters } from './CascadeFilters';
import {
  useDocuments,
  useFilteredDocuments,
  type CascadeDocumentFilters,
} from '../features/documents';
import { useHierarchy } from '../features/hierarchy';
import type { DocumentStatus } from '../types/documents';

const STATUS_LABELS: Record<DocumentStatus, string> = {
  draft: 'Borrador',
  in_review: 'En revisión',
  published: 'Publicado',
};

const STATUS_CLASS: Record<DocumentStatus, string> = {
  published:
    'bg-odoo-teal/10 text-odoo-teal dark:bg-odoo-dark-teal/20 dark:text-odoo-dark-teal',
  in_review:
    'bg-warning-light text-warning-dark dark:bg-warning-dark/20 dark:text-warning-light',
  draft:
    'bg-ui-border dark:bg-ui-dark-border text-text-secondary dark:text-text-dark-secondary',
};

/**
 * Componente para mostrar el contenido de los documentos.
 */
export function DocumentsContent() {
  const [activeFilters, setActiveFilters] = useState<CascadeDocumentFilters>({
    studyTypeId: '',
    studyId: '',
    moduleId: '',
  });
  const [, startTransition] = useTransition();

  const { documents, loading, error } = useDocuments();
  const { hierarchy } = useHierarchy();
  const filtered = useFilteredDocuments(documents, activeFilters, hierarchy);

  const handleClear = () =>
    startTransition(() =>
      setActiveFilters({ studyTypeId: '', studyId: '', moduleId: '' })
    );

  const handleChange = (filters: CascadeDocumentFilters) =>
    startTransition(() => setActiveFilters(filters));

  return (
    <div className="p-6">
      <CascadeFilters onClear={handleClear} onFilterChange={handleChange} />

      <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card overflow-hidden">
        <div className="px-5 py-3 border-b border-ui-border-l dark:border-ui-dark-border-l flex items-center justify-between">
          <h2 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
            Programaciones Didácticas
          </h2>
          {!loading && (
            <span className="text-xs text-text-muted dark:text-text-dark-muted">
              {filtered.length} {filtered.length === 1 ? 'documento' : 'documentos'}
            </span>
          )}
        </div>

        <div className="divide-y divide-ui-border-l dark:divide-ui-dark-border-l">
          {loading && (
            <p className="px-5 py-4 text-sm text-text-muted dark:text-text-dark-muted">
              Cargando documentos…
            </p>
          )}
          {error && (
            <p className="px-5 py-4 text-sm text-warning-dark dark:text-warning-light">
              Error al cargar documentos: {error.message}
            </p>
          )}
          {!loading && !error && filtered.length === 0 && (
            <p className="px-5 py-8 text-sm text-center text-text-muted dark:text-text-dark-muted">
              No hay programaciones didácticas con los filtros actuales.
            </p>
          )}
          {filtered.map((doc) => (
            <div
              key={doc.id}
              className="px-5 py-3 flex items-center justify-between gap-4 hover:bg-ui-body dark:hover:bg-ui-dark-bg transition-colors"
            >
              <div className="min-w-0">
                <p className="text-sm font-medium text-text-primary dark:text-text-dark-primary truncate">
                  {doc.title}
                </p>
                <p className="text-xs text-text-muted dark:text-text-dark-muted mt-0.5">
                  v{doc.current_version}
                </p>
              </div>
              <span
                className={`shrink-0 text-xs font-medium px-2 py-0.5 rounded-full ${STATUS_CLASS[doc.status]}`}
              >
                {STATUS_LABELS[doc.status]}
              </span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
