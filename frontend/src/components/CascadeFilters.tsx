import { useId, useState, type ChangeEvent } from 'react';
import type { CascadeDocumentFilters } from '../features/documents';
import { useHierarchy } from '../features/hierarchy';
import { Button, Select } from '../ui';

const ChevronIcon = ({ open }: { open: boolean }) => (
  <svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 20 20"
    fill="currentColor"
    aria-hidden="true"
    className={`w-4 h-4 transition-transform ${open ? 'rotate-180' : ''}`}
  >
    <path
      fillRule="evenodd"
      d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z"
      clipRule="evenodd"
    />
  </svg>
);

interface CascadeFiltersProps {
  onClear: () => void;
  onFilterChange: (filters: CascadeDocumentFilters) => void;
}

/**
 * Componente para mostrar los selectores en cascada.
 * 
 * @param onClear - Función para limpiar los filtros.
 * @param onFilterChange - Función para cambiar los filtros.
 * @returns El componente de filtros en cascada.
 */
export function CascadeFilters({ onClear, onFilterChange }: CascadeFiltersProps) {
  const { hierarchy, loading, error } = useHierarchy();
  const typeSelectId = useId();
  const studySelectId = useId();
  const moduleSelectId = useId();

  const [selectedType, setSelectedType] = useState<string>('');
  const [selectedStudy, setSelectedStudy] = useState<string>('');
  const [selectedModule, setSelectedModule] = useState<string>('');
  // Visible en móvil al pulsar el toggle; en ≥ md siempre visible vía CSS
  const [isOpen, setIsOpen] = useState(false);

  if (loading) return <div className="text-sm text-text-muted">Cargando filtros...</div>;
  if (error) return <div className="text-sm text-warning-dark">Error al cargar jerarquía</div>;

  const currentType = hierarchy.find(t => t.id === selectedType);
  const studies = currentType ? currentType.studies : [];
  const currentStudy = studies.find(s => s.id === selectedStudy);
  const modules = currentStudy ? currentStudy.course_modules : [];

  const handleTypeChange = (e: ChangeEvent<HTMLSelectElement>) => {
    const val = e.target.value;
    setSelectedType(val);
    setSelectedStudy('');
    setSelectedModule('');
    onFilterChange({ studyTypeId: val, studyId: '', moduleId: '' });
  };

  const handleStudyChange = (e: ChangeEvent<HTMLSelectElement>) => {
    const val = e.target.value;
    setSelectedStudy(val);
    setSelectedModule('');

    onFilterChange({ studyTypeId: selectedType, studyId: val, moduleId: '' });
  };

  const handleModuleChange = (e: ChangeEvent<HTMLSelectElement>) => {
    const val = e.target.value;
    setSelectedModule(val);

    onFilterChange({ studyTypeId: selectedType, studyId: selectedStudy, moduleId: val });
  };

  const clearFilters = () => {
    setSelectedType('');
    setSelectedStudy('');
    setSelectedModule('');
    onClear();
  };

  const hasActiveFilters = selectedType || selectedStudy || selectedModule;

  return (
    <div className="bg-ui-card dark:bg-ui-dark-card border border-ui-border dark:border-ui-dark-border rounded-lg mb-6 shadow-sm">
      {/* Toggle visible solo en móvil */}
      <button
        type="button"
        onClick={() => setIsOpen((v) => !v)}
        aria-expanded={isOpen}
        aria-controls="cascade-filter-panel"
        className="md:hidden w-full flex items-center justify-between px-4 py-3 text-sm font-semibold text-text-primary dark:text-text-dark-primary"
      >
        <span>
          Filtros: tipo de estudio, estudio y módulo
          {hasActiveFilters && (
            <span className="ml-2 inline-flex items-center justify-center w-2 h-2 rounded-full bg-odoo-purple" aria-hidden="true" />
          )}
        </span>
        <ChevronIcon open={isOpen} />
      </button>

      {/* Panel de selectores: colapsable en móvil, siempre visible en ≥ md */}
      <div
        id="cascade-filter-panel"
        className={`${isOpen ? 'flex' : 'hidden'} md:flex flex-col md:flex-row gap-4 p-4`}
      >
      <div className="flex-1">
        <label
          htmlFor={typeSelectId}
          className="block text-xs font-semibold text-text-secondary dark:text-text-dark-secondary mb-1"
        >
          Tipo de Estudio
        </label>
        <Select
          id={typeSelectId}
          fieldSize="comfortable"
          className="focus:ring-2 focus:ring-odoo-purple outline-none"
          value={selectedType}
          onChange={handleTypeChange}
        >
          <option value="">Todos los tipos</option>
          {hierarchy.map((t) => (
            <option key={t.id} value={t.id}>
              {t.name}
            </option>
          ))}
        </Select>
      </div>

      {selectedType && (
        <div className="flex-1">
          <label
            htmlFor={studySelectId}
            className="block text-xs font-semibold text-text-secondary dark:text-text-dark-secondary mb-1"
          >
            Estudio
          </label>
          <Select
            id={studySelectId}
            fieldSize="comfortable"
            className="focus:ring-2 focus:ring-odoo-purple outline-none"
            value={selectedStudy}
            onChange={handleStudyChange}
          >
            <option value="">Todos los estudios</option>
            {studies.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </Select>
        </div>
      )}

      {selectedStudy && (
        <div className="flex-1">
          <label
            htmlFor={moduleSelectId}
            className="block text-xs font-semibold text-text-secondary dark:text-text-dark-secondary mb-1"
          >
            Módulo
          </label>
          <Select
            id={moduleSelectId}
            fieldSize="comfortable"
            className="focus:ring-2 focus:ring-odoo-purple outline-none"
            value={selectedModule}
            onChange={handleModuleChange}
          >
            <option value="">Todos los módulos</option>
            {modules.map((m) => (
              <option key={m.id} value={m.id}>
                {m.name}
              </option>
            ))}
          </Select>
        </div>
      )}

      <div className="flex items-end">
        <Button
          type="button"
          variant="secondary"
          size="md"
          onClick={clearFilters}
          className="mt-4.5 h-9.5 self-end whitespace-nowrap md:mt-0"
        >
          Limpiar filtros
        </Button>
      </div>
      </div>{/* cierre del panel colapsable */}
    </div>
  );
}
