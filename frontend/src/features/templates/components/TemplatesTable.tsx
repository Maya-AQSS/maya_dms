import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocation, useNavigate } from 'react-router-dom';
import { buildBackState } from '@ceedcv-maya/shared-hooks-react';
import {
  DataTable,
  FilterField,
  Pagination,
  Select,
  TextInput,
  useTablePreferences,
  statusBadgeClass,
  visibilityBadgeClass,
  type ColumnDef,
} from '@ceedcv-maya/shared-ui-react';
import { useServerTemplatesTable } from '../hooks/useServerTemplatesTable';
import type { Template, TemplateStatus, TemplateVisibilityLevel } from '../../../types/templates';
import { useUserProfile } from '../../../features/user-profile';
import { DMS_PERMISSIONS } from '../../../permissions';
import { useHierarchy } from '../../../features/hierarchy';
import { formatListRowVisibilityCaption } from '../../../utils/academicContextSearch';
import { useFavoritesIds } from '../../../hooks/useFavoritesIds';
import { FavoriteInlineMark } from '../../../components/FavoriteInlineMark';
import { formatCalendarDateForBrowser } from '../../../utils/formatCalendarDate';

function templateStatusLabel(
  status: string | null | undefined,
  t: (key: string, options?: Record<string, unknown>) => string,
): string {
  if (!status) {
    return t('templates:table.notAvailable');
  }
  const label = t(`templates:table.status.${status as TemplateStatus}`, { defaultValue: '' });
  return label || status;
}

type Props = {
  /** Filtra el listado por proceso. No se expone en el panel de filtros. */
  processId?: string;
};

