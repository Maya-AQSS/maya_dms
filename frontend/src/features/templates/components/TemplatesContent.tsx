import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  Button,
  DataTable,
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
} from '@ceedcv-maya/shared-ui-react';
import { useServerTemplatesTable } from '../hooks/useServerTemplatesTable';
import { STATUS_OPTIONS } from '../constants';
import { TemplateCard } from './TemplateCard';
import { TemplateHierarchyFields } from './TemplateHierarchyFields';
import { useUserProfile } from '../../../features/user-profile';
import { useHierarchy } from '../../../features/hierarchy';
import { formatListRowVisibilityCaption } from '../../../utils/academicContextSearch';
import type { Template, TemplateStatus } from '../../../types/templates';
import { useFavoritesIds } from '../../../hooks/useFavoritesIds';
import { FavoriteInlineMark } from '../../../components/FavoriteInlineMark';
import { formatCalendarDateForBrowser } from '../../../utils/formatCalendarDate';

const STORAGE_KEY = 'maya:dms:templates-content';

/** Etiqueta i18n del estado (mismas keys que TemplatesTable; fallback al slug). */
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

/**
 * Gestión de plantillas normativas (página principal). Listado server-side vía
 * useServerTemplatesTable (filtros/paginación/sort en backend), tarjetas y mutaciones.
 */
export function TemplatesContent() {
  const navigate = useNavigate();
  const { t } = useTranslation(['templates', 'common']);
  const { profile } = useUserProfile();
  const { hierarchy } = useHierarchy();
  const { hiddenIds, toggleHidden } = useTablePreferences({ storageKey: STORAGE_KEY });
  const { templateIds: favoriteTemplateIds } = useFavoritesIds();

  const {
    rows,
    meta,
    loading,
    listError,
    actionError,
    actionInfo,
    clearActionError,
    clearActionInfo,
    refetch,
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
    deleteTemplate,
    cloneTemplate,
  } = useServerTemplatesTable(undefined, STORAGE_KEY);

  // Búsqueda con debounce → param server-side `search` (nombre de plantilla o autor).
  const [searchInput, setSearchInput] = useState(filters.search ?? '');
  const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
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

  const handleRowClick = (tpl: Template) => {
    if (tpl.list_variant === 'published_fallback' && tpl.latest_published_version_id) {
      navigate(`/templates/${tpl.id}?templateVersionId=${encodeURIComponent(tpl.latest_published_version_id)}`);
      return;
    }
    const isReviewer = tpl.status === 'in_review' && tpl.reviewers?.some((r) => r.user_id === profile?.id);
    navigate(isReviewer ? `/templates/${tpl.id}/review` : `/templates/${tpl.id}`);
  };

  const columns: ColumnDef<Template>[] = useMemo(
    () => [
      {
        id: 'name',
        header: 'Nombre',
        sortable: true,
        alwaysVisible: true,
        cell: (template) => {
          const versionId = template.latest_published_version_id;
          return (
            <span className="flex items-center gap-2 min-w-0">
              {versionId && favoriteTemplateIds.has(versionId) ? <FavoriteInlineMark /> : null}
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
        header: 'Visibilidad',
        cell: (template) => {
          const caption = formatListRowVisibilityCaption(hierarchy, {
            visibility_level: template.visibility_level,
            study_type_id: template.study_type_id,
            study_id: template.study_id,
            module_id: template.module_id,
            team_id: template.team_id,
            team: template.team,
          });
          return (
            <span
              className={`inline-flex max-w-full min-w-0 text-xs font-medium px-2 py-0.5 rounded-full ${visibilityBadgeClass(template.visibility_level)}`}
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
        id: 'status',
        header: 'Estado',
        cell: (template) => {
          const status = template.status as TemplateStatus;
          return (
            <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${statusBadgeClass(status)}`}>
              {templateStatusLabel(status, t)}
            </span>
          );
        },
      },
      {
        id: 'delivery_deadline',
        header: 'Fecha de validación',
        sortable: true,
        cell: (template) => (
          <span className="text-xs text-text-secondary dark:text-text-dark-secondary">
            {template.status === 'published' ? '—' : formatCalendarDateForBrowser(template.delivery_deadline)}
          </span>
        ),
      },
    ],
    [profile, favoriteTemplateIds, hierarchy, t],
  );

  return (
    <div className="p-6 space-y-6">
      <PageTitle
        title={t('templates:pageTitle')}
        subtitle={t('templates:pageSubtitle')}
        actions={
          <>
            <Button type="button" variant="outline" size="sm" onClick={() => refetch()} disabled={loading}>
              Actualizar
            </Button>
            <Button type="button" variant="primary" size="sm" onClick={() => navigate('/templates/new')}>
              Nueva Plantilla
            </Button>
          </>
        }
      />

      {listError && (
        <div className="rounded-lg border border-warning/40 bg-warning-light/40 dark:bg-warning-dark/10 px-4 py-3 text-sm text-warning-dark dark:text-warning-light">
          {t('templates:table.loadError', { message: listError.message })}
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
        rows={displayTemplates}
        loading={loading && rows.length === 0}
        rowKey={(tpl) => tpl.list_row_id ?? tpl.id}
        hiddenColumnIds={hiddenIds}
        onToggleHiddenColumn={toggleHidden}
        sortBy={sortBy}
        onSortChange={onSortChange}
        pageSize={pageSize}
        onPageSizeChange={onPageSizeChange}
        defaultView="cards"
        emptyMessage="No hay plantillas visibles con los filtros actuales."
        onRowClick={handleRowClick}
        cardRender={(tpl) => (
          <TemplateCard template={tpl} onDelete={deleteTemplate} onClone={cloneTemplate} />
        )}
        filtersActiveCount={filtersActiveCount}
        onClearFilters={clearFilters}
        filtersStorageKey={STORAGE_KEY}
        filtersPanel={
          <>
            <FilterField label="Buscar">
              <TextInput
                fieldSize="sm"
                type="search"
                placeholder={t('templates:table.searchName')}
                value={searchInput}
                onChange={handleSearchChange}
              />
            </FilterField>
            <FilterField label="Estado">
              <Select
                fieldSize="sm"
                value={filters.status ?? ''}
                onChange={(e) => setFilter('status', e.target.value || undefined)}
              >
                {STATUS_OPTIONS.map((o) => (
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
                onChange={(e) => setFilter('favorites', e.target.value || undefined)}
              >
                <option value="">{t('templates:table.favoritesFilter.all')}</option>
                <option value="favorites">{t('templates:table.favoritesFilter.onlyFavorites')}</option>
              </Select>
            </FilterField>
            <div className="col-span-full pt-2 border-t border-ui-border/50 dark:border-ui-dark-border/50">
              <FieldLabel>{t('fields.linking')}</FieldLabel>
              <TemplateHierarchyFields
                values={{
                  study_type_id: filters.study_type_id ?? '',
                  study_id: filters.study_id ?? '',
                  module_id: filters.module_id ?? '',
                  team_id: filters.team_id ?? '',
                }}
                onFieldChange={(key, value) => setFilter(key, value.trim() === '' ? undefined : value.trim())}
                gridClassName="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3"
                filterMode={true}
                maxLevel={undefined}
                showTeam={true}
              />
            </div>
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
              ? `${(meta.current_page - 1) * meta.per_page + 1}–${Math.min(meta.current_page * meta.per_page, meta.total)} de ${meta.total} plantillas`
              : undefined
          }
        />
      )}
    </div>
  );
}
