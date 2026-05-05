import { useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Button,
  DataTable,
  DatePicker,
  FieldLabel,
  FilterField,
  PageTitle,
  Pagination,
  Select,
  TextInput,
  useTablePreferences,
  statusBadgeClass,
  visibilityBadgeClass,
  type ColumnDef,
} from '@maya/shared-ui-react';
import { useTemplates } from '../hooks/useTemplates';
import { STATUS_OPTIONS, VISIBILITY_OPTIONS, visibilityLabel } from '../constants';
import { TemplateCard } from './TemplateCard';
import { TemplateHierarchyFields } from './TemplateHierarchyFields';
import { useUserProfile } from '../../../features/user-profile';
import type { Template, TemplateStatus, TemplateVisibilityLevel } from '../../../types/templates';
import { useFavoritesIds } from '../../../hooks/useFavoritesIds';
import { FavoriteInlineMark } from '../../../components/FavoriteInlineMark';

const HIERARCHY_VIS = new Set(['study_type', 'study', 'module']);

const STATUS_LABEL: Record<TemplateStatus, string> = {
  draft: 'Borrador',
  in_review: 'En revisión',
  published: 'Publicada',
  archived: 'Archivada',
};

// Estado y visibilidad: clases en `@maya/shared-ui-react/badges`.

function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—';
  return iso.slice(0, 10);
}

/**
 * Gestión de plantillas normativas: datos vía {@link useTemplates}.
 */
export function TemplatesContent() {
  const navigate = useNavigate();
  const { profile } = useUserProfile();
  const { hiddenIds, toggleHidden, sortBy, setSortBy, pageSize, setPageSize } =
    useTablePreferences({ storageKey: 'maya:dms:templates-content' });
  const { templateIds: favoriteTemplateIds } = useFavoritesIds();
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
  } = useTemplates(undefined, sortBy);

  const [authorInput, setAuthorInput] = useState(filters.author_name ?? '');
  const authorDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const handleAuthorChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setAuthorInput(value);
    if (authorDebounceRef.current) clearTimeout(authorDebounceRef.current);
    authorDebounceRef.current = setTimeout(() => {
      applyFilters({ author_name: value || undefined });
    }, 400);
  };

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

  const filtersActiveCount = [
    filterUi.visibility,
    filterUi.status,
    filterUi.studyTypeId,
    filterUi.studyId,
    filterUi.moduleId,
    filterUi.teamId,
    filterUi.authorName,
    filterUi.deliveryDeadline,
  ].filter((v) => v && v !== '').length;

  const clearFilters = () => {
    if (authorDebounceRef.current) clearTimeout(authorDebounceRef.current);
    setAuthorInput('');
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

  const handleRowClick = (t: Template) => {
    const isReviewer =
      t.status === 'in_review' && t.reviewers?.some((r) => r.user_id === profile?.id);
    navigate(isReviewer ? `/templates/${t.id}/review` : `/templates/${t.id}`);
  };

  const columns: ColumnDef<Template>[] = useMemo(
    () => [
      {
        id: 'name',
        header: 'Nombre',
        sortable: true,
        alwaysVisible: true,
        cell: (t) => (
          <span className="flex items-center gap-2 min-w-0">
            {favoriteTemplateIds.has(t.id) && <FavoriteInlineMark />}
            <span className="truncate font-medium">{t.name}</span>
            {t.has_review_comments && t.status === 'draft' && profile && t.created_by === profile.id && (
              <span
                className="shrink-0 inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-xs font-bold bg-danger/10 text-danger-dark dark:text-danger border border-danger/20"
                title="Esta plantilla tiene bloques con comentarios de revisión pendientes."
              >
                ⚠ Revisión
              </span>
            )}
          </span>
        ),
      },
      {
        id: 'visibility_level',
        header: 'Visibilidad',
        cell: (t) => (
          <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${visibilityBadgeClass(t.visibility_level)}`}>
            {visibilityLabel(t.visibility_level)}
          </span>
        ),
      },
      {
        id: 'author_name',
        header: 'Autor',
        cell: (t) => (
          <span className="text-xs text-text-secondary dark:text-text-dark-secondary">
            {t.author_name ?? '—'}
          </span>
        ),
      },
      {
        id: 'status',
        header: 'Estado',
        sortable: true,
        cell: (t) => {
          const status = t.status as TemplateStatus;
          return (
            <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${statusBadgeClass(status)}`}>
              {STATUS_LABEL[status] ?? status}
            </span>
          );
        },
      },
      {
        id: 'delivery_deadline',
        header: 'Fecha límite',
        sortable: true,
        cell: (t) => (
          <span className="text-xs text-text-secondary dark:text-text-dark-secondary">
            {formatDate(t.delivery_deadline)}
          </span>
        ),
      },
    ],
    [profile, favoriteTemplateIds],
  );

  return (
    <div className="p-6 space-y-6">
      <PageTitle
        title="Plantillas"
        subtitle="Listado según tu visibilidad en la API. La visibilidad compartida en alta/edición requiere roles de coordinación."
        actions={
          <>
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
          </>
        }
      />

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

      <DataTable<Template>
        columns={columns}
        rows={templates}
        loading={loading && templates.length === 0}
        rowKey={(t) => t.id}
        hiddenColumnIds={hiddenIds}
        onToggleHiddenColumn={toggleHidden}
        sortBy={sortBy}
        onSortChange={setSortBy}
        pageSize={pageSize}
        onPageSizeChange={(size) => {
          setPageSize(size);
          applyFilters({ per_page: size });
        }}
        defaultView="cards"
        emptyMessage="No hay plantillas visibles con los filtros actuales."
        onRowClick={handleRowClick}
        cardRender={(t) => (
          <TemplateCard template={t} onDelete={deleteTemplate} onClone={cloneTemplate} />
        )}
        filtersActiveCount={filtersActiveCount}
        onClearFilters={clearFilters}
        filtersStorageKey="maya:dms:templates-content"
        filtersPanel={
          <>
            <FilterField label="Visibilidad">
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
            </FilterField>
            <FilterField label="Estado">
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
            </FilterField>
            <FilterField label="Autor">
              <TextInput
                fieldSize="sm"
                placeholder="Nombre del autor..."
                value={authorInput}
                onChange={handleAuthorChange}
              />
            </FilterField>
            <FilterField label="Fecha límite">
              <DatePicker
                value={filterUi.deliveryDeadline || null}
                onChange={(d) => applyFilters({ delivery_deadline: d ?? undefined })}
                placeholder="Seleccionar fecha…"
                ariaLabel="Filtrar por fecha límite"
              />
            </FilterField>
            {(showHierarchyFilter || showTeamFilter) && (
              <div className="col-span-full pt-2 border-t border-ui-border/50 dark:border-ui-dark-border/50">
                <FieldLabel>Vinculación</FieldLabel>
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
                  gridClassName="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3"
                  filterMode={true}
                  maxLevel={showHierarchyFilter ? (filterUi.visibility as 'study_type' | 'study' | 'module') : null}
                  showTeam={showTeamFilter}
                />
              </div>
            )}
          </>
        }
      />

      {meta && (
        <Pagination
          currentPage={meta.current_page}
          totalPages={meta.last_page}
          onChange={goToPage}
          info={
            meta.total > 0
              ? `${(meta.current_page - 1) * meta.per_page + 1}–${Math.min(meta.current_page * meta.per_page, meta.total)} de ${meta.total} plantillas`
              : undefined
          }
        />
      )}
    </div>
  );
}
