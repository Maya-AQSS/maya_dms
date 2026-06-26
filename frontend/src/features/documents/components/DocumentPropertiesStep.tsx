import { DatePicker, FieldLabel, Select, TextInput } from '@ceedcv-maya/shared-ui-react';
import type { ChangeEvent } from 'react';
import { useTranslation } from 'react-i18next';
import type { DocumentDetail } from '../../../types/documents';
import type { AcademicHierarchy, CourseModule, Study } from '../../../types/hierarchy';
import type { Template } from '../../../types/templates';
import type { User } from '../../../types/users';
import { visibilityLabel } from '../../templates/constants';
import type { VisibilityRuleMode } from './documentWizardUtils';
import type { DocumentStep1Form } from './useDocumentStep1Form';

interface VisibilityFlags {
  rule: VisibilityRuleMode;
  studyTypeEditable: boolean;
  studyEditable: boolean;
  moduleEditable: boolean;
  teamEditable: boolean;
  requireStudyType: boolean;
  requireStudy: boolean;
  requireModule: boolean;
  isGlobalAcademicMode: boolean;
  fixedTeamId: string;
}

interface OwnerSearchState {
  query: string;
  setQuery: (v: string) => void;
  results: User[];
  setResults: (v: User[]) => void;
  searching: boolean;
  newOwner: { id: string; name: string } | null;
  setNewOwner: (v: { id: string; name: string } | null) => void;
}

interface DocumentPropertiesStepProps {
  form: DocumentStep1Form;
  isDraft: boolean;
  formError: string | null;
  template: Template | null;
  templateScopeLabel: string | null;
  visibility: VisibilityFlags;
  hierarchy: AcademicHierarchy;
  hierarchyLoading: boolean;
  availableTeams: { id: string; name: string }[];
  filteredStudies: Study[];
  filteredModules: CourseModule[];
  detail: DocumentDetail | null;
  currentUserId: string | null;
  profileName?: string | null;
  ownerSearch: OwnerSearchState;
  onChangeTemplate: () => void;
}

