import { useEffect, useState } from 'react';
import { FieldLabel, Select, TextArea, TextInput } from '../../../ui';
import { VISIBILITY_OPTIONS } from '../constants';
import { useHierarchy } from '../../../features/hierarchy';
import { fetchGroups } from '../../../api/groups';
import type { Group } from '../../../types/groups';
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
  groupId: string;
  setGroupId: (v: string) => void;
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
  groupId, setGroupId,
  errors,
}: Props) {
  const { hierarchy, loading: hierarchyLoading } = useHierarchy();
  const [groups, setGroups] = useState<Group[]>([]);

  useEffect(() => {
    fetchGroups(200).then((res) => setGroups(res.data)).catch(() => undefined);
  }, []);

  const selectedTypeData = hierarchy.find((t) => t.id === studyTypeId);
  const availableStudies = selectedTypeData ? selectedTypeData.studies : [];
  const selectedStudyData = availableStudies.find((s) => s.id === studyId);
  const availableModules = selectedStudyData ? selectedStudyData.course_modules : [];

  const showAcademicBlock = visibility !== 'personal' && visibility !== 'global';

  // Dynamic titles and field requirements
  const academicTitles: Record<string, string> = {
    study_type: 'Tipo de Estudio',
    study: 'Estudio',
    module: 'Módulo',
    group: 'Grupo',
  };

  return (
    <div className="flex-1 overflow-y-auto p-6">

      <div className="max-w-3xl mx-auto space-y-6">
        {/* Generales */}
        <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card p-6">
          <h3 className="text-xs font-bold uppercase tracking-wider text-text-secondary dark:text-text-dark-secondary mb-6">
            Datos generales
          </h3>
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
                  if (v === 'personal' || v === 'global') {
                    setStudyTypeId('');
                    setStudyId('');
                    setModuleId('');
                    setGroupId('');
                  }
                }}
              >
                {VISIBILITY_OPTIONS.map((o) => (
                  <option key={o.value} value={o.value}>
                    {o.label}
                  </option>
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
        </div>

        {/* Bloque de vinculación académica (condicional con animación) */}
        {showAcademicBlock && (
          <div className="wizard-academic-block bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card p-6 animate-in fade-in slide-in-from-top-1 duration-200">
            <h3 className="text-xs font-bold uppercase tracking-wider text-text-secondary dark:text-text-dark-secondary mb-6">
              {academicTitles[visibility] || 'Vinculación Académica'}
            </h3>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {/* Tipo de Estudio */}
              <div>
                <FieldLabel required>Tipo de Estudio</FieldLabel>
                <Select
                  fieldSize="comfortable"
                  value={studyTypeId}
                  disabled={hierarchyLoading}
                  onChange={(e) => {
                    setStudyTypeId(e.target.value);
                    setStudyId('');
                    setModuleId('');
                    setGroupId('');
                  }}
                  error={!!errors.studyTypeId}
                >
                  <option value="">— Seleccionar —</option>
                  {hierarchy.map((t) => (
                    <option key={t.id} value={t.id}>{t.name}</option>
                  ))}
                </Select>
                {errors.studyTypeId && <p className="mt-1 text-xs text-danger-dark dark:text-danger">{errors.studyTypeId}</p>}
              </div>

              {/* Estudio (visible if visibility is study, module, group) */}
              {(visibility === 'study' || visibility === 'module' || visibility === 'group') && (
                <div>
                  <FieldLabel required>Estudio</FieldLabel>
                  <Select
                    fieldSize="comfortable"
                    value={studyId}
                    disabled={!studyTypeId}
                    onChange={(e) => {
                      setStudyId(e.target.value);
                      setModuleId('');
                      setGroupId('');
                    }}
                    error={!!errors.studyId}
                  >
                    <option value="">— Seleccionar —</option>
                    {availableStudies.map((s) => (
                      <option key={s.id} value={s.id}>{s.name}</option>
                    ))}
                  </Select>
                  {errors.studyId && <p className="mt-1 text-xs text-danger-dark dark:text-danger">{errors.studyId}</p>}
                </div>
              )}

              {/* Módulo (visible if visibility is module, group) */}
              {(visibility === 'module' || visibility === 'group') && (
                <div>
                  <FieldLabel required>Módulo</FieldLabel>
                  <Select
                    fieldSize="comfortable"
                    value={moduleId}
                    disabled={!studyId}
                    onChange={(e) => {
                      setModuleId(e.target.value);
                      setGroupId('');
                    }}
                    error={!!errors.moduleId}
                  >
                    <option value="">— Seleccionar —</option>
                    {availableModules.map((m) => (
                      <option key={m.id} value={m.id}>{m.name}</option>
                    ))}
                  </Select>
                  {errors.moduleId && <p className="mt-1 text-xs text-danger-dark dark:text-danger">{errors.moduleId}</p>}
                </div>
              )}

              {/* Grupo (visible if visibility is group) */}
              {visibility === 'group' && (
                <div>
                  <FieldLabel required>Grupo</FieldLabel>
                  <Select
                    fieldSize="comfortable"
                    value={groupId}
                    disabled={!moduleId}
                    onChange={(e) => setGroupId(e.target.value)}
                    error={!!errors.groupId}
                  >
                    <option value="">— Seleccionar —</option>
                    {groups.map((g) => (
                      <option key={g.id} value={g.id}>{g.name}</option>
                    ))}
                  </Select>
                  {errors.groupId && <p className="mt-1 text-xs text-danger-dark dark:text-danger">{errors.groupId}</p>}
                </div>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
