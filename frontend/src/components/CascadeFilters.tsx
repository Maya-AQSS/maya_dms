import { useState, type ChangeEvent } from 'react';
import { useHierarchy } from '../features/hierarchy';
import { Button, Select } from '../ui';

interface CascadeFiltersProps {
  onClear: () => void;
  onFilterChange: (filters: {
    studyTypeId: string;
    studyId: string;
    moduleId: string;
  }) => void;
}

export function CascadeFilters({ onClear, onFilterChange }: CascadeFiltersProps) {
  const { hierarchy, loading, error } = useHierarchy();
  
  const [selectedType, setSelectedType] = useState<string>('');
  const [selectedStudy, setSelectedStudy] = useState<string>('');
  const [selectedModule, setSelectedModule] = useState<string>('');

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
    
    // Debería ser inmediato < 16ms
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

  // Responsividad: en móvil esto sería un grid col-1 o flex-col, en md es un flex-row
  return (
    <div className="bg-ui-card dark:bg-ui-dark-card border border-ui-border dark:border-ui-dark-border p-4 rounded-lg flex flex-col md:flex-row gap-4 mb-6 shadow-sm">
      <div className="flex-1">
        <label className="block text-xs font-semibold text-text-secondary dark:text-text-dark-secondary mb-1">
          Tipo de Estudio
        </label>
        <Select
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

      <div className="flex-1">
        <label className="block text-xs font-semibold text-text-secondary dark:text-text-dark-secondary mb-1">
          Estudio
        </label>
        <Select
          fieldSize="comfortable"
          className="focus:ring-2 focus:ring-odoo-purple outline-none disabled:opacity-50 disabled:cursor-not-allowed"
          value={selectedStudy}
          onChange={handleStudyChange}
          disabled={!selectedType}
        >
          <option value="">Todos los estudios</option>
          {studies.map((s) => (
            <option key={s.id} value={s.id}>
              {s.name}
            </option>
          ))}
        </Select>
      </div>

      <div className="flex-1">
        <label className="block text-xs font-semibold text-text-secondary dark:text-text-dark-secondary mb-1">
          Módulo
        </label>
        <Select
          fieldSize="comfortable"
          className="focus:ring-2 focus:ring-odoo-purple outline-none disabled:opacity-50 disabled:cursor-not-allowed"
          value={selectedModule}
          onChange={handleModuleChange}
          disabled={!selectedStudy}
        >
          <option value="">Todos los módulos</option>
          {modules.map((m) => (
            <option key={m.id} value={m.id}>
              {m.name}
            </option>
          ))}
        </Select>
      </div>

      <div className="flex items-end">
        <Button
          type="button"
          variant="secondary"
          size="md"
          onClick={clearFilters}
          className="mt-[18px] h-[38px] self-end whitespace-nowrap md:mt-0"
        >
          Limpiar filtros
        </Button>
      </div>
    </div>
  );
}
