import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import {
  Button,
  DataTable,
  DatePicker,
  FilterField,
  Pagination,
  Select,
  TextInput,
  useTablePreferences,
  statusBadgeClass,
  visibilityBadgeClass,
  type ColumnDef,
} from '@ceedcv-maya/shared-ui-react';
import { useTemplates } from '../hooks/useTemplates';
import { buildTemplatesListMeta, sliceTemplatesPage } from '../clientTemplatePagination';
import type { Template, TemplateStatus, TemplateVisibilityLevel } from '../../../types/templates';
import { useUserProfile } from '../../../features/user-profile';
import { DMS_PERMISSIONS } from '../../../permissions';
import { useHierarchy } from '../../../features/hierarchy';
import { formatListRowVisibilityCaption, listRowSearchMatches } from '../../../utils/academicContextSearch';
import { useFavoritesIds } from '../../../hooks/useFavoritesIds';
import { FavoriteInlineMark } from '../../../components/FavoriteInlineMark';
import { formatCalendarDateForBrowser } from '../../../utils/formatCalendarDate';
import { normalizeForSearch } from '../../../utils/normalizeForSearch';
import { shouldOpenTemplateEditorFromList } from '../templateListNavigation';

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

// Estado y visibilidad: clases en `@ceedcv-maya/shared-ui-react/badges`.

type Props = {
  /** Filtra el listado por proceso. No se expone en el panel de filtros. */
  processId?: string;
};

