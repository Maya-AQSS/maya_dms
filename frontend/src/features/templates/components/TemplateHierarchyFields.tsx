export type TemplateHierarchyFieldKey = 'study_type_id' | 'study_id' | 'module_id' | 'group_id';

export type TemplateHierarchyValues = Record<TemplateHierarchyFieldKey, string>;

const KEYS: TemplateHierarchyFieldKey[] = ['study_type_id', 'study_id', 'module_id', 'group_id'];

type Props = {
  values: TemplateHierarchyValues;
  onFieldChange: (key: TemplateHierarchyFieldKey, value: string) => void;
  /** Contenedor del grid (p. ej. `lg:col-span-2` para ocupar fila completa en formularios). */
  gridClassName?: string;
};

/**
 * Cuatro campos UUID de jerarquía académica (filtros, alta y edición de plantillas).
 */
export function TemplateHierarchyFields({
  values,
  onFieldChange,
  gridClassName = 'grid grid-cols-1 sm:grid-cols-2 gap-2',
}: Props) {
  return (
    <div className={gridClassName}>
      {KEYS.map((key) => (
        <div key={key}>
          <label className="block text-xs text-text-muted dark:text-text-dark-muted mb-1">{key}</label>
          <input
            type="text"
            value={values[key]}
            onChange={(e) => onFieldChange(key, e.target.value)}
            className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg font-mono text-xs px-2 py-1.5"
          />
        </div>
      ))}
    </div>
  );
}