/** Step 1: title, deadline, template summary, academic context selectors, owner transfer. */
export function DocumentPropertiesStep({
  form,
  isDraft,
  formError,
  template,
  templateScopeLabel,
  visibility,
  hierarchy,
  hierarchyLoading,
  availableTeams,
  filteredStudies,
  filteredModules,
  detail,
  currentUserId,
  profileName,
  ownerSearch,
  onChangeTemplate,
}: DocumentPropertiesStepProps) {
  const { t } = useTranslation(['documents', 'common', 'templates']);
  const {
    title,
    setTitle,
    deliveryDeadline,
    setDeliveryDeadline,
    studyTypeId,
    setStudyTypeId,
    studyId,
    setStudyId,
    moduleId,
    setModuleId,
    teamId,
    setTeamId,
    errors,
    setErrors,
  } = form;
  const visibilityRule = visibility.rule;

  return (
    <div className="flex-1 min-h-0 flex flex-col bg-ui-card dark:bg-ui-dark-card overflow-hidden">
      <div className="flex-1 overflow-y-auto px-8 py-6 space-y-6">
        {formError && (
          <div className="rounded-lg border border-danger/30 bg-danger/5 px-4 py-3 text-xs text-danger-dark dark:text-danger">
            {formError}
          </div>
        )}

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="md:col-span-2">
            <FieldLabel required htmlFor="doc-title-input">
              {t('common:fields.name')}
            </FieldLabel>
            <TextInput
              id="doc-title-input"
              type="text"
              fieldSize="comfortable"
              value={title}
              onChange={(e: ChangeEvent<HTMLInputElement>) => {
                setTitle(e.target.value);
                setErrors((prev: Record<string, string>) => ({ ...prev, title: '' }));
              }}
              disabled={!isDraft}
              placeholder={t('documents:wizard.namePlaceholder')}
              error={!!errors.title}
            />
            {errors.title && (
              <p className="text-xs text-danger-dark dark:text-danger">{errors.title}</p>
            )}
          </div>

          <div>
            <FieldLabel required htmlFor="doc-delivery-deadline-input">
              {t('documents:fields.deadline')}
            </FieldLabel>
            <DatePicker
              value={deliveryDeadline || null}
              onChange={() => {}}
              disabled
              placeholder={t('documents:wizard.selectDate')}
              ariaLabel={t('documents:fields.deadline')}
            />
          </div>
        </div>

        {template && (
          <div className="pt-5 border-t border-ui-border dark:border-ui-dark-border">
            <h3 className="mb-3 text-xs font-black uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary">
              Plantilla base
            </h3>
            <div className="flex items-center justify-between gap-3">
              <span className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
                {template.name}
              </span>
              <button
                type="button"
                onClick={onChangeTemplate}
                className="text-xs text-odoo-purple dark:text-odoo-dark-purple hover:underline cursor-pointer shrink-0"
              >
                Cambiar plantilla
              </button>
            </div>
            <div className="mt-2 flex flex-wrap gap-2 text-xs text-text-secondary dark:text-text-dark-secondary">
              <span className="rounded border border-ui-border dark:border-ui-dark-border px-2 py-0.5">
                {t('templates:fields.visibility')}: {visibilityLabel(template.visibility_level, t)}
              </span>
              {templateScopeLabel && template.visibility_level !== 'team' && (
                <span className="rounded border border-ui-border dark:border-ui-dark-border px-2 py-0.5">
                  Ámbito: {templateScopeLabel}
                </span>
              )}
              {(visibilityRule === 'team' || visibilityRule === 'global') &&
                (template.team?.name || visibility.fixedTeamId || teamId) && (
                  <span className="rounded border border-ui-border dark:border-ui-dark-border px-2 py-0.5">
                    Equipo:{' '}
                    {template.team?.name ??
                      availableTeams.find((tm) => tm.id === (visibility.fixedTeamId || teamId))
                        ?.name ??
                      'Asignado'}
                  </span>
                )}
            </div>
          </div>
        )}

        <div className="pt-5 border-t border-ui-border dark:border-ui-dark-border">
          <p className="mb-4 text-xs text-text-muted dark:text-text-dark-muted">
            Selecciona el contexto académico donde se archivará esta programación.
          </p>
          <div className="space-y-4">
            {visibility.teamEditable && (
              <div className="space-y-1">
                <FieldLabel>{t('wizard.teamOptional')}</FieldLabel>
                <Select
                  fieldSize="comfortable"
                  value={teamId}
                  disabled={!isDraft || !visibility.teamEditable}
                  onChange={(e: ChangeEvent<HTMLSelectElement>) => {
                    setTeamId(e.target.value);
                    if (e.target.value) {
                      setStudyTypeId('');
                      setStudyId('');
                      setModuleId('');
                    }
                    setErrors((prev: Record<string, string>) => ({
                      ...prev,
                      studyTypeId: '',
                      studyId: '',
                      moduleId: '',
                    }));
                  }}
                >
                  <option value="">— Sin equipo (global/académico) —</option>
                  {availableTeams.map((tm) => (
                    <option key={tm.id} value={tm.id}>
                      {tm.name}
                    </option>
                  ))}
                </Select>
              </div>
            )}

            <div className="space-y-1">
              <FieldLabel required={visibility.requireStudyType}>
                {t('fields.studyType')}
              </FieldLabel>
              <Select
                fieldSize="comfortable"
                value={studyTypeId}
                disabled={
                  hierarchyLoading ||
                  !isDraft ||
                  !visibility.studyTypeEditable ||
                  (visibilityRule === 'global' && !visibility.isGlobalAcademicMode)
                }
                onChange={(e) => {
                  setStudyTypeId(e.target.value);
                  setStudyId('');
                  setModuleId('');
                  if (visibilityRule === 'global') setTeamId('');
                  setErrors((prev: Record<string, string>) => ({
                    ...prev,
                    studyTypeId: '',
                    studyId: '',
                    moduleId: '',
                  }));
                }}
                error={!!errors.studyTypeId}
              >
                {hierarchy.length === 0 && !hierarchyLoading ? (
                  <option value="" disabled>
                    {t('wizard.noStudyTypes')}
                  </option>
                ) : (
                  <option value="">{hierarchyLoading ? 'Cargando…' : '— Seleccionar —'}</option>
                )}
                {hierarchy.map((st) => (
                  <option key={st.id} value={st.id}>
                    {st.name}
                  </option>
                ))}
              </Select>
              {errors.studyTypeId && (
                <p className="text-xs text-danger-dark dark:text-danger">{errors.studyTypeId}</p>
              )}
            </div>

            <div className="space-y-1">
              <FieldLabel required={visibility.requireStudy}>{t('fields.study')}</FieldLabel>
              <Select
                fieldSize="comfortable"
                value={studyId}
                disabled={
                  hierarchyLoading ||
                  !studyTypeId ||
                  !isDraft ||
                  !visibility.studyEditable ||
                  (visibilityRule === 'global' && !visibility.isGlobalAcademicMode)
                }
                onChange={(e: ChangeEvent<HTMLSelectElement>) => {
                  setStudyId(e.target.value);
                  setModuleId('');
                  if (visibilityRule === 'global') setTeamId('');
                  setErrors((prev: Record<string, string>) => ({
                    ...prev,
                    studyId: '',
                    moduleId: '',
                  }));
                }}
                error={!!errors.studyId}
              >
                <option value="">— Seleccionar —</option>
                {filteredStudies.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name}
                  </option>
                ))}
              </Select>
              {errors.studyId && (
                <p className="text-xs text-danger-dark dark:text-danger">{errors.studyId}</p>
              )}
            </div>

            <div className="space-y-1">
              <FieldLabel required={visibility.requireModule}>
                {t('documents:fields.module')}
              </FieldLabel>
              <Select
                fieldSize="comfortable"
                value={moduleId}
                disabled={
                  hierarchyLoading ||
                  !studyId ||
                  !isDraft ||
                  !visibility.moduleEditable ||
                  (visibilityRule === 'global' && !visibility.isGlobalAcademicMode)
                }
                onChange={(e: ChangeEvent<HTMLSelectElement>) => {
                  setModuleId(e.target.value);
                  if (visibilityRule === 'global') setTeamId('');
                  setErrors((prev: Record<string, string>) => ({ ...prev, moduleId: '' }));
                }}
                error={!!errors.moduleId}
              >
                <option value="">— Seleccionar —</option>
                {filteredModules.map((m) => (
                  <option key={m.id} value={m.id}>
                    {m.name}
                  </option>
                ))}
              </Select>
              {errors.moduleId && (
                <p className="text-xs text-danger-dark dark:text-danger">{errors.moduleId}</p>
              )}
            </div>
          </div>
        </div>

        {((isDraft && detail?.owner_id === currentUserId) || !detail?.owner_id) && (
          <div className="pt-5 border-t border-ui-border dark:border-ui-dark-border animate-in slide-in-from-top-2 fade-in space-y-3">
            <p className="text-xs font-black uppercase tracking-widest text-text-secondary dark:text-text-dark-secondary">
              Propietario
            </p>
            <div className="flex items-center gap-2">
              <span className="text-xs text-text-secondary dark:text-text-dark-secondary">
                {t('wizard.current')}
              </span>
              <span className="text-xs font-semibold text-text-primary dark:text-text-dark-primary">
                {ownerSearch.newOwner
                  ? ownerSearch.newOwner.name
                  : (detail?.owner_name ?? profileName ?? '—')}
              </span>
              {ownerSearch.newOwner && (
                <button
                  type="button"
                  onClick={() => {
                    ownerSearch.setNewOwner(null);
                    ownerSearch.setQuery('');
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
                placeholder={t('wizard.searchOwner')}
                value={ownerSearch.query}
                onChange={(e: ChangeEvent<HTMLInputElement>) =>
                  ownerSearch.setQuery(e.target.value)
                }
              />
            </div>
            {ownerSearch.query.trim().length > 0 && ownerSearch.query.trim().length < 2 && (
              <p className="text-xs text-text-muted italic">{t('common:search.minChars')}</p>
            )}
            {ownerSearch.searching && (
              <p className="text-xs text-text-muted italic">{t('common:searching')}</p>
            )}
            {!ownerSearch.searching && ownerSearch.results.length > 0 && (
              <ul className="border border-ui-border dark:border-ui-dark-border rounded-lg overflow-hidden divide-y divide-ui-border dark:divide-ui-dark-border">
                {ownerSearch.results.map((u) => (
                  <li key={u.id}>
                    <button
                      type="button"
                      onClick={() => {
                        ownerSearch.setNewOwner({ id: u.id, name: u.name });
                        ownerSearch.setQuery('');
                        ownerSearch.setResults([]);
                      }}
                      className="w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-odoo-purple/5 transition-colors"
                    >
                      <span className="shrink-0 flex items-center justify-center w-7 h-7 rounded-full bg-odoo-purple/10 text-odoo-purple text-xs font-black border border-odoo-purple/20">
                        {u.name
                          .split(' ')
                          .filter(Boolean)
                          .slice(0, 2)
                          .map((w: string) => w[0]?.toUpperCase() ?? '')
                          .join('')}
                      </span>
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-semibold text-text-primary dark:text-text-dark-primary truncate">
                          {u.name}
                        </p>
                        {u.role && (
                          <p className="text-xs text-text-secondary dark:text-text-dark-secondary">
                            {u.role}
                          </p>
                        )}
                      </div>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
