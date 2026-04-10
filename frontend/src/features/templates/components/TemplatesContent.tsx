import { useMemo, useState } from 'react';
import type { TemplateVisibilityLevel } from '../../../types/templates';
import { useTemplates } from '../hooks/useTemplates';
import { STATUS_OPTIONS, VISIBILITY_OPTIONS } from '../constants';
import { datetimeLocalToIso } from '../templateFormUtils';
import { Button, FieldLabel, Select, TextInput } from '../../../ui';
import { TemplateCard } from './TemplateCard';
import { TemplateHierarchyFields } from './TemplateHierarchyFields';

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
    updateTemplate,
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

  const hasHierarchyIdFilters = useMemo(
    () =>
      Boolean(
        filterUi.studyTypeId.trim() ||
          filterUi.studyId.trim() ||
          filterUi.moduleId.trim() ||
          filterUi.groupId.trim(),
      ),
    [filterUi],
  );

  const handleCreate = async () => {
    if (!newName.trim()) return;
    setCreating(true);
    try {
      await createTemplate({
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
        </div>
      </div>

      <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card p-5">
        <h3 className="text-xs font-semibold uppercase tracking-wide text-text-secondary dark:text-text-dark-secondary mb-3">
          Nueva plantilla
        </h3>
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
          <div>
            <FieldLabel>Nombre</FieldLabel>
            <TextInput
              type="text"
              fieldSize="comfortable"
              value={newName}
              onChange={(e) => setNewName(e.target.value)}
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
          <div className="lg:col-span-2">
            <FieldLabel>Descripción</FieldLabel>
            <TextInput
              type="text"
              fieldSize="comfortable"
              value={newDesc}
              onChange={(e) => setNewDesc(e.target.value)}
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
          <div className="lg:col-span-2">
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
        <Button
          type="button"
          variant="primary"
          size="md"
          loading={creating}
          disabled={!newName.trim()}
          className="mt-3"
          onClick={() => void handleCreate()}
        >
          {creating ? 'Creando…' : 'Crear plantilla'}
        </Button>
      </div>

      {loading && templates.length === 0 ? (
        <p className="text-sm text-text-muted dark:text-text-dark-muted">Cargando plantillas…</p>
      ) : null}

      {!loading && templates.length === 0 && !listError ? (
        <div className="text-center py-8 space-y-2 max-w-lg mx-auto">
          <p className="text-sm text-text-muted dark:text-text-dark-muted">
            No hay plantillas visibles con los filtros actuales.
          </p>
          {hasHierarchyIdFilters ? (
            <p className="text-xs text-text-muted dark:text-text-dark-muted leading-relaxed">
              Si acabas de crear una plantilla, los filtros por <span className="font-mono">study_type_id</span>,{' '}
              <span className="font-mono">study_id</span>, etc. deben coincidir con la plantilla. Si filtras por un ID
              pero la creaste sin ese campo (p. ej. personal sin jerarquía), no aparecerá: vacía esos campos o repite
              los mismos UUID que al crear.
            </p>
          ) : null}
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
            onUpdate={async (id, payload) => {
              await updateTemplate(id, payload);
            }}
            onDelete={deleteTemplate}
            onClone={cloneTemplate}
          />
        ))}
      </div>
    </div>
  );
}