export function TemplatesTable({ processId }: Props = {}) {
  const { t } = useTranslation(['common', 'templates', 'documents']);
  const navigate = useNavigate();
  const location = useLocation();
  const { profile, hasPermission } = useUserProfile();
  const canShow = hasPermission(DMS_PERMISSIONS.templateShow);
  const canReview = hasPermission(DMS_PERMISSIONS.templateReview);
  const { hierarchy } = useHierarchy();

  // useTablePreferences se usa SOLO para visibilidad de columnas; el sort y el
  // per_page los gestiona useServerTable (server-side, en URL/localStorage).
  const { hiddenIds, toggleHidden } = useTablePreferences({
    storageKey: 'maya:dms:templates-table',
  });
  const { templateIds: favoriteTemplateIds } = useFavoritesIds();

  const {
    rows,
    meta,
    loading,
    listError,
    canIndex,
    filters,
    setFilter,
    resetFilters,
    filtersActiveCount,
    page,
    onPageChange,
    pageSize,
    onPageSizeChange,
    sortBy,
    onSortChange,
  } = useServerTemplatesTable(processId);

  // Búsqueda con debounce → param server-side `search` (nombre de plantilla o autor).
  const [searchInput, setSearchInput] = useState(filters.search ?? '');
  const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  // Sincroniza el input cuando la URL cambia desde fuera (back/forward, limpiar).
  useEffect(() => {
    setSearchInput(filters.search ?? '');
  }, [filters.search]);

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setSearchInput(value);
    if (searchDebounceRef.current) clearTimeout(searchDebounceRef.current);
    searchDebounceRef.current = setTimeout(() => {
      setFilter('search', value || undefined);
    }, 400);
  };

  const clearFilters = () => {
    if (searchDebounceRef.current) clearTimeout(searchDebounceRef.current);
    setSearchInput('');
    resetFilters();
  };

  // Si la página actual queda fuera de rango (p. ej. tras filtrar), corrige a la última.
  useEffect(() => {
    if (meta && meta.last_page >= 1 && page > meta.last_page) {
      onPageChange(meta.last_page);
    }
  }, [meta, page, onPageChange]);

  // Expansión de filas: por cada plantilla, fila "live" y/o "published_fallback".
  const displayTemplates = useMemo(() => {
    const includePublishedFallbackRow = !filters.status || filters.status === 'published';

    const out: Template[] = [];
    for (const tpl of rows) {
      const hasPublishedFallback = tpl.status !== 'published' && !!tpl.latest_published_version_id;
      const isAssignedReviewer =
        tpl.status === 'in_review' &&
        !!profile?.id &&
        (tpl.reviewers?.some((r) => r.user_id === profile.id) ?? false);
      const canSeeLive = (!!profile?.id && tpl.created_by === profile.id) || isAssignedReviewer;

      if (!hasPublishedFallback) {
        out.push({ ...tpl, list_variant: 'live', list_row_id: `${tpl.id}:live` });
        continue;
      }

      const publishedFallback: Template = {
        ...tpl,
        name: tpl.latest_published_name ?? tpl.name,
        status: 'published',
        version: tpl.latest_published_version_number ?? tpl.version,
        list_variant: 'published_fallback',
        list_row_id: `${tpl.id}:published`,
      };

      if (canSeeLive) {
        out.push({ ...tpl, list_variant: 'live', list_row_id: `${tpl.id}:live` });
      }
      if (includePublishedFallbackRow) {
        out.push(publishedFallback);
      }
    }
    return out;
  }, [rows, profile?.id, filters.status]);

  const statusOptions = useMemo(
    () => [
      { value: '', label: t('templates:table.statusFilter.all') },
      { value: 'draft', label: t('templates:table.status.draft') },
      { value: 'in_review', label: t('templates:table.status.in_review') },
      { value: 'rejected', label: t('templates:table.status.rejected') },
      { value: 'published', label: t('templates:table.status.published') },
      { value: 'archived', label: t('templates:table.status.archived') },
    ],
    [t],
  );

  const canOpenTemplate = (tpl: Template): boolean => {
    if (canShow) {
      return true;
    }
    if (profile?.id && tpl.created_by === profile.id) {
      return true;
    }
    const isAssignedReviewer = tpl.reviewers?.some((r) => r.user_id === profile?.id) === true;
    if (isAssignedReviewer && (tpl.status === 'in_review' || tpl.status === 'rejected')) {
      return true;
    }
    return false;
  };

  const handleRowClick = (tpl: Template) => {
    if (!canOpenTemplate(tpl)) {
      return;
    }

    const backState = { ...buildBackState(location), processId };
    if (tpl.list_variant === 'published_fallback' && tpl.latest_published_version_id) {
      navigate(`/templates/${tpl.id}?templateVersionId=${encodeURIComponent(tpl.latest_published_version_id)}`, {
        state: backState,
      });
      return;
    }
    const isAssignedReviewer = tpl.reviewers?.some((r) => r.user_id === profile?.id) === true;
    const openReviewView = tpl.status === 'in_review' && isAssignedReviewer && canReview;
    if (openReviewView) {
      navigate(`/templates/${tpl.id}/review`, { state: backState });
      return;
    }
    navigate(`/templates/${tpl.id}`, { state: backState });
  };

  const columns: ColumnDef<Template>[] = useMemo(
    () => [
      {
        id: 'name',
        header: t('templates:table.columns.name'),
        sortable: true,
        alwaysVisible: true,
        cell: (template) => {
          const isFavorite =
            template.latest_published_version_id && favoriteTemplateIds.has(template.latest_published_version_id);
          return (
            <span className="flex items-center gap-2 min-w-0">
              {isFavorite ? <FavoriteInlineMark /> : null}
              <span className="truncate font-medium">{template.name}</span>
              {template.has_unread_review_comments && (template.status === 'draft' || template.status === 'rejected') && profile && template.created_by === profile.id && (
                <span
                  className="shrink-0 inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-xs font-bold bg-danger/10 text-danger-dark dark:text-danger border border-danger/20"
                  title={t('templates:pendingReviewTitle')}
                >
                  ⚠ {t('templates:table.reviewBadge')}
                </span>
              )}
            </span>
          );
        },
      },
      {
        id: 'visibility_level',
        header: t('templates:table.columns.visibility'),
        cell: (template) => {
          const level = template.visibility_level as TemplateVisibilityLevel;
          const caption = formatListRowVisibilityCaption(hierarchy, {
            visibility_level: level,
            study_type_id: template.study_type_id,
            study_id: template.study_id,
            module_id: template.module_id,
            team_id: template.team_id,
            team: template.team,
          });
          return (
            <span
              className={`inline-flex max-w-full min-w-0 text-xs font-medium px-2 py-0.5 rounded-full ${visibilityBadgeClass(level)}`}
              title={caption}
            >
              <span className="truncate">{caption}</span>
            </span>
          );
        },
      },
      {
        id: 'author_name',
        header: t('templates:table.columns.author'),
        cell: (template) => (
          <span className="text-xs text-text-secondary dark:text-text-dark-secondary">
            {template.author_name ?? t('templates:table.notAvailable')}
          </span>
        ),
      },
      {
        id: 'status',
        header: t('templates:table.columns.status'),
        cell: (template) => {
          const status = template.status ?? '';
          return (
            <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${statusBadgeClass(status)}`}>
              {templateStatusLabel(status, t)}
            </span>
          );
        },
      },
      {
        id: 'delivery_deadline',
        header: t('templates:table.columns.validationDate'),
        sortable: true,
        cell: (template) => (
          <span className="text-xs text-text-secondary dark:text-text-dark-secondary">
            {template.status === 'published'
              ? t('templates:table.notAvailable')
              : formatCalendarDateForBrowser(template.delivery_deadline)}
          </span>
        ),
      },
    ],
    [profile, favoriteTemplateIds, hierarchy, t],
  );

  if (!canIndex) {
    return (
      <p className="text-sm text-text-secondary dark:text-text-dark-secondary py-4 text-center">
        {t('templates.noIndexPermission')}
      </p>
    );
  }

  return (
    <div className="space-y-4">
      {listError && (
        <div className="rounded-lg border border-warning/40 bg-warning-light/40 dark:bg-warning-dark/10 px-4 py-3 text-sm text-warning-dark dark:text-warning-light">
          {t('templates:table.loadError', { message: listError.message })}
        </div>
      )}

      <DataTable<Template>
        columns={columns}
        rows={displayTemplates}
        loading={loading && rows.length === 0}
        rowKey={(tpl) => tpl.list_row_id ?? tpl.id}
        hiddenColumnIds={hiddenIds}
        onToggleHiddenColumn={toggleHidden}
        sortBy={sortBy}
        onSortChange={onSortChange}
        pageSize={pageSize}
        onPageSizeChange={onPageSizeChange}
        filtersLabel={t('templates:table.filtersLabel')}
        columnsLabel={t('templates:table.columnsLabel')}
        clearFiltersLabel={t('templates:table.clearFiltersLabel')}
        pageSizeLabel={t('templates:table.pageSizeLabel')}
        emptyMessage={t('templates:table.emptyFiltered')}
        onRowClick={handleRowClick}
        rowClassName={(tpl) => (canOpenTemplate(tpl) ? '' : 'opacity-60 cursor-not-allowed')}
        filtersActiveCount={filtersActiveCount}
        onClearFilters={clearFilters}
        filtersStorageKey="maya:dms:templates-table"
        filtersPanel={
          <>
            <FilterField label={t('templates:table.filters.name')}>
              <TextInput
                fieldSize="sm"
                type="search"
                placeholder={t('templates:table.searchName')}
                value={searchInput}
                onChange={handleSearchChange}
              />
            </FilterField>
            <FilterField label={t('templates:table.filters.status')}>
              <Select
                fieldSize="sm"
                value={filters.status ?? ''}
                onChange={(e: React.ChangeEvent<HTMLSelectElement>) =>
                  setFilter('status', e.target.value || undefined)
                }
              >
                {statusOptions.map((o) => (
                  <option key={o.value || 'all'} value={o.value}>
                    {o.label}
                  </option>
                ))}
              </Select>
            </FilterField>
            <FilterField label={t('templates:table.filters.favorites')}>
              <Select
                fieldSize="sm"
                value={filters.favorites ?? ''}
                onChange={(e: React.ChangeEvent<HTMLSelectElement>) =>
                  setFilter('favorites', e.target.value || undefined)
                }
              >
                <option value="">{t('templates:table.favoritesFilter.all')}</option>
                <option value="favorites">{t('templates:table.favoritesFilter.onlyFavorites')}</option>
              </Select>
            </FilterField>
          </>
        }
      />

      {meta && (
        <Pagination
          currentPage={meta.current_page}
          totalPages={meta.last_page}
          onChange={onPageChange}
          info={
            meta.total > 0
              ? t('templates:table.paginationInfo', {
                  from: (meta.current_page - 1) * meta.per_page + 1,
                  to: Math.min(meta.current_page * meta.per_page, meta.total),
                  total: meta.total,
                })
              : undefined
          }
        />
      )}
    </div>
  );
}
