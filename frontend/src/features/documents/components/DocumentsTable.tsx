import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useServerDocumentsTable } from '../hooks/useServerDocumentsTable';
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
import type { Document, DocumentStatus } from '../../../types/documents';
import type { TemplateVisibilityLevel } from '../../../types/templates';
import { useFavoritesIds } from '../../../hooks/useFavoritesIds';
import { FavoriteInlineMark } from '../../../components/FavoriteInlineMark';
import { useUserProfile } from '../../../features/user-profile';
import { DMS_PERMISSIONS } from '../../../permissions';
import { useHierarchy } from '../../../features/hierarchy';
import { formatCalendarDateForBrowser } from '../../../utils/formatCalendarDate';
import { formatListRowVisibilityCaption } from '../../../utils/academicContextSearch';

function documentStatusLabel(
  status: string | null | undefined,
  t: (key: string, options?: Record<string, unknown>) => string,
): string {
  if (!status) {
    return t('documents:table.notAvailable');
  }
  const label = t(`documents:table.status.${status as DocumentStatus}`, { defaultValue: '' });
  return label || status;
}

type Props = {
  /** Filtra el listado por proceso. No se expone en el panel de filtros. */
  processId?: string;
};

export function DocumentsTable({ processId }: Props = {}) {
  const navigate = useNavigate();
  const { t } = useTranslation(['documents', 'common']);
  const { profile, hasPermission } = useUserProfile();
  const canShow = hasPermission(DMS_PERMISSIONS.documentShow);
  const { hierarchy } = useHierarchy();
  const { documentIds: favoriteDocumentIds } = useFavoritesIds();

  // useTablePreferences se usa SOLO para visibilidad de columnas; sort y per_page
  // los gestiona useServerTable (server-side, en URL/localStorage).
  const { hiddenIds, toggleHidden } = useTablePreferences({
    storageKey: 'maya:dms:documents-table',
  });

  const {
    rows,
    meta,
    loading,
    error,
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
  } = useServerDocumentsTable(processId);

  // Búsqueda con debounce → param server-side `search` (título del documento).
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

  const statusFilterOptions = useMemo(
    () => [
      { value: '', label: t('documents:table.statusFilter.all') },
      { value: 'draft', label: t('documents:table.status.draft') },
      { value: 'in_review', label: t('documents:table.status.in_review') },
      { value: 'published', label: t('documents:table.status.published') },
      { value: 'rejected', label: t('documents:table.status.rejected') },
    ],
    [t],
  );

  // Expansión de filas: por cada documento, fila "live" y/o "published_fallback".
  const displayDocuments = useMemo(() => {
    const out: Document[] = [];
    for (const d of rows) {
      const hasPublishedFallback = d.status !== 'published' && !!d.latest_published_version_id;
      const isAssignedReviewer = d.status === 'in_review' && d.is_assigned_reviewer === true;
      const canSeeLive =
        (profile?.id != null && (profile.id === d.created_by || profile.id === d.owner_id)) ||
        d.share_permission === 'edit' ||
        isAssignedReviewer;

      if (!hasPublishedFallback) {
        out.push({ ...d, list_variant: 'live', list_row_id: `${d.id}:live` });
        continue;
      }

      const publishedFallback: Document = {
        ...d,
        title: d.latest_published_title ?? d.title,
        status: 'published',
        current_version: d.latest_published_version_number ?? d.current_version,
        list_variant: 'published_fallback',
        list_row_id: `${d.id}:published`,
      };

      if (canSeeLive) {
        out.push({ ...d, list_variant: 'live', list_row_id: `${d.id}:live` });
      }
      out.push(publishedFallback);
    }
    return out;
  }, [rows, profile?.id]);

  const columns: ColumnDef<Document>[] = useMemo(
    () => [
      {
        id: 'title',
        header: t('documents:table.columns.name'),
        alwaysVisible: true,
        sortable: true,
        cell: (doc) => (
          <span className="flex items-center gap-2 min-w-0">
            {favoriteDocumentIds.has(doc.id) && <FavoriteInlineMark />}
            <span className="font-medium truncate">{doc.title}</span>
            {doc.has_review_comments && doc.status === 'draft' && profile && (doc.owner_id === profile.id || doc.created_by === profile.id) && (
              <span
                className="shrink-0 inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-xs font-bold bg-danger/10 text-danger-dark dark:text-danger border border-danger/20"
                title={t('documents:table.rejectedTitle')}
              >
                ⚠ {t('documents:table.reviewBadge')}
              </span>
            )}
          </span>
        ),
      },
      {
        id: 'visibility_level',
        header: t('documents:table.columns.visibility'),
        cell: (doc) => {
          const visLevel = doc.visibility_level;
          if (visLevel == null) {
            return (
              <span className="text-xs text-text-secondary dark:text-text-dark-secondary">
                {t('documents:table.notAvailable')}
              </span>
            );
          }
          const level = visLevel as TemplateVisibilityLevel;
          const caption = formatListRowVisibilityCaption(hierarchy, {
            visibility_level: level,
            study_type_id: doc.study_type_id,
            study_id: doc.study_id,
            module_id: doc.module_id,
            team_id: doc.team_id,
            team: doc.team,
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
        id: 'owner_name',
        header: t('documents:table.columns.author'),
        cell: (doc) => (
          <span className="text-xs text-text-secondary dark:text-text-dark-secondary">
            {doc.owner_name ?? t('documents:table.notAvailable')}
          </span>
        ),
      },
      {
        id: 'status',
        header: t('documents:table.columns.status'),
        sortable: true,
        cell: (doc) => {
          const status = doc.status as DocumentStatus;
          return (
            <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${statusBadgeClass(status)}`}>
              {documentStatusLabel(status, t)}
            </span>
          );
        },
      },
      {
        id: 'delivery_deadline',
        header: t('documents:table.columns.validationDate'),
        sortable: true,
        cell: (doc) => (
          <span className="text-xs text-text-secondary dark:text-text-dark-secondary">
            {doc.status === 'published'
              ? t('documents:table.notAvailable')
              : formatCalendarDateForBrowser(doc.delivery_deadline)}
          </span>
        ),
      },
    ],
    [favoriteDocumentIds, hierarchy, profile, t],
  );

  const canOpenDocument = (doc: Document): boolean => {
    if (canShow) {
      return true;
    }
    if (profile?.id && (doc.created_by === profile.id || doc.owner_id === profile.id)) {
      return true;
    }
    return false;
  };

  if (!canIndex) {
    return (
      <p className="text-sm text-text-secondary dark:text-text-dark-secondary py-4 text-center">
        {t('documents:table.noIndexPermission')}
      </p>
    );
  }

  return (
    <div className="space-y-4">
      {error && (
        <div className="rounded-lg border border-warning/40 bg-warning-light/40 dark:bg-warning-dark/10 px-4 py-3 text-sm text-warning-dark dark:text-warning-light">
          {t('documents:table.loadError', { message: error.message })}
        </div>
      )}

      <DataTable
        columns={columns}
        rows={displayDocuments}
        loading={loading && rows.length === 0}
        rowKey={(doc) => doc.list_row_id ?? doc.id}
        hiddenColumnIds={hiddenIds}
        onToggleHiddenColumn={toggleHidden}
        sortBy={sortBy}
        onSortChange={onSortChange}
        pageSize={pageSize}
        onPageSizeChange={onPageSizeChange}
        filtersLabel={t('documents:table.filtersLabel')}
        columnsLabel={t('documents:table.columnsLabel')}
        clearFiltersLabel={t('documents:table.clearFiltersLabel')}
        pageSizeLabel={t('documents:table.pageSizeLabel')}
        emptyMessage={t('documents:table.emptyFiltered')}
        filtersActiveCount={filtersActiveCount}
        onClearFilters={clearFilters}
        filtersStorageKey="maya:dms:documents-table"
        onRowClick={(doc) => {
          if (!canOpenDocument(doc)) {
            return;
          }
          if (doc.list_variant === 'published_fallback' && doc.latest_published_version_id) {
            navigate(`/documents/${doc.id}?documentVersionId=${encodeURIComponent(doc.latest_published_version_id)}`, {
              state: { backTo: processId ? `/procesos/${processId}` : '/dashboard', processId },
            });
            return;
          }
          const isReviewerForDoc = doc.status === 'in_review' && doc.is_assigned_reviewer === true;
          if (isReviewerForDoc) {
            navigate(`/documents/${doc.id}/validate`, {
              state: { backTo: processId ? `/procesos/${processId}` : '/dashboard', processId },
            });
            return;
          }
          navigate(`/documents/${doc.id}`, {
            state: { backTo: processId ? `/procesos/${processId}` : '/dashboard', processId },
          });
        }}
        filtersPanel={
          <>
            <FilterField label={t('documents:table.filters.name')}>
              <TextInput
                fieldSize="sm"
                type="search"
                placeholder={t('documents:wizard.searchByName')}
                value={searchInput}
                onChange={handleSearchChange}
              />
            </FilterField>
            <FilterField label={t('documents:table.filters.status')}>
              <Select
                fieldSize="sm"
                value={filters.status ?? ''}
                onChange={(e) => setFilter('status', e.target.value || undefined)}
              >
                {statusFilterOptions.map((o) => (
                  <option key={o.value} value={o.value}>{o.label}</option>
                ))}
              </Select>
            </FilterField>
            <FilterField label={t('documents:table.filters.favorites')}>
              <Select
                fieldSize="sm"
                value={filters.favorites ?? ''}
                onChange={(e) => setFilter('favorites', e.target.value || undefined)}
              >
                <option value="">{t('documents:table.favoritesFilter.all')}</option>
                <option value="favorites">{t('documents:table.favoritesFilter.onlyFavorites')}</option>
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
              ? t('documents:table.paginationInfo', {
                  page: meta.current_page,
                  totalPages: meta.last_page,
                  count: meta.total,
                })
              : undefined
          }
        />
      )}
    </div>
  );
}
