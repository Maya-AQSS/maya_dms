import { FieldLabel } from '../../../ui/FieldLabel';
import { TextInput } from '../../../ui/TextInput';

export type TemplateHierarchyFieldKey = 'study_type_id' | 'study_id' | 'module_id' | 'team_id';

export type TemplateHierarchyValues = Record<TemplateHierarchyFieldKey, string>;

const KEYS: TemplateHierarchyFieldKey[] = ['study_type_id', 'study_id', 'module_id', 'team_id'];

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
          <FieldLabel>{key}</FieldLabel>
          <TextInput
            type="text"
            fieldSize="mono"
            value={values[key]}
            onChange={(e) => onFieldChange(key, e.target.value)}
          />
        </div>
      ))}
    </div>
  );
}
