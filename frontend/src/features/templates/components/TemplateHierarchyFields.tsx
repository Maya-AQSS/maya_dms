import { useEffect, useState } from 'react';
import { FieldLabel, Select } from '../../../ui';
import { useHierarchy } from '../../hierarchy';
import { fetchMe, type UserTeam } from '../../../api/users';

export type TemplateHierarchyFieldKey = 'study_type_id' | 'study_id' | 'module_id' | 'team_id';

export type TemplateHierarchyValues = Record<TemplateHierarchyFieldKey, string>;

type Props = {
  values: TemplateHierarchyValues;
  onFieldChange: (key: TemplateHierarchyFieldKey, value: string) => void;
  /** Contenedor del grid (p. ej. `lg:col-span-2` para ocupar fila completa en formularios). */
  gridClassName?: string;
  /** Nivel de visibilidad actual. */
  visibility?: string;
};

export function TemplateHierarchyFields({
  values,
  onFieldChange,
  gridClassName = 'grid grid-cols-1 sm:grid-cols-2 gap-2',
  visibility,
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
    onFieldChange('team_id', '');
  };

  const handleStudyChange = (value: string) => {
    onFieldChange('study_id', value);
    onFieldChange('module_id', '');
    onFieldChange('team_id', '');
  };

  const handleModuleChange = (value: string) => {
    onFieldChange('module_id', value);
    onFieldChange('team_id', '');
  };

  const isTeamVisibility = visibility === 'team';

  return (
    <div className={gridClassName}>
      <div className={isTeamVisibility ? 'opacity-50 pointer-events-none' : ''}>
        <FieldLabel>Tipo de Estudio</FieldLabel>
        <Select
          fieldSize="sm"
          value={values.study_type_id}
          disabled={hierarchyLoading || isTeamVisibility}
          onChange={(e) => handleStudyTypeChange(e.target.value)}
        >
          <option value="">Todos</option>
          {hierarchy.map((t) => (
            <option key={t.id} value={t.id}>{t.name}</option>
          ))}
        </Select>
      </div>

      <div className={isTeamVisibility ? 'opacity-50 pointer-events-none' : ''}>
        <FieldLabel>Estudio</FieldLabel>
        <Select
          fieldSize="sm"
          value={values.study_id}
          disabled={hierarchyLoading || !values.study_type_id || isTeamVisibility}
          onChange={(e) => handleStudyChange(e.target.value)}
        >
          <option value="">Todos</option>
          {filteredStudies.map((s) => (
            <option key={s.id} value={s.id}>{s.name}</option>
          ))}
        </Select>
      </div>

      <div className={isTeamVisibility ? 'opacity-50 pointer-events-none' : ''}>
        <FieldLabel>Módulo</FieldLabel>
        <Select
          fieldSize="sm"
          value={values.module_id}
          disabled={hierarchyLoading || !values.study_id || isTeamVisibility}
          onChange={(e) => handleModuleChange(e.target.value)}
        >
          <option value="">Todos</option>
          {filteredModules.map((m) => (
            <option key={m.id} value={m.id}>{m.name}</option>
          ))}
        </Select>
      </div>

      {isTeamVisibility && (
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
