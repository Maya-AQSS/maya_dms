import { useEffect, useMemo, useRef, useState } from 'react';
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
} from '@maya/shared-ui-react';
import { useTemplates } from '../hooks/useTemplates';
import { buildTemplatesListMeta, sliceTemplatesPage } from '../clientTemplatePagination';
import { FAVORITES_FILTER_OPTIONS, STATUS_OPTIONS } from '../constants';
import type { Template, TemplateStatus, TemplateVisibilityLevel } from '../../../types/templates';
import { useUserProfile } from '../../../features/user-profile';
import { useHierarchy } from '../../../features/hierarchy';
import { formatListRowVisibilityCaption, listRowSearchMatches } from '../../../utils/academicContextSearch';
import { useFavoritesIds } from '../../../hooks/useFavoritesIds';
import { FavoriteInlineMark } from '../../../components/FavoriteInlineMark';
import { formatCalendarDateForBrowser } from '../../../utils/formatCalendarDate';

const STATUS_LABEL: Record<TemplateStatus, string> = {
  draft: 'Borrador',
  in_review: 'En revisión',
  published: 'Publicada',
  archived: 'Archivada',
};

// Estado y visibilidad: clases en `@maya/shared-ui-react/badges`.

type Props = {
  /** Filtra el listado por proceso. No se expone en el panel de filtros. */
  processId?: string;
};

export function TemplatesTable({ processId }: Props = {}) {
  const navigate = useNavigate();
  const { profile } = useUserProfile();
  const { hierarchy } = useHierarchy();

  const { hiddenIds, toggleHidden, sortBy, setSortBy, pageSize, setPageSize } =
    useTablePreferences({ storageKey: 'maya:dms:templates-table' });
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
  const listPerPage = filters.per_page ?? 20;

  const clientFilteredCatalog = useMemo(() => {
    let list = catalogSorted;
    if (favoritesFilter === 'favorites') {
      list = list.filter((t) => favoriteTemplateIds.has(t.id));
    }
    if (nameFilter.trim()) {
      const needle = nameFilter.toLowerCase();
      list = list.filter((t) => (t.name ?? '').toLowerCase().includes(needle));
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

  const handleRowClick = (t: Template) => {
    if (t.list_variant === 'published_fallback' && t.latest_published_version_id) {
      navigate(`/templates/${t.id}?templateVersionId=${encodeURIComponent(t.latest_published_version_id)}`);
      return;
    }
    const isReviewer =
      t.status === 'in_review' && t.reviewers?.some((r) => r.user_id === profile?.id);
    const backTo = processId ? `/procesos/${processId}` : '/dashboard';
    navigate(isReviewer ? `/templates/${t.id}/review` : `/templates/${t.id}`, {
      state: { backTo, processId },
    });
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
        cell: (t) => {
          const level = t.visibility_level as TemplateVisibilityLevel;
          const caption = formatListRowVisibilityCaption(hierarchy, {
            visibility_level: level,
            study_type_id: t.study_type_id,
            study_id: t.study_id,
            module_id: t.module_id,
            team_id: t.team_id,
            team: t.team,
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
        header: 'Fecha de validación',
        sortable: true,
        cell: (t) => (
          <span className="text-xs text-text-secondary dark:text-text-dark-secondary">
            {t.status === 'published' ? '—' : formatCalendarDateForBrowser(t.delivery_deadline)}
          </span>
        ),
      },
    ],
    [profile, favoriteTemplateIds, hierarchy],
  );

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
        emptyMessage="No hay plantillas con los filtros actuales."
        onRowClick={handleRowClick}
        filtersActiveCount={filtersActiveCount}
        onClearFilters={clearFilters}
        filtersStorageKey="maya:dms:templates-table"
        filtersPanel={
          <>
            <FilterField label="Nombre">
              <TextInput
                fieldSize="sm"
                placeholder="Buscar por nombre..."
                value={nameInput}
                onChange={handleNameChange}
              />
            </FilterField>
            <FilterField label="Contexto académico">
              <TextInput
                fieldSize="sm"
                type="search"
                placeholder="Global, personal, equipo, nombre de equipo o contexto académico…"
                value={academicContextInput}
                onChange={handleAcademicContextChange}
              />
            </FilterField>
            <FilterField label="Estado">
              <Select
                fieldSize="sm"
                value={filterUi.status}
                onChange={(e: React.ChangeEvent<HTMLSelectElement>) =>
                  applyFilters({ status: (e.target.value as any) || undefined })
                }
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
            <FilterField label="Favoritos">
              <Select
                fieldSize="sm"
                value={favoritesFilter}
                onChange={(e: React.ChangeEvent<HTMLSelectElement>) => {
                  setFavoritesFilter(e.target.value);
                  applyFilters({ page: 1 });
                }}
              >
                {FAVORITES_FILTER_OPTIONS.map((o) => (
                  <option key={o.value || 'all'} value={o.value}>
                    {o.label}
                  </option>
                ))}
              </Select>
            </FilterField>
            <FilterField label="Fecha de validación (hasta)">
              <DatePicker
                value={filterUi.deliveryDeadline || null}
                onChange={(d: string | null) =>
                  applyFilters({ delivery_deadline: d ?? undefined, page: 1 })
                }
                placeholder="Cualquier plazo…"
                ariaLabel="Plantillas no publicadas cuya fecha límite de validación sea esta fecha o anterior (las publicadas no aplican)"
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
              ? `${(meta.current_page - 1) * meta.per_page + 1}–${Math.min(meta.current_page * meta.per_page, meta.total)} de ${meta.total}`
              : undefined
          }
        />
      )}
    </div>
  );
}
