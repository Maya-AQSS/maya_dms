import { useEffect, useMemo, useState } from 'react';
import { Controller, useFormContext, useWatch } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { DatePicker, FieldLabel, Select, TextArea, TextInput } from '@maya/shared-ui-react';
import { VISIBILITY_OPTIONS } from '../constants';
import { useHierarchy } from '../../../features/hierarchy';
import { useUserProfile } from '../../../features/user-profile';
import { usePublishedThemes } from '../../../features/themes/hooks/usePublishedThemes';
import { ThemeA4Preview } from '../../../features/themes/components/ThemeA4Preview';
import { searchOwnerCandidates, type UserTeam } from '../../../api/users';
import type { User } from '../../../types/users';
import { DMS_PERMISSIONS } from '../../../permissions';
import type { TemplateStatus, TemplateVisibilityLevel } from '../../../types/templates';
import type { TemplateStep1Input } from '../schemas/templateStep1';

type Props = {
  errors?: { api?: string };
  templateStatus?: TemplateStatus;
  isCreator?: boolean;
  currentAuthorName?: string | null;
};

export function WizardStep1Properties({ errors, templateStatus, isCreator, currentAuthorName }: Props) {
  const { t } = useTranslation('templates');
  const deadlineLocked = templateStatus === 'in_review' || templateStatus === 'published';
  const { hierarchy, teams: hierarchyTeams, loading: hierarchyLoading, error: hierarchyError } = useHierarchy();
  const { hasPermission } = useUserProfile();
  const teams: UserTeam[] = hierarchyTeams;
  const teamsLoading = hierarchyLoading;
  const teamsError = hierarchyError
    ? 'No se pudo cargar el contexto académico (equipos). Revisa la sesión o inténtalo de nuevo.'
    : null;

  const {
    control,
    register,
    setValue,
    watch,
    formState: { errors: formErrors },
  } = useFormContext<TemplateStep1Input>();

  const [ownerQuery, setOwnerQuery] = useState('');
  const [ownerResults, setOwnerResults] = useState<User[]>([]);
  const [ownerSearching, setOwnerSearching] = useState(false);
  const [selectedOwnerName, setSelectedOwnerName] = useState<string | null>(null);
  const createdBy = watch('createdBy');

  useEffect(() => {
    const q = ownerQuery.trim();
    if (q.length < 2) {
      setOwnerResults([]);
      return;
    }
    const timer = setTimeout(() => {
      setOwnerSearching(true);
      searchOwnerCandidates(q)
        .then((res) => setOwnerResults(res.data))
        .catch(() => setOwnerResults([]))
        .finally(() => setOwnerSearching(false));
    }, 300);
    return () => clearTimeout(timer);
  }, [ownerQuery]);

  const visibility = useWatch({ control, name: 'visibility' });
  const studyTypeId = useWatch({ control, name: 'studyTypeId' });
  const studyId = useWatch({ control, name: 'studyId' });

  const canCreateShared = hasPermission(DMS_PERMISSIONS.templateCreate);
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

  /**
   * Estado de cascada progresiva: qué selects mostrar y cuántas columnas usar.
   * Memoizado para evitar recalcular en cada render del wizard.
   */
  const cascadeState = useMemo(() => {
    const needsStudyType =
      visibility === 'study_type' || visibility === 'study' || visibility === 'module';
    const showStudy = (visibility === 'study' || visibility === 'module') && !!studyTypeId;
    const showModule = visibility === 'module' && !!studyId;
    const visibleCols = 1 + (showStudy ? 1 : 0) + (showModule ? 1 : 0);
    const gridColsClass =
      visibleCols === 3 ? 'grid-cols-3' : visibleCols === 2 ? 'grid-cols-2' : 'grid-cols-1';
    return { needsStudyType, showStudy, showModule, gridColsClass };
  }, [visibility, studyTypeId, studyId]);

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
            <FieldLabel required>{t('tables.name')}</FieldLabel>
            <TextInput
              type="text"
              fieldSize="comfortable"
              placeholder={t('placeholders.templateName')}
              error={!!formErrors.name}
              {...register('name')}
            />
            {formErrors.name?.message && (
              <p className="mt-1 text-xs text-danger-dark dark:text-danger">{formErrors.name.message}</p>
            )}
          </div>

          <div className="md:col-span-2">
            <FieldLabel>{t('tables.description')}</FieldLabel>
            <TextArea
              fieldSize="comfortable"
              placeholder={t('wizard.purposePlaceholder')}
              style={{ minHeight: '64px' }}
              {...register('description')}
            />
          </div>

          <div>
            <FieldLabel required>{t('fields.visibility')}</FieldLabel>
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
            <FieldLabel htmlFor="delivery-deadline" required>{t('fields.deadline')}</FieldLabel>
            <Controller
              control={control}
              name="deliveryDeadline"
              render={({ field }) => (
                <DatePicker
                  value={field.value || null}
                  onChange={(d: string | null) => field.onChange(d ?? '')}
                  disabled={deadlineLocked}
                  placeholder={t('wizard.selectDatePlaceholder')}
                  ariaLabel={t('fields.deadline')}
                />
              )}
            />
            {formErrors.deliveryDeadline?.message && (
              <p className="mt-1 text-xs text-danger-dark dark:text-danger">{formErrors.deliveryDeadline.message}</p>
            )}
            {deadlineLocked && (
              <p className="mt-1 text-xs text-text-muted italic">
                {t('notEditableInState')}
              </p>
            )}
          </div>
        </div>

        {showAcademicBlock && cascadeState && (
          <div className="pt-5 border-t border-ui-border dark:border-ui-dark-border animate-in slide-in-from-top-2 fade-in">
            {/*
              Cascada progresiva: el siguiente select aparece solo cuando el
              anterior tiene valor. Evita mostrar 3 selects vacíos a la vez en
              visibility=module — el usuario navega un paso a la vez.
              Estado memoizado en `cascadeState` (declaración arriba) para evitar
              re-evaluación en cada render.
            */}
            <div className={`grid gap-4 ${cascadeState.gridColsClass}`}>
                  {cascadeState.needsStudyType && (
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
                              <option value="" disabled>{t('noStudyTypesAssigned')}</option>
                            ) : (
                              <option value="">{t('selectPlaceholder')}</option>
                            )}
                            {hierarchy.map((opt) => (
                              <option key={opt.id} value={opt.id}>{opt.name}</option>
                            ))}
                          </Select>
                        )}
                      />
                      {formErrors.studyTypeId?.message && (
                        <p className="mt-1 text-xs text-danger-dark dark:text-danger">{formErrors.studyTypeId.message}</p>
                      )}
                    </div>
                  )}

                  {cascadeState.showStudy && (
                    <div className="animate-in slide-in-from-left-2 fade-in">
                      <Controller
                        control={control}
                        name="studyId"
                        render={({ field }) => (
                          <Select
                            fieldSize="comfortable"
                            value={field.value}
                            disabled={hierarchyLoading || filteredStudies.length === 0}
                            onChange={(e) => {
                              field.onChange(e.target.value);
                              setValue('moduleId', '', { shouldDirty: true });
                            }}
                            onBlur={field.onBlur}
                            error={!!formErrors.studyId}
                          >
                            <option value="">{t('selectPlaceholder')}</option>
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

                  {cascadeState.showModule && (
                    <div className="animate-in slide-in-from-left-2 fade-in">
                      <Select
                        fieldSize="comfortable"
                        disabled={hierarchyLoading || filteredModules.length === 0}
                        error={!!formErrors.moduleId}
                        {...register('moduleId')}
                      >
                        <option value="">{t('selectPlaceholder')}</option>
                        {filteredModules.map((m) => (
                          <option key={m.id} value={m.id}>{m.name}</option>
                        ))}
                      </Select>
                      {formErrors.moduleId?.message && (
                        <p className="mt-1 text-xs text-danger-dark dark:text-danger">{formErrors.moduleId.message}</p>
                      )}
                    </div>
                  )}
            </div>

            {visibility === 'team' && (
              <div>
                <Select
                  fieldSize="comfortable"
                  disabled={teamsLoading || !teams.length}
                  error={!!formErrors.teamId}
                  {...register('teamId')}
                >
                  <option value="">
                    {teamsLoading ? t('loadingTeams') : t('selectPlaceholder')}
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
        )}

        {/* ─── Seleccionar editor ───────────────────── */}
        {(isCreator || !currentAuthorName) && (
          <div className="pt-5 border-t border-ui-border dark:border-ui-dark-border animate-in slide-in-from-top-2 fade-in">
            <FieldLabel>Propietario</FieldLabel>
            <div className="mt-1 mb-2 flex items-center gap-2">
              <span className="text-xs text-text-secondary dark:text-text-dark-secondary">
                Actual:
              </span>
              <span className="text-xs font-semibold text-text-primary dark:text-text-dark-primary">
                {createdBy ? (selectedOwnerName ?? '—') : (currentAuthorName ?? '—')}
              </span>
              {createdBy && (
                <button
                  type="button"
                  onClick={() => {
                    setValue('createdBy', undefined);
                    setSelectedOwnerName(null);
                    setOwnerQuery('');
                  }}
                  className="text-xs text-danger-dark hover:underline"
                >
                  Deshacer
                </button>
              )}
            </div>
            <div className="relative">
              <TextInput
                type="search"
                fieldSize="comfortable"
                placeholder="Buscar usuario por nombre…"
                value={ownerQuery}
                onChange={(e) => setOwnerQuery(e.target.value)}
                className="pl-9"
              />
              <svg className="absolute left-3 top-2.5 w-4 h-4 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            {ownerQuery.trim().length > 0 && ownerQuery.trim().length < 2 && (
              <p className="mt-1 text-xs text-text-muted italic">Escribe al menos 2 caracteres para buscar.</p>
            )}
            {ownerSearching && <p className="mt-1 text-xs text-text-muted italic">Buscando…</p>}
            {!ownerSearching && ownerResults.length > 0 && (
              <ul className="mt-1 border border-ui-border dark:border-ui-dark-border rounded-lg overflow-hidden divide-y divide-ui-border dark:divide-ui-dark-border">
                {ownerResults.map((u) => (
                  <li key={u.id}>
                    <button
                      type="button"
                      onClick={() => {
                        setValue('createdBy', u.id, { shouldDirty: true });
                        setSelectedOwnerName(u.name);
                        setOwnerQuery('');
                        setOwnerResults([]);
                      }}
                      className="w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-odoo-purple/5 transition-colors"
                    >
                      <span className="shrink-0 flex items-center justify-center w-7 h-7 rounded-full bg-odoo-purple/10 text-odoo-purple text-xs font-black border border-odoo-purple/20">
                        {u.name.split(' ').filter(Boolean).slice(0, 2).map((w) => w[0]?.toUpperCase() ?? '').join('')}
                      </span>
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-semibold text-text-primary dark:text-text-dark-primary truncate">{u.name}</p>
                        {u.role && <p className="text-xs text-text-secondary dark:text-text-dark-secondary">{u.role}</p>}
                      </div>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>
        )}

        {/* ─── Identidad visual (theme opcional) ───────────────────── */}
        <div className="pt-5 border-t border-ui-border dark:border-ui-dark-border">
          <h3 className="mb-3 text-xs font-black uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary">
            {t('visualIdentity')}
          </h3>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <FieldLabel htmlFor="template-theme">{t('fields.theme')}</FieldLabel>
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
            <div className="flex justify-center md:justify-start">
              <ThemeA4Preview theme={selectedTheme} />
            </div>
          </div>
        </div>

      </div>
    </div>
  );
}
