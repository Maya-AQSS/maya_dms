import { FieldLabel, Select, TextArea, TextInput } from '../../../ui';
import { VISIBILITY_OPTIONS } from '../constants';
import { useHierarchy } from '../../../features/hierarchy';
import { useUserProfile } from '../../../features/user-profile';
import type { UserTeam } from '../../../api/users';
import type { TemplateStatus, TemplateVisibilityLevel } from '../../../types/templates';

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
  templateStatus?: TemplateStatus;
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
  templateStatus,
}: Props) {
  const deadlineLocked = templateStatus === 'in_review' || templateStatus === 'published';
  const { hierarchy, loading: hierarchyLoading } = useHierarchy();
  const { profile, loading: profileLoading, error: profileError } = useUserProfile();
  const teams: UserTeam[] = profile?.teams ?? [];
  const teamsLoading = profileLoading;
  const teamsError = profileError
    ? 'No se pudo cargar el perfil (equipos). Revisa la sesión o inténtalo de nuevo.'
    : null;

  const allStudies = hierarchy.flatMap((t) => t.studies);
  const filteredStudies = studyTypeId
    ? (hierarchy.find((t) => String(t.id) === studyTypeId)?.studies ?? [])
    : allStudies;
  const filteredModules = studyId
    ? (allStudies.find((s) => String(s.id) === studyId)?.course_modules ?? [])
    : [];

  const showAcademicBlock = visibility !== 'personal' && visibility !== 'global';

  return (
    <div className="flex-1 min-h-0 flex flex-col bg-ui-card dark:bg-ui-dark-card overflow-hidden">
      <div className="flex-1 overflow-y-auto px-8 py-6 space-y-6">
        {errors.api && (
          <div className="rounded-lg border border-danger/30 bg-danger/5 px-4 py-3 text-xs text-danger-dark dark:text-danger">
            {errors.api}
          </div>
        )}

        {/* Campos generales — grid sin card wrapper */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="md:col-span-2">
            <FieldLabel required>Nombre</FieldLabel>
            <TextInput
              type="text"
              fieldSize="comfortable"
              value={name}
              onChange={(e: React.ChangeEvent<HTMLInputElement>) => setName(e.target.value)}
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
              onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setDescription(e.target.value)}
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
            <FieldLabel htmlFor="delivery-deadline" required>Plazo de entrega</FieldLabel>
            <TextInput
              id="delivery-deadline"
              type="date"
              fieldSize="comfortable"
              value={deliveryDeadline}
              onChange={(e: React.ChangeEvent<HTMLInputElement>) => setDeliveryDeadline(e.target.value)}
              disabled={deadlineLocked}
              placeholder="Seleccionar fecha…"
            />
            {errors.deliveryDeadline && (
              <p className="mt-1 text-xs text-danger-dark dark:text-danger">{errors.deliveryDeadline}</p>
            )}
            {deadlineLocked && (
              <p className="mt-1 text-xs text-text-muted italic">
                No editable en estado actual.
              </p>
            )}
          </div>
        </div>

        {/* Bloque de vinculación — separador, sin card */}
        {showAcademicBlock && (
          <div
            className="pt-5 border-t border-ui-border dark:border-ui-dark-border"
            style={{ animation: 'wizardFadeSlide 150ms ease both' }}
          >
            <style>{`
              @keyframes wizardFadeSlide {
                from { opacity: 0; transform: translateY(-4px); }
                to   { opacity: 1; transform: translateY(0); }
              }
            `}</style>

            <div className={`grid gap-4 ${
              visibility === 'module' ? 'grid-cols-3' :
              visibility === 'study' ? 'grid-cols-2' :
              'grid-cols-1'
            }`}>
              {/* Tipo de Estudio — required parent context for study_type, study, module */}
              {(visibility === 'study_type' || visibility === 'study' || visibility === 'module') && (
                <div>
                  <Select
                    fieldSize="comfortable"
                    value={studyTypeId}
                    disabled={hierarchyLoading}
                    onChange={(e) => {
                      setStudyTypeId(e.target.value);
                      setStudyId('');
                      setModuleId('');
                    }}
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

              {/* Estudio — filtered by Tipo de Estudio; required parent context for study, module */}
              {(visibility === 'study' || visibility === 'module') && (
                <div>
                  <Select
                    fieldSize="comfortable"
                    value={studyId}
                    disabled={hierarchyLoading}
                    onChange={(e) => {
                      setStudyId(e.target.value);
                      setModuleId('');
                    }}
                    error={!!errors.studyId}
                  >
                    <option value="">— Seleccionar —</option>
                    {filteredStudies.map((s) => (
                      <option key={s.id} value={s.id}>{s.name}</option>
                    ))}
                  </Select>
                  {errors.studyId && (
                    <p className="mt-1 text-xs text-danger-dark dark:text-danger">{errors.studyId}</p>
                  )}
                </div>
              )}

              {/* Módulo — filtered by Estudio */}
              {visibility === 'module' && (
                <div>
                  <Select
                    fieldSize="comfortable"
                    value={moduleId}
                    disabled={hierarchyLoading}
                    onChange={(e: React.ChangeEvent<HTMLSelectElement>) => setModuleId(e.target.value)}
                    error={!!errors.moduleId}
                  >
                    <option value="">— Seleccionar —</option>
                    {filteredModules.map((m) => (
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
                  <Select
                    fieldSize="comfortable"
                    value={teamId}
                    disabled={teamsLoading || !teams.length}
                    onChange={(e: React.ChangeEvent<HTMLSelectElement>) => setTeamId(e.target.value)}
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
