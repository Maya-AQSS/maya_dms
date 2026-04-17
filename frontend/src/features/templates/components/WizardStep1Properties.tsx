import { useEffect, useState } from 'react';
import { FieldLabel, Select, TextArea, TextInput } from '../../../ui';
import { VISIBILITY_OPTIONS } from '../constants';
import { useHierarchy } from '../../../features/hierarchy';
import { fetchMe, type UserTeam } from '../../../api/users';
import type { TemplateVisibilityLevel } from '../../../types/templates';

type Props = {
  name: string;
  setName: (v: string) => void;
  description: string;
  setDescription: (v: string) => void;
  visibility: TemplateVisibilityLevel;
  setVisibility: (v: TemplateVisibilityLevel) => void;
  deliveryDeadline: string;
  setDeliveryDeadline: (v: string) => void;
  studyTypeId: string;
  setStudyTypeId: (v: string) => void;
  studyId: string;
  setStudyId: (v: string) => void;
  moduleId: string;
  setModuleId: (v: string) => void;
  teamId: string;
  setTeamId: (v: string) => void;
  errors: Record<string, string>;
};

export function WizardStep1Properties({
  name, setName,
  description, setDescription,
  visibility, setVisibility,
  deliveryDeadline, setDeliveryDeadline,
  studyTypeId, setStudyTypeId,
  studyId, setStudyId,
  moduleId, setModuleId,
  teamId, setTeamId,
  errors,
}: Props) {
  const { hierarchy, loading: hierarchyLoading } = useHierarchy();
  const [teams, setTeams] = useState<UserTeam[]>([]);
  const [teamsLoading, setTeamsLoading] = useState(false);
  const [teamsError, setTeamsError] = useState<string | null>(null);

  useEffect(() => {
    setTeamsLoading(true);
    setTeamsError(null);
    fetchMe()
      .then((res) => {
        setTeams(res.data.teams ?? []);
      })
      .catch(() => {
        setTeams([]);
        setTeamsError('No se pudieron cargar tus equipos. Revisa la sesión o inténtalo de nuevo.');
      })
      .finally(() => {
        setTeamsLoading(false);
      });
  }, []);

  const allStudies = hierarchy.flatMap((t) => t.studies);
  const allModules = allStudies.flatMap((s) => s.course_modules);

  const showAcademicBlock = visibility !== 'personal' && visibility !== 'global';

  const BINDING_TITLES: Partial<Record<TemplateVisibilityLevel, string>> = {
    study_type: 'Tipo de Estudio',
    study: 'Estudio',
    module: 'Módulo',
    team: 'Equipo',
  };

  return (
    <div className="flex-1 overflow-y-auto px-8 py-6">
      <div className="space-y-6">

        {/* Campos generales — grid sin card wrapper */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="md:col-span-2">
            <FieldLabel required>Nombre</FieldLabel>
            <TextInput
              type="text"
              fieldSize="comfortable"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Ej. Acta de Evaluación Final"
              error={!!errors.name}
            />
            {errors.name && <p className="mt-1 text-xs text-danger-dark dark:text-danger">{errors.name}</p>}
          </div>

          <div className="md:col-span-2">
            <FieldLabel>Descripción</FieldLabel>
            <TextArea
              fieldSize="comfortable"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Propósito de la plantilla…"
              style={{ minHeight: '64px' }}
            />
          </div>

          <div>
            <FieldLabel required>Visibilidad</FieldLabel>
            <Select
              fieldSize="comfortable"
              value={visibility}
              onChange={(e) => {
                const v = e.target.value as TemplateVisibilityLevel;
                setVisibility(v);
                setStudyTypeId('');
                setStudyId('');
                setModuleId('');
                setTeamId('');
              }}
            >
              {VISIBILITY_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </Select>
          </div>

          <div>
            <FieldLabel>Plazo de entrega</FieldLabel>
            <TextInput
              type="date"
              fieldSize="comfortable"
              value={deliveryDeadline}
              onChange={(e) => setDeliveryDeadline(e.target.value)}
            />
          </div>
        </div>

        {/* Bloque de vinculación — separador, sin card */}
        {showAcademicBlock && (
          <div
            className="pt-5 border-t border-ui-border dark:border-ui-dark-border"
            style={{
              animation: 'wizardFadeSlide 150ms ease both',
            }}
          >
            <style>{`
              @keyframes wizardFadeSlide {
                from { opacity: 0; transform: translateY(-4px); }
                to   { opacity: 1; transform: translateY(0); }
              }
            `}</style>
            <p className="text-xs font-bold uppercase tracking-wider text-text-secondary dark:text-text-dark-secondary mb-4">
              {BINDING_TITLES[visibility] ?? visibility}
            </p>

            <div className="max-w-sm">
              {visibility === 'study_type' && (
                <div>
                  <FieldLabel required>Tipo de Estudio</FieldLabel>
                  <Select
                    fieldSize="comfortable"
                    value={studyTypeId}
                    disabled={hierarchyLoading}
                    onChange={(e) => setStudyTypeId(e.target.value)}
                    error={!!errors.studyTypeId}
                  >
                    <option value="">— Seleccionar —</option>
                    {hierarchy.map((t) => (
                      <option key={t.id} value={t.id}>{t.name}</option>
                    ))}
                  </Select>
                  {errors.studyTypeId && (
                    <p className="mt-1 text-xs text-danger-dark dark:text-danger">{errors.studyTypeId}</p>
                  )}
                </div>
              )}

              {visibility === 'study' && (
                <div>
                  <FieldLabel required>Estudio</FieldLabel>
                  <Select
                    fieldSize="comfortable"
                    value={studyId}
                    disabled={hierarchyLoading}
                    onChange={(e) => setStudyId(e.target.value)}
                    error={!!errors.studyId}
                  >
                    <option value="">— Seleccionar —</option>
                    {allStudies.map((s) => (
                      <option key={s.id} value={s.id}>{s.name}</option>
                    ))}
                  </Select>
                  {errors.studyId && (
                    <p className="mt-1 text-xs text-danger-dark dark:text-danger">{errors.studyId}</p>
                  )}
                </div>
              )}

              {visibility === 'module' && (
                <div>
                  <FieldLabel required>Módulo</FieldLabel>
                  <Select
                    fieldSize="comfortable"
                    value={moduleId}
                    disabled={hierarchyLoading}
                    onChange={(e) => setModuleId(e.target.value)}
                    error={!!errors.moduleId}
                  >
                    <option value="">— Seleccionar —</option>
                    {allModules.map((m) => (
                      <option key={m.id} value={m.id}>{m.name}</option>
                    ))}
                  </Select>
                  {errors.moduleId && (
                    <p className="mt-1 text-xs text-danger-dark dark:text-danger">{errors.moduleId}</p>
                  )}
                </div>
              )}

              {visibility === 'team' && (
                <div>
                  <FieldLabel required>Equipo</FieldLabel>
                  <Select
                    fieldSize="comfortable"
                    value={teamId}
                    disabled={teamsLoading || !teams.length}
                    onChange={(e) => setTeamId(e.target.value)}
                    error={!!errors.teamId}
                  >
                    <option value="">
                      {teamsLoading ? 'Cargando equipos…' : '— Seleccionar —'}
                    </option>
                    {teams.map((team) => (
                      <option key={team.id} value={team.id}>{team.name}</option>
                    ))}
                  </Select>
                  {teamsError && (
                    <p className="mt-1 text-xs text-danger-dark dark:text-danger">{teamsError}</p>
                  )}
                  {errors.teamId && (
                    <p className="mt-1 text-xs text-danger-dark dark:text-danger">{errors.teamId}</p>
                  )}
                </div>
              )}
            </div>
          </div>
        )}

      </div>
    </div>
  );
}
