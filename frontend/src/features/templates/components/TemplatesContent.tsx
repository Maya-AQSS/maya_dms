import { useMemo, useState } from 'react';
import type { TemplateVisibilityLevel } from '../../../types/templates';
import { useTemplates } from '../hooks/useTemplates';
import { STATUS_OPTIONS, VISIBILITY_OPTIONS } from '../constants';
import { datetimeLocalToIso } from '../templateFormUtils';
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
        <button
          type="button"
          onClick={() => void refetch()}
          disabled={loading}
          className="rounded border border-ui-border dark:border-ui-dark-border px-3 py-1.5 text-xs text-text-secondary dark:text-text-dark-secondary hover:bg-ui-body dark:hover:bg-ui-dark-bg disabled:opacity-50"
        >
          Actualizar
        </button>
      </div>

      {listError && (
        <div className="rounded-lg border border-warning/40 bg-warning-light/40 dark:bg-warning-dark/10 px-4 py-3 text-sm text-warning-dark dark:text-warning-light">
          {listError}
        </div>
      )}

      {actionError && (
        <div className="rounded-lg border border-odoo-purple/30 bg-odoo-purple/5 px-4 py-3 text-sm text-text-primary dark:text-text-dark-primary flex justify-between gap-4">
          <span>{actionError}</span>
          <button type="button" onClick={clearActionError} className="text-xs text-text-link shrink-0">
            Cerrar
          </button>
        </div>
      )}

      {actionInfo && (
        <div className="rounded-lg border border-odoo-teal/30 bg-odoo-teal/5 px-4 py-3 text-sm flex justify-between gap-4">
          <span>{actionInfo}</span>
          <button type="button" onClick={clearActionInfo} className="text-xs text-text-link shrink-0">
            Cerrar
          </button>
        </div>
      )}

      <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card p-4 space-y-3">
        <h3 className="text-xs font-semibold uppercase tracking-wide text-text-secondary dark:text-text-dark-secondary">
          Filtros
        </h3>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <div>
            <label className="block text-xs text-text-muted mb-1">Visibilidad</label>
            <select
              value={filterUi.visibility}
              onChange={(e) =>
                applyFilters({
                  visibility_level: e.target.value || undefined,
                })
              }
              className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-2 py-1.5 text-sm"
            >
              <option value="">Todas</option>
              {VISIBILITY_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-xs text-text-muted mb-1">Estado</label>
            <select
              value={filterUi.status}
              onChange={(e) => applyFilters({ status: e.target.value || undefined })}
              className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-2 py-1.5 text-sm"
            >
              {STATUS_OPTIONS.map((o) => (
                <option key={o.value || 'all'} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
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

      <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card p-4">
        <h3 className="text-xs font-semibold uppercase tracking-wide text-text-secondary dark:text-text-dark-secondary mb-3">
          Nueva plantilla
        </h3>
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
          <div>
            <label className="block text-xs text-text-muted mb-1">Nombre</label>
            <input
              type="text"
              value={newName}
              onChange={(e) => setNewName(e.target.value)}
              className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label className="block text-xs text-text-muted mb-1">Visibilidad</label>
            <select
              value={newVisibility}
              onChange={(e) => setNewVisibility(e.target.value as TemplateVisibilityLevel)}
              className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-3 py-2 text-sm"
            >
              {VISIBILITY_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
          </div>
          <div className="lg:col-span-2">
            <label className="block text-xs text-text-muted mb-1">Descripción</label>
            <input
              type="text"
              value={newDesc}
              onChange={(e) => setNewDesc(e.target.value)}
              className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label className="block text-xs text-text-muted mb-1">Plazo de entrega (opcional)</label>
            <input
              type="datetime-local"
              value={newDeadline}
              onChange={(e) => setNewDeadline(e.target.value)}
              className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-3 py-2 text-sm"
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
        <button
          type="button"
          disabled={creating || !newName.trim()}
          onClick={() => void handleCreate()}
          className="mt-3 rounded bg-odoo-purple px-4 py-2 text-sm font-medium text-white hover:opacity-90 disabled:opacity-50 inline-flex items-center gap-2"
        >
          {creating ? (
            <>
              <span
                className="inline-block size-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white"
                aria-hidden
              />
              Creando…
            </>
          ) : (
            'Crear plantilla'
          )}
        </button>
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
            <button
              type="button"
              disabled={loading || meta.current_page <= 1}
              onClick={() => goToPage(meta.current_page - 1)}
              className="rounded border border-ui-border px-2 py-1 disabled:opacity-40"
            >
              Anterior
            </button>
            <button
              type="button"
              disabled={loading || meta.current_page >= meta.last_page}
              onClick={() => goToPage(meta.current_page + 1)}
              className="rounded border border-ui-border px-2 py-1 disabled:opacity-40"
            >
              Siguiente
            </button>
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
