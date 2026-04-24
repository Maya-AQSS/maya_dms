import { useMemo } from 'react';
import { useTemplates } from '../hooks/useTemplates';
import { STATUS_OPTIONS, VISIBILITY_OPTIONS } from '../constants';
import { Button, FieldLabel, Select, TextInput } from '../../../ui';
import { TemplateCard } from './TemplateCard';
import { TemplateHierarchyFields } from './TemplateHierarchyFields';
import { useNavigate } from 'react-router-dom';

const HIERARCHY_VIS = new Set(['study_type', 'study', 'module']);


/**
 * Gestión de plantillas normativas: datos vía {@link useTemplates}.
 */
export function TemplatesContent() {
  const navigate = useNavigate();
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
    deleteTemplate,
    cloneTemplate,
  } = useTemplates();

  const filterUi = useMemo(
    () => ({
      visibility: filters.visibility_level ?? '',
      status: filters.status ?? '',
      studyTypeId: filters.study_type_id ?? '',
      studyId: filters.study_id ?? '',
      moduleId: filters.module_id ?? '',
      teamId: filters.team_id ?? '',
      authorName: filters.author_name ?? '',
      deliveryDeadline: filters.delivery_deadline ?? '',
    }),
    [filters],
  );

  const clearFilters = () => {
    applyFilters({
      visibility_level: undefined,
      status: undefined,
      study_type_id: undefined,
      study_id: undefined,
      module_id: undefined,
      team_id: undefined,
      author_name: undefined,
      delivery_deadline: undefined,
    });
  };

  const showTeamFilter = filterUi.visibility === 'team';
  const showHierarchyFilter = HIERARCHY_VIS.has(filterUi.visibility);
  const showConditionalFilters = showTeamFilter || showHierarchyFilter;

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
        <div className="flex items-center gap-3">
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
            onClick={() => navigate('/templates/new')}
          >
            Nueva Plantilla
          </Button>
        </div>

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
        <div className="flex items-center justify-between">
          <h3 className="text-xs font-semibold uppercase tracking-wide text-text-secondary dark:text-text-dark-secondary">
            Filtros
          </h3>
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={clearFilters}
          >
            Limpiar filtros
          </Button>
        </div>

        {showConditionalFilters ? (
          <div className="space-y-3">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
              <div className="space-y-3">
                <div>
                  <FieldLabel>Visibilidad</FieldLabel>
                  <Select
                    fieldSize="sm"
                    value={filterUi.visibility}
                    onChange={(e) =>
                      applyFilters({
                        visibility_level: e.target.value || undefined,
                        study_type_id: undefined,
                        study_id: undefined,
                        module_id: undefined,
                        team_id: undefined,
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
              </div>
              <TemplateHierarchyFields
                values={{
                  study_type_id: filterUi.studyTypeId,
                  study_id: filterUi.studyId,
                  module_id: filterUi.moduleId,
                  team_id: filterUi.teamId,
                }}
                onFieldChange={(key, value) =>
                  applyFilters({ [key]: value.trim() === '' ? undefined : value.trim() })
                }
                gridClassName="grid grid-cols-1 sm:grid-cols-2 gap-3"
                filterMode={true}
                maxLevel={showHierarchyFilter ? (filterUi.visibility as 'study_type' | 'study' | 'module') : null}
                showTeam={showTeamFilter}
              />
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 border-t border-ui-border/50 dark:border-ui-dark-border/50">
              <div>
                <FieldLabel>Autor</FieldLabel>
                <TextInput
                  fieldSize="sm"
                  placeholder="Buscar por autor..."
                  value={filterUi.authorName}
                  onChange={(e) => applyFilters({ author_name: e.target.value || undefined })}
                />
              </div>
              <div>
                <FieldLabel>Fecha límite</FieldLabel>
                <TextInput
                  fieldSize="sm"
                  type="date"
                  value={filterUi.deliveryDeadline}
                  onChange={(e) => applyFilters({ delivery_deadline: e.target.value || undefined })}
                />
              </div>
            </div>
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
              <FieldLabel>Visibilidad</FieldLabel>
              <Select
                fieldSize="sm"
                value={filterUi.visibility}
                onChange={(e) =>
                  applyFilters({
                    visibility_level: e.target.value || undefined,
                    study_type_id: undefined,
                    study_id: undefined,
                    module_id: undefined,
                    team_id: undefined,
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
            <div>
              <FieldLabel>Autor</FieldLabel>
              <TextInput
                fieldSize="sm"
                placeholder="Nombre del autor..."
                value={filterUi.authorName}
                onChange={(e) => applyFilters({ author_name: e.target.value || undefined })}
              />
            </div>
            <div>
              <FieldLabel>Fecha límite</FieldLabel>
              <TextInput
                fieldSize="sm"
                type="date"
                value={filterUi.deliveryDeadline}
                onChange={(e) => applyFilters({ delivery_deadline: e.target.value || undefined })}
              />
            </div>
          </div>
        )}
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
    </div>

  );
}
