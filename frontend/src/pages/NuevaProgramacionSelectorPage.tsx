import { useMemo } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { buildBackState, useBackNavigation } from '@ceedcv-maya/shared-hooks-react';
import { useTranslation } from 'react-i18next';
import { useProcessesQuery } from '../hooks/useProcesses';
import type { Template } from '../types/templates';
import { useFavoritesIds } from '../hooks/useFavoritesIds';
import { FavoriteInlineMark } from '../components/FavoriteInlineMark';
import type { Process } from '../types/processes';
import { formatCalendarDateForBrowser } from '../utils/formatCalendarDate';
import { useHierarchy } from '../features/hierarchy';
import { formatListRowVisibilityCaption, listRowSearchMatches } from '../utils/academicContextSearch';
import {
  DataTable,
  DatePicker,
  FilterField,
  PageTitle,
  Pagination,
  TextInput,
  visibilityBadgeClass,
  type ColumnDef,
} from '@ceedcv-maya/shared-ui-react';
import { useServerNuevaProgramacionTable } from '../hooks/useServerNuevaProgramacionTable';

export function NuevaProgramacionSelectorPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const { t } = useTranslation(['documents', 'common']);
  const { hierarchy } = useHierarchy();
  const locationState = location.state as { moduleId?: string; processId?: string } | null;
  const selectedModuleId = locationState?.moduleId;
  const selectedProcessId = locationState?.processId;
  const { goBack, hasBackState } = useBackNavigation({
    fallback: selectedProcessId ? `/processes/${selectedProcessId}` : '/processes',
  });
  const { templateIds: favoriteTemplateIds } = useFavoritesIds();

  const { rows: allTemplates, meta, loading, error: listError, filters, setFilter, resetFilters,
          filtersActiveCount, onPageChange, pageSize, onPageSizeChange,
          sortBy, onSortChange } = useServerNuevaProgramacionTable({
    processId: selectedProcessId,
  });

  // Catálogo cacheado de procesos (TanStack Query) en lugar de fetch manual.
  const processesQuery = useProcessesQuery(undefined, { enabled: !!selectedProcessId });
  const process = useMemo<Process | null>(() => {
    if (!selectedProcessId) return null;
    return processesQuery.data?.data.find((p) => p.id === selectedProcessId) ?? null;
  }, [selectedProcessId, processesQuery.data]);

  const mappedTemplates = useMemo(() => {
    return allTemplates.map((template) => {
      if (template.status !== 'published' && template.latest_published_version_id) {
        return {
          ...template,
          name: template.latest_published_name ?? template.name,
          status: 'published' as const,
          version: template.latest_published_version_number ?? template.version,
          list_variant: 'published_fallback' as const,
          list_row_id: `${template.id}:published`,
        };
      }
      return {
        ...template,
        list_variant: 'live' as const,
        list_row_id: `${template.id}:live`,
      };
    });
  }, [allTemplates]);

  const afterFavorites = useMemo(() => {
    const favoritesFilterValue = filters.favorite_ids ? 'favorites' : '';
    if (favoritesFilterValue !== 'favorites') return mappedTemplates;
    return mappedTemplates.filter(
      (template) => !!template.latest_published_version_id && favoriteTemplateIds.has(template.latest_published_version_id),
    );
  }, [mappedTemplates, filters.favorite_ids, favoriteTemplateIds]);

  const afterAcademicContext = useMemo(() => {
    const academicContextFilter = filters.search ?? '';
    if (!academicContextFilter.trim()) {
      return afterFavorites;
    }
    return afterFavorites.filter((template) =>
      listRowSearchMatches(
        hierarchy,
        {
          visibility_level: template.visibility_level,
          study_type_id: template.study_type_id,
          study_id: template.study_id,
          module_id: template.module_id,
          team_id: template.team_id,
          team: template.team,
        },
        academicContextFilter,
      ),
    );
  }, [afterFavorites, filters.search, hierarchy]);

  const templates = afterAcademicContext;

  const columns: ColumnDef<Template>[] = useMemo(
    () => [
      {
        id: 'name',
        header: 'Nombre',
        sortable: true,
        alwaysVisible: true,
        cell: (template) => (
          <span className="flex items-center gap-2 min-w-0">
            {!!template.latest_published_version_id && favoriteTemplateIds.has(template.latest_published_version_id) && <FavoriteInlineMark />}
            <span className="truncate font-medium">{template.name}</span>
          </span>
        ),
      },
      {
        id: 'visibility_level',
        header: 'Visibilidad',
        cell: (t) => {
          const caption = formatListRowVisibilityCaption(hierarchy, {
            visibility_level: t.visibility_level,
            study_type_id: t.study_type_id,
            study_id: t.study_id,
            module_id: t.module_id,
            team_id: t.team_id,
            team: t.team,
          });
          return (
            <span
              className={`inline-flex max-w-full min-w-0 text-xs font-medium px-2 py-0.5 rounded-full ${visibilityBadgeClass(t.visibility_level)}`}
              title={caption}
            >
              <span className="truncate">{caption}</span>
            </span>
          );
        },
      },
      {
        id: 'author_name',
        header: 'Autor',
        cell: (template) => (
          <span className="text-xs text-text-secondary dark:text-text-dark-secondary">
            {template.author_name ?? '—'}
          </span>
        ),
      },
      {
        id: 'latest_published_at',
        header: 'Fecha de publicación',
        sortable: true,
        cell: (template) => (
          <span className="text-xs text-text-secondary dark:text-text-dark-secondary">
            {formatCalendarDateForBrowser(template.latest_published_at)}
          </span>
        ),
      },
      {
        id: 'version',
        header: 'Versión',
        cell: (template) => (
          <span className="text-xs text-text-secondary dark:text-text-dark-secondary">v{template.version}</span>
        ),
      },
    ],
    [favoriteTemplateIds, hierarchy],
  );


  return (
    <div className="min-h-full overflow-y-auto p-6 space-y-4">
      <PageTitle
        title={t('documents:newDocument')}
        subtitle={
          process
            ? `Proceso: ${process.code} — ${process.name} · Selecciona una plantilla`
            : 'Selecciona una plantilla'
        }
        onBack={() => {
          if (hasBackState) {
            goBack();
            return;
          }
          navigate(selectedProcessId ? `/processes/${selectedProcessId}` : '/processes', {
            state: { tab: 'documents' },
          });
        }}
        backLabel={t('common:navigation.backToDocuments')}
      />

      {listError && (
        <div className="rounded-lg border border-warning/40 bg-warning-light/40 dark:bg-warning-dark/10 px-4 py-3 text-sm text-warning-dark dark:text-warning-light">
          {listError.message}
        </div>
      )}

      <DataTable
        columns={columns}
        rows={templates}
        loading={loading}
        rowKey={(t) => t.list_row_id ?? t.id}
        sortBy={sortBy}
        onSortChange={onSortChange}
        pageSize={pageSize}
        onPageSizeChange={onPageSizeChange}
        emptyMessage="No hay plantillas utilizables para crear documentos con los filtros actuales."
        filtersActiveCount={filtersActiveCount}
        onClearFilters={resetFilters}
        filtersStorageKey="maya:dms:nueva-programacion-selector"
        onRowClick={(t) => {
          const selectedTemplateVersionId =
            t.list_variant === 'published_fallback'
              ? (t.latest_published_version_id ?? null)
              : null;
          navigate(`/documents/new/${t.id}/wizard`, {
            state: {
              moduleId: selectedModuleId,
              processId: selectedProcessId,
              templateVersionId: selectedTemplateVersionId,
              ...buildBackState(location),
            },
          });
        }}
        filtersPanel={
          <>
            <FilterField label="Nombre">
              <TextInput
                fieldSize="sm"
                placeholder={t('documents:wizard.searchByName')}
                value={filters.search ?? ''}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
                  setFilter('search', e.target.value || undefined)
                }
              />
            </FilterField>
            <FilterField label="Visibilidad">
              <TextInput
                fieldSize="sm"
                type="search"
                placeholder={t('documents:wizard.searchVisibility')}
                value={''}
                onChange={() => {}}
              />
            </FilterField>
            <FilterField label="Autor">
              <TextInput
                fieldSize="sm"
                placeholder={t('documents:wizard.authorPlaceholder')}
                value={filters.author_name ?? ''}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
                  setFilter('author_name', e.target.value || undefined)
                }
              />
            </FilterField>
            <FilterField label="Publicadas desde">
              <DatePicker
                value={filters.published_on || null}
                onChange={(d) => setFilter('published_on', d ?? undefined)}
                placeholder={t('documents:wizard.datePlaceholder')}
                ariaLabel="Filtrar plantillas publicadas desde esta fecha (inclusive)"
              />
            </FilterField>
          </>
        }
      />

      {meta && (
        <Pagination
          currentPage={meta.current_page}
          totalPages={meta.last_page}
          onChange={onPageChange}
          info={`${meta.total} ${meta.total === 1 ? 'plantilla disponible' : 'plantillas disponibles'}`}
        />
      )}
    </div>
  );
}