export function TemplatesTable({ processId }: Props = {}) {
  const { t } = useTranslation(['common', 'templates', 'documents']);
  const navigate = useNavigate();
  const { profile, hasPermission } = useUserProfile();
  const canIndex = hasPermission(DMS_PERMISSIONS.templateIndex);
  const canShow = hasPermission(DMS_PERMISSIONS.templateShow);
  const canReview = hasPermission(DMS_PERMISSIONS.templateReview);
  const { hierarchy } = useHierarchy();

  const { hiddenIds, toggleHidden, sortBy, setSortBy, pageSize, setPageSize } = useTablePreferences({
    storageKey: 'maya:dms:templates-table',
  });
  const { templateIds: favoriteTemplateIds } = useFavoritesIds();
  const {
    catalogSorted,
    filters,
    loading,
    listError,
    actionError,
    clearActionError,
    applyFilters,
    goToPage,
  } = useTemplates(processId, sortBy);

  const [nameInput, setNameInput] = useState('');
  const [nameFilter, setNameFilter] = useState('');
  const nameDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const [favoritesFilter, setFavoritesFilter] = useState('');

  const [authorInput, setAuthorInput] = useState(filters.author_name ?? '');
  const authorDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const [academicContextInput, setAcademicContextInput] = useState('');
  const [academicContextFilter, setAcademicContextFilter] = useState('');
  const academicContextDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const listPage = filters.page ?? 1;
  const listPerPage = filters.per_page ?? pageSize;

  const clientFilteredCatalog = useMemo(() => {
    let list = catalogSorted;
    if (favoritesFilter === 'favorites') {
      list = list.filter(
        (t) =>
          (!!t.working_version_id && favoriteTemplateIds.has(t.working_version_id)) ||
          (!!t.latest_published_version_id && favoriteTemplateIds.has(t.latest_published_version_id)),
      );
    }
    if (nameFilter.trim()) {
      const needle = normalizeForSearch(nameFilter.trim());
      list = list.filter((t) => normalizeForSearch(t.name ?? '').includes(needle));
    }
    if (academicContextFilter.trim()) {
      list = list.filter((t) =>
        listRowSearchMatches(
          hierarchy,
          {
            visibility_level: t.visibility_level,
            study_type_id: t.study_type_id,
            study_id: t.study_id,
            module_id: t.module_id,
            team_id: t.team_id,
            team: t.team,
          },
          academicContextFilter,
        ),
      );
    }
    return list;
  }, [catalogSorted, favoritesFilter, nameFilter, academicContextFilter, favoriteTemplateIds, hierarchy]);

  useEffect(() => {
    const last = Math.max(1, Math.ceil(clientFilteredCatalog.length / Math.max(1, listPerPage)));
    if (listPage > last) {
      applyFilters({ page: last });
    }
  }, [clientFilteredCatalog.length, listPage, listPerPage, applyFilters]);

  const pagedCatalog = useMemo(
    () => sliceTemplatesPage(clientFilteredCatalog, listPage, listPerPage),
    [clientFilteredCatalog, listPage, listPerPage],
  );

  const meta = useMemo(
    () => buildTemplatesListMeta(clientFilteredCatalog.length, listPage, listPerPage),
    [clientFilteredCatalog.length, listPage, listPerPage],
  );

  const displayTemplates = useMemo(() => {
    /** Con filtro de estado ≠ publicada, no mostrar la fila sintética de última publicada (siempre `published`). */
    const includePublishedFallbackRow = !filters.status || filters.status === 'published';

    const out: Template[] = [];
    for (const t of pagedCatalog) {
      const hasPublishedFallback =
        t.status !== 'published' &&
        !!t.latest_published_version_id;
      const isAssignedReviewer =
        t.status === 'in_review' &&
        !!profile?.id &&
        (t.reviewers?.some((r) => r.user_id === profile.id) ?? false);
      const canSeeLive = (!!profile?.id && t.created_by === profile.id) || isAssignedReviewer;

      if (!hasPublishedFallback) {
        out.push({ ...t, list_variant: 'live', list_row_id: `${t.id}:live` });
        continue;
      }

      const publishedFallback: Template = {
        ...t,
        name: t.latest_published_name ?? t.name,
        status: 'published',
        version: t.latest_published_version_number ?? t.version,
        list_variant: 'published_fallback',
        list_row_id: `${t.id}:published`,
      };

      if (canSeeLive) {
        out.push({ ...t, list_variant: 'live', list_row_id: `${t.id}:live` });
      }
      if (includePublishedFallbackRow) {
        out.push(publishedFallback);
      }
    }
    if (filters.delivery_deadline) {
      return out.filter((row) => row.status !== 'published');
    }
    return out;
  }, [pagedCatalog, profile?.id, filters.delivery_deadline, filters.status]);

  const filterUi = useMemo(
    () => ({
      status: filters.status ?? '',
      deliveryDeadline: filters.delivery_deadline ?? '',
    }),
    [filters],
  );

  const filtersActiveCount =
    (favoritesFilter ? 1 : 0) +
    [nameFilter, academicContextFilter, filterUi.status, filterUi.deliveryDeadline, authorInput].filter((v) => v && v !== '')
      .length;

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

  const favoritesFilterOptions = useMemo(
    () => [
      { value: '', label: t('templates:table.favoritesFilter.all') },
      { value: 'favorites', label: t('templates:table.favoritesFilter.onlyFavorites') },
    ],
    [t],
  );

  const handleNameChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setNameInput(value);
    if (nameDebounceRef.current) clearTimeout(nameDebounceRef.current);
    nameDebounceRef.current = setTimeout(() => {
      setNameFilter(value);
    }, 400);
  };

  const handleAuthorChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setAuthorInput(value);
    if (authorDebounceRef.current) clearTimeout(authorDebounceRef.current);
    authorDebounceRef.current = setTimeout(() => {
      applyFilters({ author_name: value || undefined });
    }, 400);
  };

  const handleAcademicContextChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setAcademicContextInput(value);
    if (academicContextDebounceRef.current) clearTimeout(academicContextDebounceRef.current);
    academicContextDebounceRef.current = setTimeout(() => {
      setAcademicContextFilter(value);
    }, 400);
  };

  const clearFilters = () => {
    if (nameDebounceRef.current) clearTimeout(nameDebounceRef.current);
    if (academicContextDebounceRef.current) clearTimeout(academicContextDebounceRef.current);
    if (authorDebounceRef.current) clearTimeout(authorDebounceRef.current);
    setNameInput('');
    setNameFilter('');
    setAcademicContextInput('');
    setAcademicContextFilter('');
    setFavoritesFilter('');
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

  const canOpenTemplate = (t: Template): boolean => {
    if (canShow) {
      return true;
    }
    if (profile?.id && t.created_by === profile.id) {
      return true;
    }
    const isAssignedReviewer =
      t.reviewers?.some((r) => r.user_id === profile?.id) === true;
    if (isAssignedReviewer && (t.status === 'in_review' || t.status === 'rejected')) {
      return true;
    }
    return false;
  };

  const handleRowClick = (t: Template) => {
    if (!canOpenTemplate(t)) {
      return;
    }

    const backTo = processId ? `/procesos/${processId}` : '/dashboard';
    if (t.list_variant === 'published_fallback' && t.latest_published_version_id) {
      navigate(`/templates/${t.id}?templateVersionId=${encodeURIComponent(t.latest_published_version_id)}`, {
        state: { backTo, processId },
      });
      return;
    }
    const isAssignedReviewer =
      t.reviewers?.some((r) => r.user_id === profile?.id) === true;
    const openReviewView = t.status === 'in_review' && isAssignedReviewer && canReview;
    if (openReviewView) {
      navigate(`/templates/${t.id}/review`, { state: { backTo, processId } });
      return;
    }
    const isOwner = profile?.id != null && t.created_by === profile.id;
    if (shouldOpenTemplateEditorFromList(t, isOwner)) {
      navigate(`/templates/${t.id}/edit`, { state: { backTo, processId } });
      return;
    }
    navigate(`/templates/${t.id}`, { state: { backTo, processId } });
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
            (template.latest_published_version_id && favoriteTemplateIds.has(template.latest_published_version_id));
          return (
            <span className="flex items-center gap-2 min-w-0">
              {isFavorite ? <FavoriteInlineMark /> : null}
              <span className="truncate font-medium">{template.name}</span>
              {template.has_review_comments && (template.status === 'draft' || template.status === 'rejected') && profile && template.created_by === profile.id && (
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
          {listError}
        </div>
      )}
      {actionError && (
        <div className="rounded-lg border border-odoo-purple/30 bg-odoo-purple/5 px-4 py-3 text-sm text-text-primary dark:text-text-dark-primary flex justify-between gap-4">
          <span>{actionError}</span>
          <Button type="button" variant="ghost" size="xs" onClick={clearActionError} className="shrink-0">
            {t('templates:table.close')}
          </Button>
        </div>
      )}

      <DataTable<Template>
        columns={columns}
        rows={displayTemplates}
        loading={loading && catalogSorted.length === 0}
        rowKey={(t) => t.list_row_id ?? t.id}
        hiddenColumnIds={hiddenIds}
        onToggleHiddenColumn={toggleHidden}
        sortBy={sortBy}
        onSortChange={setSortBy}
        pageSize={pageSize}
        onPageSizeChange={(size) => {
          setPageSize(size);
          applyFilters({ per_page: size });
        }}
        filtersLabel={t('templates:table.filtersLabel')}
        columnsLabel={t('templates:table.columnsLabel')}
        clearFiltersLabel={t('templates:table.clearFiltersLabel')}
        pageSizeLabel={t('templates:table.pageSizeLabel')}
        emptyMessage={t('templates:table.emptyFiltered')}
        onRowClick={handleRowClick}
        rowClassName={(t) => (canOpenTemplate(t) ? '' : 'opacity-60 cursor-not-allowed')}
        filtersActiveCount={filtersActiveCount}
        onClearFilters={clearFilters}
        filtersStorageKey="maya:dms:templates-table"
        filtersPanel={
          <>
            <FilterField label={t('templates:table.filters.name')}>
              <TextInput
                fieldSize="sm"
                placeholder={t('templates:table.searchName')}
                value={nameInput}
                onChange={handleNameChange}
              />
            </FilterField>
            <FilterField label={t('templates:table.filters.visibility')}>
              <TextInput
                fieldSize="sm"
                type="search"
                placeholder={t('templates:table.searchVisibility')}
                value={academicContextInput}
                onChange={handleAcademicContextChange}
              />
            </FilterField>
            <FilterField label={t('templates:table.filters.status')}>
              <Select
                fieldSize="sm"
                value={filterUi.status}
                onChange={(e: React.ChangeEvent<HTMLSelectElement>) =>
                  applyFilters({ status: (e.target.value as any) || undefined })
                }
              >
                {statusOptions.map((o) => (
                  <option key={o.value || 'all'} value={o.value}>
                    {o.label}
                  </option>
                ))}
              </Select>
            </FilterField>
            <FilterField label={t('templates:table.filters.author')}>
              <TextInput
                fieldSize="sm"
                placeholder={t('templates:table.authorPlaceholder')}
                value={authorInput}
                onChange={handleAuthorChange}
              />
            </FilterField>
            <FilterField label={t('templates:table.filters.favorites')}>
              <Select
                fieldSize="sm"
                value={favoritesFilter}
                onChange={(e: React.ChangeEvent<HTMLSelectElement>) => {
                  setFavoritesFilter(e.target.value);
                  applyFilters({ page: 1 });
                }}
              >
                {favoritesFilterOptions.map((o) => (
                  <option key={o.value || 'all'} value={o.value}>
                    {o.label}
                  </option>
                ))}
              </Select>
            </FilterField>
            <FilterField label={t('templates:table.filters.validationUntil')}>
              <DatePicker
                value={filterUi.deliveryDeadline || null}
                onChange={(d: string | null) =>
                  applyFilters({ delivery_deadline: d ?? undefined, page: 1 })
                }
                placeholder={t('templates:table.deadlinePlaceholder')}
                ariaLabel={t('templates:table.deadlineAria')}
              />
            </FilterField>
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
