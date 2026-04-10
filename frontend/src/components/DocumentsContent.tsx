import { useState, useMemo, useEffect } from 'react';
import { CascadeFilters } from './CascadeFilters';
import { useHierarchy } from '../features/hierarchy';
import { fetchDocuments } from '../api/documents';
import type { Document } from '../types/document';

type Filters = { studyTypeId: string; studyId: string; moduleId: string };

const STATUS_STYLES: Record<Document['status'], string> = {
  published: 'bg-success-light text-success-dark',
  draft:     'bg-warning-light text-warning-dark',
  in_review: 'bg-info-light text-info-dark',
};

const STATUS_LABELS: Record<Document['status'], string> = {
  published: 'Publicado',
  draft:     'Borrador',
  in_review: 'En revisión',
};

function StatusBadge({ status }: { status: Document['status'] }) {
  return (
    <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold ${STATUS_STYLES[status] ?? 'bg-ui-border text-text-secondary'}`}>
      {STATUS_LABELS[status] ?? status}
    </span>
  );
}

export function DocumentsContent() {
  const { hierarchy } = useHierarchy();

  const [documents, setDocuments] = useState<Document[]>([]);
  const [loadingDocs, setLoadingDocs] = useState(true);
  const [filters, setFilters] = useState<Filters>({ studyTypeId: '', studyId: '', moduleId: '' });
  const [isMobileOpen, setIsMobileOpen] = useState(false);

  useEffect(() => {
    let cancelled = false;
    fetchDocuments()
      .then(docs => { if (!cancelled) { setDocuments(docs); setLoadingDocs(false); } })
      .catch(() => { if (!cancelled) setLoadingDocs(false); });
    return () => { cancelled = true; };
  }, []);

  // Pre-compute the set of study IDs belonging to the selected type.
  // Using a Set makes the per-document type check O(1) instead of O(studies).
  const typeStudyIds = useMemo<Set<string>>(() => {
    if (!filters.studyTypeId) return new Set();
    const type = hierarchy.find(t => t.id === filters.studyTypeId);
    return new Set(type?.studies.map(s => s.id) ?? []);
  }, [hierarchy, filters.studyTypeId]);

  // Client-side filtering — no API calls. useMemo ensures the array is only
  // recomputed when documents or the active filters actually change.
  const filteredDocuments = useMemo<Document[]>(() => {
    return documents.filter(doc => {
      if (filters.studyTypeId && !typeStudyIds.has(doc.study_id)) return false;
      if (filters.studyId && doc.study_id !== filters.studyId) return false;
      if (filters.moduleId && doc.course_module_id !== filters.moduleId) return false;
      return true;
    });
  }, [documents, filters, typeStudyIds]);

  const activeCount = [filters.studyTypeId, filters.studyId, filters.moduleId].filter(Boolean).length;

  const handleClear = () => setFilters({ studyTypeId: '', studyId: '', moduleId: '' });
  const handleFilterChange = (f: Filters) => setFilters(f);

  return (
    <div className="p-6">
      {/* ── Mobile accordion toggle ────────────────────────────── */}
      <div className="md:hidden mb-4">
        <button
          type="button"
          onClick={() => setIsMobileOpen(prev => !prev)}
          aria-expanded={isMobileOpen}
          aria-controls="filter-panel"
          className="w-full flex items-center justify-between px-4 py-3 bg-ui-card dark:bg-ui-dark-card border border-ui-border dark:border-ui-dark-border rounded-lg shadow-sm text-sm font-medium text-text-primary dark:text-text-dark-primary"
        >
          <span className="flex items-center gap-2">
            Filtros
            {activeCount > 0 && (
              <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-odoo-purple/10 text-odoo-purple">
                {activeCount}
              </span>
            )}
          </span>
          <svg
            className={`w-4 h-4 transition-transform duration-200 ${isMobileOpen ? 'rotate-180' : ''}`}
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
          </svg>
        </button>
      </div>

      {/* ── Filters: accordion on mobile, always visible on md+ ── */}
      {/* CSS visibility (not unmount) so CascadeFilters keeps its selector state */}
      <div id="filter-panel" className={`${isMobileOpen ? 'block' : 'hidden'} md:block`}>
        <CascadeFilters onClear={handleClear} onFilterChange={handleFilterChange} />
      </div>

      {/* ── Document list ──────────────────────────────────────── */}
      <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card overflow-hidden">
        <div className="px-5 py-3 border-b border-ui-border-l dark:border-ui-dark-border-l flex items-center justify-between">
          <h2 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
            Programaciones Didácticas
          </h2>
          {!loadingDocs && (
            <span className="text-xs text-text-muted dark:text-text-dark-muted">
              {filteredDocuments.length} resultado{filteredDocuments.length !== 1 ? 's' : ''}
            </span>
          )}
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full">
            <thead className="bg-ui-body dark:bg-ui-dark-card">
              <tr>
                {['Título', 'Estudio / Módulo', 'Estado', 'Última mod.'].map(h => (
                  <th
                    key={h}
                    className="px-4 py-2 text-left text-xs uppercase tracking-wide text-text-secondary dark:text-text-dark-secondary font-medium"
                  >
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-ui-border-l dark:divide-ui-dark-border-l">
              {loadingDocs ? (
                <tr>
                  <td colSpan={4} className="px-4 py-8 text-center text-sm text-text-muted dark:text-text-dark-muted">
                    Cargando programaciones...
                  </td>
                </tr>
              ) : filteredDocuments.length === 0 ? (
                <tr>
                  <td colSpan={4} className="px-4 py-8 text-center text-sm text-text-muted dark:text-text-dark-muted">
                    No se encontraron programaciones con los filtros seleccionados.
                  </td>
                </tr>
              ) : (
                filteredDocuments.map(doc => (
                  <tr
                    key={doc.id}
                    className="hover:bg-ui-body dark:hover:bg-ui-dark-bg transition-colors"
                  >
                    <td className="px-4 py-3 text-sm font-medium text-text-primary dark:text-text-dark-primary whitespace-nowrap">
                      {doc.title}
                    </td>
                    <td className="px-4 py-3 whitespace-nowrap">
                      <div className="text-xs text-text-secondary dark:text-text-dark-secondary">{doc.study_name}</div>
                      <div className="text-xs text-text-muted dark:text-text-dark-muted">{doc.module_name}</div>
                    </td>
                    <td className="px-4 py-3 whitespace-nowrap">
                      <StatusBadge status={doc.status} />
                    </td>
                    <td className="px-4 py-3 text-sm text-text-muted dark:text-text-dark-muted whitespace-nowrap">
                      {doc.updated_at}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
