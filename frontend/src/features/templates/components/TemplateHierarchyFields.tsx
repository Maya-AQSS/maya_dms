import { useEffect, useState } from 'react';
import { FieldLabel, Select } from '@maya/shared-ui-react';
import { useHierarchy } from '../../hierarchy';
import { fetchMe, type UserTeam } from '../../../api/users';

export type TemplateHierarchyFieldKey = 'study_type_id' | 'study_id' | 'module_id' | 'team_id';

export type TemplateHierarchyValues = Record<TemplateHierarchyFieldKey, string>;

const HIERARCHY_LEVELS = ['study_type', 'study', 'module'] as const;
type HierarchyMaxLevel = typeof HIERARCHY_LEVELS[number] | null;

function levelGte(maxLevel: HierarchyMaxLevel | undefined, min: typeof HIERARCHY_LEVELS[number]): boolean {
  if (maxLevel === undefined) return true;
  if (maxLevel === null) return false;
  return HIERARCHY_LEVELS.indexOf(maxLevel) >= HIERARCHY_LEVELS.indexOf(min);
}

type Props = {
  values: TemplateHierarchyValues;
  onFieldChange: (key: TemplateHierarchyFieldKey, value: string) => void;
  /** Contenedor del grid (p. ej. `lg:col-span-2` para ocupar fila completa en formularios). */
  gridClassName?: string;
  /** Filter mode: hide conditional fields instead of disabling; don't cascade-reset team_id. */
  filterMode?: boolean;
  /**
   * Max hierarchy depth to show in filterMode.
   * null = hide all hierarchy fields (e.g. when showing only Equipo).
   * undefined = show all (default, backward-compat).
   */
  maxLevel?: HierarchyMaxLevel;
  /** Whether to render the Equipo field (default true). */
  showTeam?: boolean;
};

export function TemplateHierarchyFields({
  values,
  onFieldChange,
  gridClassName = 'grid grid-cols-1 sm:grid-cols-2 gap-2',
  filterMode = false,
  maxLevel,
  showTeam = true,
}: Props) {
  const { hierarchy, loading: hierarchyLoading } = useHierarchy();
  const [teams, setTeams] = useState<UserTeam[]>([]);

  useEffect(() => {
    fetchMe()
      .then((res) => setTeams(res.data.teams ?? []))
      .catch(() => setTeams([]));
  }, []);

  const allStudies = hierarchy.flatMap((t) => t.studies);
  const allModules = allStudies.flatMap((s) => s.course_modules);

  const filteredStudies = values.study_type_id
    ? (hierarchy.find((t) => String(t.id) === values.study_type_id)?.studies ?? [])
    : allStudies;

  const filteredModules = values.study_id
    ? (allStudies.find((s) => String(s.id) === values.study_id)?.course_modules ?? [])
    : allModules;

  const handleStudyTypeChange = (value: string) => {
    onFieldChange('study_type_id', value);
    onFieldChange('study_id', '');
    onFieldChange('module_id', '');
    if (!filterMode) onFieldChange('team_id', '');
  };

  const handleStudyChange = (value: string) => {
    onFieldChange('study_id', value);
    onFieldChange('module_id', '');
    if (!filterMode) onFieldChange('team_id', '');
  };

  const handleModuleChange = (value: string) => {
    onFieldChange('module_id', value);
    if (!filterMode) onFieldChange('team_id', '');
  };

  const showStudyType = !filterMode || levelGte(maxLevel, 'study_type');
  const showEstudio = !filterMode || (!!values.study_type_id && levelGte(maxLevel, 'study'));
  const showModulo = !filterMode || (!!values.study_id && levelGte(maxLevel, 'module'));

  return (
    <div className={gridClassName}>
      {showStudyType && (
        <div>
          <FieldLabel>Tipo de Estudio</FieldLabel>
          <Select
            fieldSize="sm"
            value={values.study_type_id}
            disabled={hierarchyLoading}
            onChange={(e) => handleStudyTypeChange(e.target.value)}
          >
            <option value="">Todos</option>
            {hierarchy.map((t) => (
              <option key={t.id} value={t.id}>{t.name}</option>
            ))}
          </Select>
        </div>
      )}

      {showEstudio && (
        <div>
          <FieldLabel>Estudio</FieldLabel>
          <Select
            fieldSize="sm"
            value={values.study_id}
            disabled={hierarchyLoading || (!filterMode && !values.study_type_id)}
            onChange={(e) => handleStudyChange(e.target.value)}
          >
            <option value="">Todos</option>
            {filteredStudies.map((s) => (
              <option key={s.id} value={s.id}>{s.name}</option>
            ))}
          </Select>
        </div>
      )}

      {showModulo && (
        <div>
          <FieldLabel>Módulo</FieldLabel>
          <Select
            fieldSize="sm"
            value={values.module_id}
            disabled={hierarchyLoading || (!filterMode && !values.study_id)}
            onChange={(e) => handleModuleChange(e.target.value)}
          >
            <option value="">Todos</option>
            {filteredModules.map((m) => (
              <option key={m.id} value={m.id}>{m.name}</option>
            ))}
          </Select>
        </div>
      )}

      {showTeam && (
        <div>
          <FieldLabel>Equipo</FieldLabel>
          <Select
            fieldSize="sm"
            value={values.team_id}
            disabled={!teams.length}
            onChange={(e) => onFieldChange('team_id', e.target.value)}
          >
            <option value="">Todos</option>
            {teams.map((team) => (
              <option key={team.id} value={team.id}>{team.name}</option>
            ))}
          </Select>
        </div>
      )}
    </div>
  );
}
