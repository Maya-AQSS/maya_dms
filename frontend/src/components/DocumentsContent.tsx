import { useState } from 'react';
import { CascadeFilters } from './CascadeFilters';

export function DocumentsContent() {
  const [activeFilters, setActiveFilters] = useState<{
    studyTypeId: string;
    studyId: string;
    moduleId: string;
  }>({
    studyTypeId: '',
    studyId: '',
    moduleId: ''
  });

  const handleClear = () => {
    setActiveFilters({ studyTypeId: '', studyId: '', moduleId: '' });
  };

  const handleChange = (filters: { studyTypeId: string; studyId: string; moduleId: string }) => {
    setActiveFilters(filters);
  };

  return (
    <div className="p-6">
      <CascadeFilters onClear={handleClear} onFilterChange={handleChange} />

      <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card overflow-hidden">
        <div className="px-5 py-3 border-b border-ui-border-l dark:border-ui-dark-border-l flex items-center justify-between">
          <h2 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
            Programaciones Didácticas
          </h2>
        </div>

        <div className="p-6">
          <p className="text-sm text-text-muted dark:text-text-dark-muted mb-4">
            Filtros activos de la jerarquía académica:
          </p>
          <ul className="list-disc pl-5 text-sm text-text-secondary dark:text-text-dark-secondary mb-6">
            <li>Tipo de Estudio ID: {activeFilters.studyTypeId || 'Ninguno'}</li>
            <li>Estudio ID: {activeFilters.studyId || 'Ninguno'}</li>
            <li>Módulo ID: {activeFilters.moduleId || 'Ninguno'}</li>
          </ul>
          
          <div className="text-center py-8">
            <p className="text-sm italic">
              (Aquí aparecería el listado reactivo de documentos según los filtros. El filtrado ocurre en el cliente instantáneamente.)
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
