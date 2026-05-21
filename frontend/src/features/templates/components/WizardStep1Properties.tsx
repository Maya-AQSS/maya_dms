import { useEffect, useMemo } from 'react';
import { Controller, useFormContext, useWatch } from 'react-hook-form';
import { DatePicker, FieldLabel, Select, TextArea, TextInput } from '@maya/shared-ui-react';
import { VISIBILITY_OPTIONS } from '../constants';
import { useHierarchy } from '../../../features/hierarchy';
import { useUserProfile } from '../../../features/user-profile';
import { usePublishedThemes } from '../../../features/themes/hooks/usePublishedThemes';
import { ThemeMiniPreview } from '../../../features/themes/components/ThemeMiniPreview';
import type { UserTeam } from '../../../api/users';
import type { TemplateStatus, TemplateVisibilityLevel } from '../../../types/templates';
import type { TemplateStep1Input } from '../schemas/templateStep1';

type Props = {
  errors?: { api?: string };
  templateStatus?: TemplateStatus;
};

export function WizardStep1Properties({ errors, templateStatus }: Props) {
  const deadlineLocked = templateStatus === 'in_review' || templateStatus === 'published';
  const { hierarchy, loading: hierarchyLoading } = useHierarchy();
  const { profile, loading: profileLoading, error: profileError, hasPermission } = useUserProfile();
  const teams: UserTeam[] = profile?.teams ?? [];
  const teamsLoading = profileLoading;
  const teamsError = profileError
    ? 'No se pudo cargar el perfil (equipos). Revisa la sesión o inténtalo de nuevo.'
    : null;

  const {
    control,
    register,
    setValue,
    formState: { errors: formErrors },
  } = useFormContext<TemplateStep1Input>();

  const visibility = useWatch({ control, name: 'visibility' });
  const studyTypeId = useWatch({ control, name: 'studyTypeId' });
  const studyId = useWatch({ control, name: 'studyId' });

  const canCreateShared = hasPermission('templates.create');
  const visibilityOptions = VISIBILITY_OPTIONS.filter(
    (o) => o.value === 'personal' || canCreateShared,
  );

  const allStudies = hierarchy.flatMap((t) => t.studies);
  const filteredStudies = studyTypeId
    ? (hierarchy.find((t) => String(t.id) === studyTypeId)?.studies ?? [])
    : allStudies;
  const filteredModules = studyId
    ? (allStudies.find((s) => String(s.id) === studyId)?.course_modules ?? [])
    : [];

  useEffect(() => {
    if (hierarchyLoading || hierarchy.length === 0 || studyTypeId) return;
    if (hierarchy.length === 1) {
      setValue('studyTypeId', String(hierarchy[0].id), { shouldDirty: false });
    }
  }, [hierarchy, hierarchyLoading, studyTypeId, setValue]);

  useEffect(() => {
    if (!studyTypeId || studyId) return;
    const typeNode = hierarchy.find((t) => String(t.id) === studyTypeId);
    if (!typeNode) return;
    if ((typeNode.studies ?? []).length === 1) {
      setValue('studyId', String(typeNode.studies[0].id), { shouldDirty: false });
    }
  }, [hierarchy, studyTypeId, studyId, setValue]);

  const moduleId = useWatch({ control, name: 'moduleId' });
  useEffect(() => {
    if (!studyId || moduleId) return;
    const studyNode = allStudies.find((s) => String(s.id) === studyId);
    if (!studyNode) return;
    if ((studyNode.course_modules ?? []).length === 1) {
      setValue('moduleId', String(studyNode.course_modules[0].id), { shouldDirty: false });
    }
  }, [allStudies, studyId, moduleId, setValue]);

  const showAcademicBlock = visibility !== 'personal' && visibility !== 'global';

  // Themes publicados disponibles para asignar.
  const themesQuery = usePublishedThemes();
  const themeId = useWatch({ control, name: 'themeId' });
  const selectedTheme = useMemo(
    () => themesQuery.data?.find((t) => t.id === themeId) ?? null,
    [themesQuery.data, themeId],
  );

  return (
    <div className="flex-1 min-h-0 flex flex-col bg-ui-card dark:bg-ui-dark-card overflow-hidden">
      <div className="flex-1 overflow-y-auto px-8 py-6 space-y-6">
        {errors?.api && (
          <div className="rounded-lg border border-danger/30 bg-danger/5 px-4 py-3 text-xs text-danger-dark dark:text-danger">
            {errors.api}
          </div>
        )}

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="md:col-span-2">
            <FieldLabel required>Nombre</FieldLabel>
            <TextInput
              type="text"
              fieldSize="comfortable"
              placeholder="Ej. Acta de Evaluación Final"
              error={!!formErrors.name}
              {...register('name')}
            />
            {formErrors.name?.message && (
              <p className="mt-1 text-xs text-danger-dark dark:text-danger">{formErrors.name.message}</p>
            )}
          </div>

          <div className="md:col-span-2">
            <FieldLabel>Descripción</FieldLabel>
            <TextArea
              fieldSize="comfortable"
              placeholder="Propósito de la plantilla…"
              style={{ minHeight: '64px' }}
              {...register('description')}
            />
          </div>

          <div>
            <FieldLabel required>Visibilidad</FieldLabel>
            <Controller
              control={control}
              name="visibility"
              render={({ field }) => (
                <Select
                  fieldSize="comfortable"
                  value={field.value}
                  onChange={(e) => {
                    const v = e.target.value as TemplateVisibilityLevel;
                    field.onChange(v);
                    setValue('studyTypeId', '', { shouldDirty: true });
                    setValue('studyId', '', { shouldDirty: true });
                    setValue('moduleId', '', { shouldDirty: true });
                    setValue('teamId', '', { shouldDirty: true });
                  }}
                  onBlur={field.onBlur}
                >
                  {visibilityOptions.map((o) => (
                    <option key={o.value} value={o.value}>{o.label}</option>
                  ))}
                </Select>
              )}
            />
          </div>

          <div>
            <FieldLabel htmlFor="delivery-deadline" required>Plazo de entrega</FieldLabel>
            <Controller
              control={control}
              name="deliveryDeadline"
              render={({ field }) => (
                <DatePicker
                  value={field.value || null}
                  onChange={(d: string | null) => field.onChange(d ?? '')}
                  disabled={deadlineLocked}
                  placeholder="Seleccionar fecha…"
                  ariaLabel="Plazo de entrega"
                />
              )}
            />
            {formErrors.deliveryDeadline?.message && (
              <p className="mt-1 text-xs text-danger-dark dark:text-danger">{formErrors.deliveryDeadline.message}</p>
            )}
            {deadlineLocked && (
              <p className="mt-1 text-xs text-text-muted italic">
                No editable en estado actual.
              </p>
            )}
          </div>
        </div>

        {showAcademicBlock && (
          <div className="pt-5 border-t border-ui-border dark:border-ui-dark-border animate-in slide-in-from-top-2 fade-in">
            <div className={`grid gap-4 ${
              visibility === 'module' ? 'grid-cols-3' :
              visibility === 'study' ? 'grid-cols-2' :
              'grid-cols-1'
            }`}>
              {(visibility === 'study_type' || visibility === 'study' || visibility === 'module') && (
                <div>
                  <Controller
                    control={control}
                    name="studyTypeId"
                    render={({ field }) => (
                      <Select
                        fieldSize="comfortable"
                        value={field.value}
                        disabled={hierarchyLoading}
                        onChange={(e) => {
                          field.onChange(e.target.value);
                          setValue('studyId', '', { shouldDirty: true });
                          setValue('moduleId', '', { shouldDirty: true });
                        }}
                        onBlur={field.onBlur}
                        error={!!formErrors.studyTypeId}
                      >
                        {hierarchy.length === 0 && !hierarchyLoading ? (
                          <option value="" disabled>No tienes tipos de estudio asignados, contacta con un administrador</option>
                        ) : (
                          <option value="">— Seleccionar —</option>
                        )}
                        {hierarchy.map((t) => (
                          <option key={t.id} value={t.id}>{t.name}</option>
                        ))}
                      </Select>
                    )}
                  />
                  {formErrors.studyTypeId?.message && (
                    <p className="mt-1 text-xs text-danger-dark dark:text-danger">{formErrors.studyTypeId.message}</p>
                  )}
                </div>
              )}

              {(visibility === 'study' || visibility === 'module') && (
                <div>
                  <Controller
                    control={control}
                    name="studyId"
                    render={({ field }) => (
                      <Select
                        fieldSize="comfortable"
                        value={field.value}
                        disabled={hierarchyLoading}
                        onChange={(e) => {
                          field.onChange(e.target.value);
                          setValue('moduleId', '', { shouldDirty: true });
                        }}
                        onBlur={field.onBlur}
                        error={!!formErrors.studyId}
                      >
                        <option value="">— Seleccionar —</option>
                        {filteredStudies.map((s) => (
                          <option key={s.id} value={s.id}>{s.name}</option>
                        ))}
                      </Select>
                    )}
                  />
                  {formErrors.studyId?.message && (
                    <p className="mt-1 text-xs text-danger-dark dark:text-danger">{formErrors.studyId.message}</p>
                  )}
                </div>
              )}

              {visibility === 'module' && (
                <div>
                  <Select
                    fieldSize="comfortable"
                    disabled={hierarchyLoading}
                    error={!!formErrors.moduleId}
                    {...register('moduleId')}
                  >
                    <option value="">— Seleccionar —</option>
                    {filteredModules.map((m) => (
                      <option key={m.id} value={m.id}>{m.name}</option>
                    ))}
                  </Select>
                  {formErrors.moduleId?.message && (
                    <p className="mt-1 text-xs text-danger-dark dark:text-danger">{formErrors.moduleId.message}</p>
                  )}
                </div>
              )}

              {visibility === 'team' && (
                <div>
                  <Select
                    fieldSize="comfortable"
                    disabled={teamsLoading || !teams.length}
                    error={!!formErrors.teamId}
                    {...register('teamId')}
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
                  {formErrors.teamId?.message && (
                    <p className="mt-1 text-xs text-danger-dark dark:text-danger">{formErrors.teamId.message}</p>
                  )}
                </div>
              )}
            </div>
          </div>
        )}

        {/* ─── Identidad visual (theme opcional) ───────────────────── */}
        <div className="pt-5 border-t border-ui-border dark:border-ui-dark-border">
          <h3 className="mb-3 text-xs font-black uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary">
            Identidad visual
          </h3>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <FieldLabel htmlFor="template-theme">Theme aplicado</FieldLabel>
              <Controller
                control={control}
                name="themeId"
                render={({ field }) => (
                  <Select
                    id="template-theme"
                    fieldSize="comfortable"
                    value={field.value}
                    onChange={(e) => field.onChange(e.target.value)}
                    onBlur={field.onBlur}
                    disabled={themesQuery.isLoading}
                  >
                    <option value="">
                      {themesQuery.isLoading ? 'Cargando…' : '— Sin theme —'}
                    </option>
                    {(themesQuery.data ?? []).map((t) => (
                      <option key={t.id} value={t.id}>
                        {t.name}
                      </option>
                    ))}
                  </Select>
                )}
              />
              <p className="mt-1 text-xs text-text-muted">
                Define paleta, tipografías, logo y layout. Se aplica al previsualizar y al exportar PDF.
              </p>
              {themesQuery.isError && (
                <p className="mt-1 text-xs text-danger-dark dark:text-danger">
                  No se pudieron cargar los themes publicados.
                </p>
              )}
            </div>
            <div className="flex items-end">
              <ThemeMiniPreview theme={selectedTheme} variant="full" className="w-full" />
            </div>
          </div>
        </div>

      </div>
    </div>
  );
}
