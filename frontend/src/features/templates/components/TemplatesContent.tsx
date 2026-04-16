import { useEffect, useMemo, useState } from 'react';
import type { Template, TemplateVisibilityLevel } from '../../../types/templates';
import { useTemplates } from '../hooks/useTemplates';
import { STATUS_OPTIONS, VISIBILITY_OPTIONS } from '../constants';
import { datetimeLocalToIso } from '../templateFormUtils';
import { Button, FieldLabel, Select, TextInput } from '../../../ui';
import { TemplateCard } from './TemplateCard';
import { TemplateHierarchyFields } from './TemplateHierarchyFields';
import { TemplateBlockEditor } from './TemplateBlockEditor';
import { useHierarchy } from '../../../features/hierarchy';
import { fetchGroups } from '../../../api/groups';
import type { Group } from '../../../types/groups';

/**
 * Gestión de plantillas normativas: datos vía {@link useTemplates}.
 */
export function TemplatesContent() {
  const {
    templates,
    meta,
    filters,
    loading,
    listError,
    actionError,
    actionInfo,
    clearActionError,
    clearActionInfo,
    refetch,
    applyFilters,
    goToPage,
    createTemplate,
    deleteTemplate,
    cloneTemplate,
  } = useTemplates();

  const [newName, setNewName] = useState('');
  const [newDesc, setNewDesc] = useState('');
  const [newVisibility, setNewVisibility] = useState<TemplateVisibilityLevel>('personal');
  const [newDeadline, setNewDeadline] = useState('');
  const [newStudyTypeId, setNewStudyTypeId] = useState('');
  const [newStudyId, setNewStudyId] = useState('');
  const [newModuleId, setNewModuleId] = useState('');
  const [newGroupId, setNewGroupId] = useState('');
  const [creating, setCreating] = useState(false);

  const [viewMode, setViewMode] = useState<'list' | 'create'>('list');
  const [editingTemplate, setEditingTemplate] = useState<Template | null>(null);

  const filterUi = useMemo(
    () => ({
      visibility: filters.visibility_level ?? '',
      status: filters.status ?? '',
      studyTypeId: filters.study_type_id ?? '',
      studyId: filters.study_id ?? '',
      moduleId: filters.module_id ?? '',
      groupId: filters.group_id ?? '',
    }),
    [filters],
  );

  // ── Academic hierarchy for cascade filter dropdowns ──────────────────────
  const { hierarchy, loading: hierarchyLoading } = useHierarchy();
  const [groups, setGroups] = useState<Group[]>([]);

  useEffect(() => {
    fetchGroups(200).then((res) => setGroups(res.data)).catch(() => undefined);
  }, []);

  const selectedTypeData = hierarchy.find((t) => t.id === filterUi.studyTypeId);
  const availableStudies = selectedTypeData ? selectedTypeData.studies : [];
  const selectedStudyData = availableStudies.find((s) => s.id === filterUi.studyId);
  const availableModules = selectedStudyData ? selectedStudyData.course_modules : [];

  const clearFilters = () => {
    applyFilters({
      visibility_level: undefined,
      status: undefined,
      study_type_id: undefined,
      study_id: undefined,
      module_id: undefined,
      group_id: undefined,
    });
  };

  const handleCreate = async () => {
    if (!newName.trim()) return;
    setCreating(true);
    try {
      const newTemplate = await createTemplate({
        name: newName.trim(),
        description: newDesc.trim() === '' ? null : newDesc.trim(),
        visibility_level: newVisibility,
        delivery_deadline: datetimeLocalToIso(newDeadline),
        study_type_id: newStudyTypeId.trim() === '' ? null : newStudyTypeId.trim(),
        study_id: newStudyId.trim() === '' ? null : newStudyId.trim(),
        module_id: newModuleId.trim() === '' ? null : newModuleId.trim(),
        group_id: newGroupId.trim() === '' ? null : newGroupId.trim(),
      });
      
      setNewName('');
      setNewDesc('');
      setNewVisibility('personal');
      setNewDeadline('');
      setNewStudyTypeId('');
      setNewStudyId('');
      setNewModuleId('');
      setNewGroupId('');
      
      setViewMode('list');
      
      // Auto-abrir editor
      setEditingTemplate(newTemplate);
    } catch {
      /* el hook ya dejó el mensaje en actionError */
    } finally {
      setCreating(false);
    }
  };

  return (
    <div className="p-6 space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
            Plantillas normativas
          </h2>
          <p className="text-xs text-text-muted dark:text-text-dark-muted mt-1">
            Listado según tu visibilidad en la API (máx. 20 por página). La visibilidad compartida en alta/edición
            requiere roles de coordinación.
          </p>
        </div>
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => void refetch()}
          disabled={loading}
        >
          Actualizar
        </Button>
        <Button
          type="button"
          variant="primary"
          size="sm"
          onClick={() => setViewMode('create')}
        >
          Nueva Plantilla
        </Button>
      </div>

      {listError && (
        <div className="rounded-lg border border-warning/40 bg-warning-light/40 dark:bg-warning-dark/10 px-4 py-3 text-sm text-warning-dark dark:text-warning-light">
          {listError}
        </div>
      )}

      {actionError && (
        <div className="rounded-lg border border-odoo-purple/30 bg-odoo-purple/5 px-4 py-3 text-sm text-text-primary dark:text-text-dark-primary dark:border-odoo-dark-purple/40 dark:bg-odoo-dark-purple/15 flex justify-between gap-4">
          <span>{actionError}</span>
          <Button type="button" variant="ghost" size="xs" onClick={clearActionError} className="shrink-0">
            Cerrar
          </Button>
        </div>
      )}

      {actionInfo && (
        <div className="rounded-lg border border-odoo-teal/30 bg-odoo-teal/5 px-4 py-3 text-sm text-text-primary dark:text-text-dark-primary dark:border-odoo-dark-teal/45 dark:bg-odoo-dark-teal/15 flex justify-between gap-4">
          <span>{actionInfo}</span>
          <Button type="button" variant="ghost" size="xs" onClick={clearActionInfo} className="shrink-0">
            Cerrar
          </Button>
        </div>
      )}

      {viewMode === 'list' ? (
        <>
          <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card p-5 space-y-3">
            <h3 className="text-xs font-semibold uppercase tracking-wide text-text-secondary dark:text-text-dark-secondary">
              Filtros
            </h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
              <div>
                <FieldLabel>Visibilidad</FieldLabel>
                <Select
                  fieldSize="sm"
                  value={filterUi.visibility}
                  onChange={(e) =>
                    applyFilters({
                      visibility_level: e.target.value || undefined,
                    })
                  }
                >
                  <option value="">Todas</option>
                  {VISIBILITY_OPTIONS.map((o) => (
                    <option key={o.value} value={o.value}>
                      {o.label}
                    </option>
                  ))}
                </Select>
              </div>
              <div>
                <FieldLabel>Estado</FieldLabel>
                <Select
                  fieldSize="sm"
                  value={filterUi.status}
                  onChange={(e) => applyFilters({ status: e.target.value || undefined })}
                >
                  {STATUS_OPTIONS.map((o) => (
                    <option key={o.value || 'all'} value={o.value}>
                      {o.label}
                    </option>
                  ))}
                </Select>
              </div>
              <div className="lg:col-span-3">
                <div className="flex flex-col lg:flex-row items-end gap-3">
                  <div className="flex-1">
                    <TemplateHierarchyFields
                      values={{
                        study_type_id: filterUi.studyTypeId,
                        study_id: filterUi.studyId,
                        module_id: filterUi.moduleId,
                        group_id: filterUi.groupId,
                      }}
                      onFieldChange={(key, value) =>
                        applyFilters({ [key]: value.trim() === '' ? undefined : value.trim() })
                      }
                      gridClassName="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3"
                    />
                  </div>
                  <Button
                    type="button"
                    variant="secondary"
                    size="md"
                    onClick={clearFilters}
                    className="h-9.5 whitespace-nowrap shrink-0"
                  >
                    Limpiar filtros
                  </Button>
                </div>
              </div>
            </div>
          </div>

          {loading && templates.length === 0 ? (
            <p className="text-sm text-text-muted dark:text-text-dark-muted">Cargando plantillas…</p>
          ) : null}

          {!loading && templates.length === 0 && !listError ? (
            <div className="text-center py-8 space-y-2 max-w-lg mx-auto">
              <p className="text-sm text-text-muted dark:text-text-dark-muted">
                No hay plantillas visibles con los filtros actuales.
              </p>
            </div>
          ) : null}

          {meta ? (
            <div className="flex flex-wrap items-center justify-between gap-3 text-xs text-text-muted dark:text-text-dark-muted">
              <span>
                Página {meta.current_page} de {meta.last_page} — {meta.total} plantillas
              </span>
              <div className="flex gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="xs"
                  disabled={loading || meta.current_page <= 1}
                  onClick={() => goToPage(meta.current_page - 1)}
                >
                  Anterior
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  size="xs"
                  disabled={loading || meta.current_page >= meta.last_page}
                  onClick={() => goToPage(meta.current_page + 1)}
                >
                  Siguiente
                </Button>
              </div>
            </div>
          ) : null}

          <div className="space-y-4">
            {templates.map((t) => (
              <TemplateCard
                key={t.id}
                template={t}
                onDelete={deleteTemplate}
                onClone={cloneTemplate}
              />
            ))}
          </div>
        </>
      ) : (
        <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card overflow-hidden">
          <div className="px-6 py-5 border-b border-ui-border dark:border-ui-dark-border bg-ui-card/50 dark:bg-ui-dark-card/50">
            <h3 className="text-base font-semibold text-text-primary dark:text-text-dark-primary">
              Nueva plantilla normativa
            </h3>
            <p className="text-xs text-text-muted dark:text-text-dark-muted mt-1">
              Configura los metadatos básicos. En el siguiente paso podrás diseñar los bloques de contenido.
            </p>
          </div>
          
          <div className="p-6 space-y-6">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <div className="space-y-4">
                <div>
                  <FieldLabel>Nombre de la plantilla</FieldLabel>
                  <TextInput
                    type="text"
                    fieldSize="comfortable"
                    value={newName}
                    onChange={(e) => setNewName(e.target.value)}
                    placeholder="Ej. Acta de Evaluación Final"
                  />
                </div>
                <div>
                  <FieldLabel>Visibilidad</FieldLabel>
                  <Select
                    fieldSize="comfortable"
                    value={newVisibility}
                    onChange={(e) => setNewVisibility(e.target.value as TemplateVisibilityLevel)}
                  >
                    {VISIBILITY_OPTIONS.map((o) => (
                      <option key={o.value} value={o.value}>
                        {o.label}
                      </option>
                    ))}
                  </Select>
                </div>
                <div>
                  <FieldLabel>Descripción corta</FieldLabel>
                  <TextInput
                    type="text"
                    fieldSize="comfortable"
                    value={newDesc}
                    onChange={(e) => setNewDesc(e.target.value)}
                    placeholder="Propósito de la plantilla..."
                  />
                </div>
                <div>
                  <FieldLabel>Plazo de entrega (opcional)</FieldLabel>
                  <TextInput
                    type="datetime-local"
                    fieldSize="comfortable"
                    value={newDeadline}
                    onChange={(e) => setNewDeadline(e.target.value)}
                  />
                </div>
              </div>

              <div className="bg-ui-body/30 dark:bg-ui-dark-bg/30 rounded-lg p-5 border border-dashed border-ui-border dark:border-ui-dark-border">
                <h4 className="text-xs font-bold uppercase tracking-wider text-text-secondary dark:text-text-dark-secondary mb-4">
                  Vinculación Académica
                </h4>
                <TemplateHierarchyFields
                  values={{
                    study_type_id: newStudyTypeId,
                    study_id: newStudyId,
                    module_id: newModuleId,
                    group_id: newGroupId,
                  }}
                  onFieldChange={(key, value) => {
                    if (key === 'study_type_id') setNewStudyTypeId(value);
                    else if (key === 'study_id') setNewStudyId(value);
                    else if (key === 'module_id') setNewModuleId(value);
                    else setNewGroupId(value);
                  }}
                />
              </div>
            </div>

            <div className="pt-6 border-t border-ui-border dark:border-ui-dark-border flex items-center gap-3">
              <Button
                type="button"
                variant="primary"
                size="md"
                className="px-8"
                loading={creating}
                disabled={!newName.trim()}
                onClick={() => void handleCreate()}
              >
                Crear y continuar a bloques
              </Button>
              <Button
                type="button"
                variant="outline"
                size="md"
                disabled={creating}
                onClick={() => setViewMode('list')}
              >
                Cancelar
              </Button>
            </div>
          </div>
        </div>
      )}

      {editingTemplate && (
        <TemplateBlockEditor 
          template={editingTemplate} 
          onClose={() => setEditingTemplate(null)} 
        />
      )}
    </div>
  );
}
