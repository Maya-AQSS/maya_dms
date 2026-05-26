import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { fetchTemplates } from '../api/templates';
import { fetchProcesses } from '../api/processes';
import {
  buildTemplatesListMeta,
  sliceTemplatesPage,
} from '../features/templates/clientTemplatePagination';
import { FAVORITES_FILTER_OPTIONS } from '../features/templates/constants';
import type { Template, TemplateListFilters } from '../types/templates';
import { useFavoritesIds } from '../hooks/useFavoritesIds';
import { FavoriteInlineMark } from '../components/FavoriteInlineMark';
import type { Process } from '../types/processes';
import { formatCalendarDateForBrowser } from '../utils/formatCalendarDate';
import { useHierarchy } from '../features/hierarchy';
import { formatListRowVisibilityCaption, listRowSearchMatches } from '../utils/academicContextSearch';
import { normalizeForSearch } from '../utils/normalizeForSearch';
import {
  DataTable,
  DatePicker,
  FilterField,
  PageTitle,
  Pagination,
  Select,
  TextInput,
  useTablePreferences,
  visibilityBadgeClass,
  type ColumnDef,
} from '@ceedcv-maya/shared-ui-react';

/** Orden local solo en columnas con `sortable: true` (nombre y fecha de publicación). */
const SORTABLE_SELECTOR_COLUMN_IDS = new Set(['name', 'latest_published_at']);

export function NuevaProgramacionSelectorPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const { t } = useTranslation(['documents', 'common']);
  const { hierarchy } = useHierarchy();
  const locationState = location.state as { moduleId?: string; processId?: string } | null;
  const selectedModuleId = locationState?.moduleId;
  const selectedProcessId = locationState?.processId;
  const [process, setProcess] = useState<Process | null>(null);

  const { hiddenIds, toggleHidden, sortBy, setSortBy, pageSize, setPageSize } = useTablePreferences({
    storageKey: 'maya:dms:nueva-programacion-selector',
  });
  const { templateIds: favoriteTemplateIds } = useFavoritesIds();

  const [favoritesFilter, setFavoritesFilter] = useState('');
  const [filters, setFilters] = useState<TemplateListFilters>({
    usable_for_documents: true,
    per_page: pageSize,
  });
  const [allTemplates, setAllTemplates] = useState<Template[]>([]);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState<string | null>(null);
  const [nameInput, setNameInput] = useState('');
  const [nameFilter, setNameFilter] = useState('');
  const nameDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const [authorInput, setAuthorInput] = useState('');
  const authorDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const [academicContextInput, setAcademicContextInput] = useState('');
  const [academicContextFilter, setAcademicContextFilter] = useState('');
  const academicContextDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    if (!selectedProcessId) {
      setProcess(null);
      return;
    }
    let cancelled = false;
    void fetchProcesses()
      .then((res) => {
        if (cancelled) return;
        setProcess(res.data.find((p) => p.id === selectedProcessId) ?? null);
      })
      .catch(() => {
        if (!cancelled) setProcess(null);
      });
    return () => {
      cancelled = true;
    };
  }, [selectedProcessId]);

  useEffect(() => {
    let cancelled = false;
    const load = async () => {
      setLoading(true);
      setListError(null);
      try {
        const res = await fetchTemplates({
          usable_for_documents: true,
          author_name: filters.author_name,
          published_on: filters.published_on,
          ...(selectedProcessId ? { process_id: selectedProcessId } : {}),
        });
        if (!cancelled) {
          setAllTemplates(res.data);
        }
      } catch (e) {
        if (!cancelled) {
          setListError(e instanceof Error ? e.message : 'No se pudieron cargar las plantillas.');
          setAllTemplates([]);
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    void load();
    return () => { cancelled = true; };
  }, [filters.author_name, filters.published_on, selectedProcessId]);

  const listPage = filters.page ?? 1;
  const listPerPage = filters.per_page ?? pageSize;

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
    if (favoritesFilter !== 'favorites') return mappedTemplates;
    return mappedTemplates.filter(
      (template) => !!template.latest_published_version_id && favoriteTemplateIds.has(template.latest_published_version_id),
    );
  }, [mappedTemplates, favoritesFilter, favoriteTemplateIds]);

  const afterName = useMemo(() => {
    if (!nameFilter.trim()) {
      return afterFavorites;
    }
    const needle = normalizeForSearch(nameFilter.trim());
    return afterFavorites.filter((template) => normalizeForSearch(template.name ?? '').includes(needle));
  }, [afterFavorites, nameFilter]);

  const afterAcademicContext = useMemo(() => {
    if (!academicContextFilter.trim()) {
      return afterName;
    }
    return afterName.filter((template) =>
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
  }, [afterName, academicContextFilter, hierarchy]);

  const sortedList = useMemo(() => {
    if (!sortBy || !SORTABLE_SELECTOR_COLUMN_IDS.has(sortBy.columnId)) {
      return afterAcademicContext;
    }
    const { columnId, direction } = sortBy;
    const dir = direction === 'asc' ? 1 : -1;

    return [...afterAcademicContext].sort((a, b) => {
      if (columnId === 'name') {
        return (a.name ?? '').localeCompare(b.name ?? '', 'es') * dir;
      }
      if (columnId === 'latest_published_at') {
        const valA = a.latest_published_at ?? '';
        const valB = b.latest_published_at ?? '';
        if (valA < valB) {
          return -1 * dir;
        }
        if (valA > valB) {
          return 1 * dir;
        }

        return 0;
      }

      return 0;
    });
  }, [afterAcademicContext, sortBy]);

  useEffect(() => {
    const last = Math.max(1, Math.ceil(sortedList.length / Math.max(1, listPerPage)));
    if (listPage > last) {
      setFilters((f) => ({ ...f, page: last }));
    }
  }, [sortedList.length, listPage, listPerPage]);

  const templates = useMemo(
    () => sliceTemplatesPage(sortedList, listPage, listPerPage),
    [sortedList, listPage, listPerPage],
  );

  const meta = useMemo(
    () => buildTemplatesListMeta(sortedList.length, listPage, listPerPage),
    [sortedList.length, listPage, listPerPage],
  );

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

  useEffect(() => {
    if (filters.per_page !== pageSize) {
      setFilters((f) => ({ ...f, per_page: pageSize, page: 1 }));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pageSize]);

  const applyFilters = (patch: Partial<TemplateListFilters>) => {
    setFilters((f) => ({ ...f, ...patch, usable_for_documents: true, page: 1 }));
  };

  const goToPage = (page: number) => {
    setFilters((f) => ({ ...f, page: Math.max(1, page) }));
  };

  const handleNameChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setNameInput(value);
    if (nameDebounceRef.current) {
      clearTimeout(nameDebounceRef.current);
    }
    nameDebounceRef.current = setTimeout(() => {
      setNameFilter(value);
      setFilters((f) => ({ ...f, page: 1 }));
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
      setFilters((f) => ({ ...f, page: 1 }));
    }, 400);
  };

  const clearFilters = () => {
    if (nameDebounceRef.current) {
      clearTimeout(nameDebounceRef.current);
    }
    if (authorDebounceRef.current) {
      clearTimeout(authorDebounceRef.current);
    }
    if (academicContextDebounceRef.current) {
      clearTimeout(academicContextDebounceRef.current);
    }
    setNameInput('');
    setNameFilter('');
    setAuthorInput('');
    setAcademicContextInput('');
    setAcademicContextFilter('');
    setFavoritesFilter('');
    setFilters({ usable_for_documents: true, per_page: pageSize, page: 1 });
  };

  const filterUi = useMemo(
    () => ({
      publishedOn: filters.published_on ?? '',
    }),
    [filters],
  );

  const filtersActiveCount =
    (favoritesFilter ? 1 : 0) +
    [nameFilter, academicContextFilter, filters.author_name, filters.published_on].filter(
      (v) => v && String(v).trim() !== '',
    ).length;

  return (
    <div className="min-h-full overflow-y-auto p-6 space-y-4">
      <PageTitle
        title={t('documents:newDocument')}
        subtitle={
          process
            ? `Proceso: ${process.code} — ${process.name} · Selecciona una plantilla`
            : 'Selecciona una plantilla'
        }
        onBack={() =>
          navigate(selectedProcessId ? `/procesos/${selectedProcessId}` : '/dashboard', {
            state: { tab: 'documents' },
          })
        }
        backLabel="Documentos"
      />

      {listError && (
        <div className="rounded-lg border border-warning/40 bg-warning-light/40 dark:bg-warning-dark/10 px-4 py-3 text-sm text-warning-dark dark:text-warning-light">
          {listError}
        </div>
      )}

      <DataTable
        columns={columns}
        rows={templates}
        loading={loading}
        rowKey={(t) => t.list_row_id ?? t.id}
        hiddenColumnIds={hiddenIds}
        onToggleHiddenColumn={toggleHidden}
        sortBy={sortBy}
        onSortChange={setSortBy}
        pageSize={pageSize}
        onPageSizeChange={setPageSize}
        emptyMessage="No hay plantillas utilizables para crear documentos con los filtros actuales."
        filtersActiveCount={filtersActiveCount}
        onClearFilters={clearFilters}
        filtersStorageKey="maya:dms:nueva-programacion-selector"
        onRowClick={(t) => {
          const selectedTemplateVersionId =
            t.list_variant === 'published_fallback'
              ? (t.latest_published_version_id ?? null)
              : null;
          navigate(`/documentos/nuevo/${t.id}/wizard`, {
            state: {
              moduleId: selectedModuleId,
              processId: selectedProcessId,
              templateVersionId: selectedTemplateVersionId,
            },
          });
        }}
        filtersPanel={
          <>
            <FilterField label="Nombre">
              <TextInput
                fieldSize="sm"
                placeholder={t('documents:wizard.searchByName')}
                value={nameInput}
                onChange={handleNameChange}
              />
            </FilterField>
            <FilterField label="Visibilidad">
              <TextInput
                fieldSize="sm"
                type="search"
                placeholder={t('documents:wizard.searchVisibility')}
                value={academicContextInput}
                onChange={handleAcademicContextChange}
              />
            </FilterField>
            <FilterField label="Autor">
              <TextInput
                fieldSize="sm"
                placeholder={t('documents:wizard.authorPlaceholder')}
                value={authorInput}
                onChange={handleAuthorChange}
              />
            </FilterField>
            <FilterField label="Favoritos">
              <Select
                fieldSize="sm"
                value={favoritesFilter}
                onChange={(e) => {
                  setFavoritesFilter(e.target.value);
                  setFilters((f) => ({ ...f, page: 1 }));
                }}
              >
                {FAVORITES_FILTER_OPTIONS.map((o) => (
                  <option key={o.value || 'all'} value={o.value}>
                    {o.label}
                  </option>
                ))}
              </Select>
            </FilterField>
            <FilterField label="Publicadas desde">
              <DatePicker
                value={filterUi.publishedOn || null}
                onChange={(d) => applyFilters({ published_on: d ?? undefined })}
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
          onChange={goToPage}
          info={`${meta.total} ${meta.total === 1 ? 'plantilla disponible' : 'plantillas disponibles'}`}
        />
      )}
    </div>
  );
}
