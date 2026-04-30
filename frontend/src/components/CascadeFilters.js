import { useId, useState } from 'react';
import { useHierarchy } from '../features/hierarchy';
import { Button, Select } from '../ui';
const ChevronIcon = ({ open }) => (<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" className={`w-4 h-4 transition-transform ${open ? 'rotate-180' : ''}`}>
    <path fillRule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clipRule="evenodd"/>
  </svg>);
/**
 * Componente para mostrar los selectores en cascada.
 *
 * Soporta modo controlado (pasando `value`) o no controlado.
 *
 * @param onFilterChange - Función para cambiar los filtros.
 * @param value - Filtros controlados desde el padre (opcional).
 * @returns El componente de filtros en cascada.
 */
export function CascadeFilters({ onFilterChange, value }) {
    const { hierarchy, loading, error } = useHierarchy();
    const typeSelectId = useId();
    const studySelectId = useId();
    const moduleSelectId = useId();
    const [internalType, setInternalType] = useState('');
    const [internalStudy, setInternalStudy] = useState('');
    const [internalModule, setInternalModule] = useState('');
    const isControlled = value !== undefined;
    const selectedType = isControlled ? value.studyTypeId : internalType;
    const selectedStudy = isControlled ? value.studyId : internalStudy;
    const selectedModule = isControlled ? value.moduleId : internalModule;
    const setSelectedType = (v) => {
        if (!isControlled)
            setInternalType(v);
    };
    const setSelectedStudy = (v) => {
        if (!isControlled)
            setInternalStudy(v);
    };
    const setSelectedModule = (v) => {
        if (!isControlled)
            setInternalModule(v);
    };
    // Visible en móvil al pulsar el toggle; en ≥ md siempre visible vía CSS
    const [isOpen, setIsOpen] = useState(false);
    if (loading)
        return <div className="text-sm text-text-muted">Cargando filtros...</div>;
    if (error)
        return <div className="text-sm text-warning-dark">Error al cargar jerarquía</div>;
    const currentType = hierarchy.find(t => t.id === selectedType);
    const studies = currentType ? currentType.studies : [];
    const currentStudy = studies.find(s => s.id === selectedStudy);
    const modules = currentStudy ? currentStudy.course_modules : [];
    const handleTypeChange = (e) => {
        const val = e.target.value;
        setSelectedType(val);
        setSelectedStudy('');
        setSelectedModule('');
        onFilterChange({ studyTypeId: val, studyId: '', moduleId: '' });
    };
    const handleStudyChange = (e) => {
        const val = e.target.value;
        setSelectedStudy(val);
        setSelectedModule('');
        onFilterChange({ studyTypeId: selectedType, studyId: val, moduleId: '' });
    };
    const handleModuleChange = (e) => {
        const val = e.target.value;
        setSelectedModule(val);
        onFilterChange({ studyTypeId: selectedType, studyId: selectedStudy, moduleId: val });
    };
    const hasActiveFilters = selectedType || selectedStudy || selectedModule;
    return (<>
      {/* Toggle visible solo en móvil */}
      <Button variant="unstyled" onClick={() => setIsOpen((v) => !v)} aria-expanded={isOpen} aria-controls="cascade-filter-panel" className="md:hidden w-full flex items-center justify-between text-sm font-semibold text-text-primary dark:text-text-dark-primary">
        <span>
          Filtros: tipo de estudio, estudio y módulo
          {hasActiveFilters && (<span className="ml-2 inline-flex items-center justify-center w-2 h-2 rounded-full bg-odoo-purple" aria-hidden="true"/>)}
        </span>
        <ChevronIcon open={isOpen}/>
      </Button>

      {/* Panel de selectores: colapsable en móvil, siempre visible en ≥ md */}
      <div id="cascade-filter-panel" className={`${isOpen ? 'flex' : 'hidden'} md:flex flex-col md:flex-row gap-4`}>
      <div className="flex-1">
        <label htmlFor={typeSelectId} className="block text-xs font-semibold text-text-secondary dark:text-text-dark-secondary mb-1">
          Tipo de Estudio
        </label>
        <Select id={typeSelectId} fieldSize="comfortable" className="focus:ring-2 focus:ring-odoo-purple outline-none" value={selectedType} onChange={handleTypeChange}>
          <option value="">Todos los tipos</option>
          {hierarchy.map((t) => (<option key={t.id} value={t.id}>
              {t.name}
            </option>))}
        </Select>
      </div>

      {selectedType && (<div className="flex-1">
          <label htmlFor={studySelectId} className="block text-xs font-semibold text-text-secondary dark:text-text-dark-secondary mb-1">
            Estudio
          </label>
          <Select id={studySelectId} fieldSize="comfortable" className="focus:ring-2 focus:ring-odoo-purple outline-none" value={selectedStudy} onChange={handleStudyChange}>
            <option value="">Todos los estudios</option>
            {studies.map((s) => (<option key={s.id} value={s.id}>
                {s.name}
              </option>))}
          </Select>
        </div>)}

      {selectedStudy && (<div className="flex-1">
          <label htmlFor={moduleSelectId} className="block text-xs font-semibold text-text-secondary dark:text-text-dark-secondary mb-1">
            Módulo
          </label>
          <Select id={moduleSelectId} fieldSize="comfortable" className="focus:ring-2 focus:ring-odoo-purple outline-none" value={selectedModule} onChange={handleModuleChange}>
            <option value="">Todos los módulos</option>
            {modules.map((m) => (<option key={m.id} value={m.id}>
                {m.name}
              </option>))}
          </Select>
        </div>)}

      </div>{/* cierre del panel colapsable */}
    </>);
}
