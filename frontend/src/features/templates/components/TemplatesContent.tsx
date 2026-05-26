import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
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
} from '@ceedcv-maya/shared-ui-react';
import { useTemplates } from '../hooks/useTemplates';
import { buildTemplatesListMeta, sliceTemplatesPage } from '../clientTemplatePagination';
import { STATUS_OPTIONS } from '../constants';
import { TemplateCard } from './TemplateCard';
import { TemplateHierarchyFields } from './TemplateHierarchyFields';
import { useUserProfile } from '../../../features/user-profile';
import { useHierarchy } from '../../../features/hierarchy';
import { formatListRowVisibilityCaption, listRowSearchMatches } from '../../../utils/academicContextSearch';
import type { Template, TemplateStatus } from '../../../types/templates';
import { useFavoritesIds } from '../../../hooks/useFavoritesIds';
import { FavoriteInlineMark } from '../../../components/FavoriteInlineMark';
import { formatCalendarDateForBrowser } from '../../../utils/formatCalendarDate';

const STATUS_LABEL: Record<TemplateStatus, string> = {
  draft: 'Borrador',
  in_review: 'En revisión',
  published: 'Publicada',
  archived: 'Archivada',
  rejected: 'Rechazada',
};

// Estado y visibilidad: clases en `@ceedcv-maya/shared-ui-react/badges`.

/**
 * Gestión de plantillas normativas: datos vía {@link useTemplates}.
 */
export function TemplatesContent() {
  const navigate = useNavigate();
  const { t } = useTranslation(['templates', 'common']);
  const { profile } = useUserProfile();
  const { hierarchy } = useHierarchy();
  const { hiddenIds, toggleHidden, sortBy, setSortBy, pageSize, setPageSize } = useTablePreferences({
    storageKey: 'maya:dms:templates-content',
  });
  const { templateIds: favoriteTemplateIds } = useFavoritesIds();
  const {
    catalogSorted,
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

  const [academicContextInput, setAcademicContextInput] = useState('');
  const [academicContextFilter, setAcademicContextFilter] = useState('');
  const academicContextDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const listPage = filters.page ?? 1;
  const listPerPage = filters.per_page ?? pageSize;

  const clientFilteredCatalog = useMemo(() => {
    if (!academicContextFilter.trim()) return catalogSorted;
    return catalogSorted.filter((t) =>
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
  }, [catalogSorted, academicContextFilter, hierarchy]);

  useEffect(() => {
    const last = Math.max(1, Math.ceil(clientFilteredCatalog.length / Math.max(1, listPerPage)));
    if (listPage > last) {
      applyFilters({ page: last });
    }
  }, [clientFilteredCatalog.length, listPage, listPerPage, applyFilters]);

  const pagedSource = useMemo(
    () => sliceTemplatesPage(clientFilteredCatalog, listPage, listPerPage),
    [clientFilteredCatalog, listPage, listPerPage],
  );

  const listMeta = useMemo(
    () => buildTemplatesListMeta(clientFilteredCatalog.length, listPage, listPerPage),
    [clientFilteredCatalog.length, listPage, listPerPage],
  );

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

  const filterUi = useMemo(
    () => ({
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
    academicContextFilter,
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
    if (academicContextDebounceRef.current) clearTimeout(academicContextDebounceRef.current);
    setAuthorInput('');
    setAcademicContextInput('');
    setAcademicContextFilter('');
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

  const displayTemplates = useMemo(() => {
    /** Con filtro de estado ≠ publicada, no mostrar la fila sintética de última publicada (siempre `published`). */
    const includePublishedFallbackRow = !filters.status || filters.status === 'published';

    const out: Template[] = [];
    for (const t of pagedSource) {
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
  }, [pagedSource, profile?.id, filters.delivery_deadline, filters.status]);

  const handleRowClick = (t: Template) => {
    if (t.list_variant === 'published_fallback' && t.latest_published_version_id) {
      navigate(`/templates/${t.id}?templateVersionId=${encodeURIComponent(t.latest_published_version_id)}`);
      return;
    }
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
        cell: (template) => {
          const versionId = template.latest_published_version_id;
          return (
            <span className="flex items-center gap-2 min-w-0">
              {versionId && favoriteTemplateIds.has(versionId) ? <FavoriteInlineMark /> : null}
              <span className="truncate font-medium">{template.name}</span>
              {template.has_review_comments && (template.status === 'draft' || template.status === 'rejected') && profile && template.created_by === profile.id && (
                <span
                  className="shrink-0 inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-xs font-bold bg-danger/10 text-danger-dark dark:text-danger border border-danger/20"
                  title={t('templates:pendingReviewTitle')}
                >
                  ⚠ Revisión
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
              {STATUS_LABEL[status] ?? status}
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
              <TextInput
                fieldSize="sm"
                type="search"
                placeholder={t('templates:table.searchVisibility')}
                value={academicContextInput}
                onChange={handleAcademicContextChange}
              />
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
                placeholder={t('templates:table.authorPlaceholder')}
                value={authorInput}
                onChange={handleAuthorChange}
              />
            </FilterField>
            <FilterField label="Fecha de validación (hasta)">
              <DatePicker
                value={filterUi.deliveryDeadline || null}
                onChange={(d) => applyFilters({ delivery_deadline: d ?? undefined })}
                placeholder={t('templates:table.deadlinePlaceholder')}
                ariaLabel="Plantillas no publicadas cuya fecha límite de validación sea esta fecha o anterior (las publicadas no aplican)"
              />
            </FilterField>
            <div className="col-span-full pt-2 border-t border-ui-border/50 dark:border-ui-dark-border/50">
              <FieldLabel>{t('fields.linking')}</FieldLabel>
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
                maxLevel={undefined}
                showTeam={true}
              />
            </div>
          </>
        }
      />

      {listMeta && (
        <Pagination
          currentPage={listMeta.current_page}
          totalPages={listMeta.last_page}
          onChange={goToPage}
          info={
            listMeta.total > 0
              ? `${(listMeta.current_page - 1) * listMeta.per_page + 1}–${Math.min(listMeta.current_page * listMeta.per_page, listMeta.total)} de ${listMeta.total} plantillas`
              : undefined
          }
        />
      )}
    </div>
  );
}
