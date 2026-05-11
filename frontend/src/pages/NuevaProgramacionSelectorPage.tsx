import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { fetchTemplates } from '../api/templates';
import { fetchProcesses } from '../api/processes';
import {
  buildTemplatesListMeta,
  sliceTemplatesPage,
} from '../features/templates/clientTemplatePagination';
import { FAVORITES_FILTER_OPTIONS, VISIBILITY_OPTIONS, visibilityLabel } from '../features/templates/constants';
import type { Template, TemplateListFilters } from '../types/templates';
import { useFavoritesIds } from '../hooks/useFavoritesIds';
import { FavoriteInlineMark } from '../components/FavoriteInlineMark';
import type { Process } from '../types/processes';
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
} from '@maya/shared-ui-react';

function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—';
  return iso.slice(0, 10);
}

export function NuevaProgramacionSelectorPage() {
  const navigate = useNavigate();
  const location = useLocation();
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
  const [authorInput, setAuthorInput] = useState('');
  const authorDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

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
          visibility_level: filters.visibility_level,
          author_name: filters.author_name,
          delivery_deadline: filters.delivery_deadline,
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
  }, [
    filters.visibility_level,
    filters.author_name,
    filters.delivery_deadline,
    selectedProcessId,
  ]);

  const listPage = filters.page ?? 1;
  const listPerPage = filters.per_page ?? pageSize;

  const mappedTemplates = useMemo(() => {
    return allTemplates.map((t) => {
      if (t.status !== 'published' && t.latest_published_version_id) {
        return {
          ...t,
          name: t.latest_published_name ?? t.name,
          status: 'published' as const,
          version: t.latest_published_version_number ?? t.version,
          list_variant: 'published_fallback' as const,
          list_row_id: `${t.id}:published`,
        };
      }
      return {
        ...t,
        list_variant: 'live' as const,
        list_row_id: `${t.id}:live`,
      };
    });
  }, [allTemplates]);

  const afterFavorites = useMemo(() => {
    if (favoritesFilter !== 'favorites') return mappedTemplates;
    return mappedTemplates.filter((t) => favoriteTemplateIds.has(t.id));
  }, [mappedTemplates, favoritesFilter, favoriteTemplateIds]);

  const sortedTemplates = useMemo(() => {
    if (!sortBy) return afterFavorites;
    const { columnId, direction } = sortBy;
    const dir = direction === 'asc' ? 1 : -1;

    return [...afterFavorites].sort((a, b) => {
      let valA: string | number = '';
      let valB: string | number = '';

      if (columnId === 'name') {
        return (a.name ?? '').localeCompare(b.name ?? '', 'es') * dir;
      } else if (columnId === 'delivery_deadline') {
        valA = a.delivery_deadline ?? '';
        valB = b.delivery_deadline ?? '';
      } else if (columnId === 'version') {
        valA = a.version ?? 0;
        valB = b.version ?? 0;
      }

      if (valA < valB) return -1 * dir;
      if (valA > valB) return 1 * dir;
      return 0;
    });
  }, [afterFavorites, sortBy]);

  useEffect(() => {
    const last = Math.max(1, Math.ceil(sortedTemplates.length / Math.max(1, listPerPage)));
    if (listPage > last) {
      setFilters((f) => ({ ...f, page: last }));
    }
  }, [sortedTemplates.length, listPage, listPerPage]);

  const templates = useMemo(
    () => sliceTemplatesPage(sortedTemplates, listPage, listPerPage),
    [sortedTemplates, listPage, listPerPage],
  );

  const meta = useMemo(
    () => buildTemplatesListMeta(sortedTemplates.length, listPage, listPerPage),
    [sortedTemplates.length, listPage, listPerPage],
  );

  const columns: ColumnDef<Template>[] = useMemo(
    () => [
      {
        id: 'name',
        header: 'Nombre',
        alwaysVisible: true,
        cell: (t) => (
          <span className="flex items-center gap-2 min-w-0">
            {favoriteTemplateIds.has(t.id) && <FavoriteInlineMark />}
            <span className="truncate font-medium">{t.name}</span>
          </span>
        ),
        sortable: true,
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
        id: 'delivery_deadline',
        header: 'Fecha límite de validación',
        sortable: true,
        cell: (t) => (
          <span className="text-xs text-text-secondary dark:text-text-dark-secondary">
            {formatDate(t.delivery_deadline)}
          </span>
        ),
      },
      {
        id: 'version',
        header: 'Versión',
        cell: (t) => (
          <span className="text-xs text-text-secondary dark:text-text-dark-secondary">v{t.version}</span>
        ),
      },
    ],
    [favoriteTemplateIds],
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

  const handleAuthorChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setAuthorInput(value);
    if (authorDebounceRef.current) clearTimeout(authorDebounceRef.current);
    authorDebounceRef.current = setTimeout(() => {
      applyFilters({ author_name: value || undefined });
    }, 400);
  };

  const clearFilters = () => {
    if (authorDebounceRef.current) clearTimeout(authorDebounceRef.current);
    setAuthorInput('');
    setFavoritesFilter('');
    setFilters({ usable_for_documents: true, per_page: pageSize, page: 1 });
  };

  const filterUi = useMemo(
    () => ({
      visibility: filters.visibility_level ?? '',
      deliveryDeadline: filters.delivery_deadline ?? '',
    }),
    [filters],
  );

  const filtersActiveCount =
    (favoritesFilter ? 1 : 0) +
    [filters.visibility_level, filters.author_name, filters.delivery_deadline].filter(Boolean).length;

  return (
    <div className="min-h-full overflow-y-auto p-6 space-y-4">
      <PageTitle
        title="Nuevo Documento"
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
        pageSize={pageSize}
        onPageSizeChange={setPageSize}
        sortBy={sortBy}
        onSortChange={setSortBy}
        emptyMessage="No hay plantillas utilizables para crear documentos con los filtros actuales."
        filtersActiveCount={filtersActiveCount}
        onClearFilters={clearFilters}
        filtersStorageKey="maya:dms:nueva-programacion-selector"
        onRowClick={(t) => {
          const selectedTemplateVersionId =
            t.list_variant === 'published_fallback'
              ? (t.latest_published_version_id ?? null)
              : null;
          const path = selectedTemplateVersionId
            ? `/templates/${t.id}?templateVersionId=${encodeURIComponent(selectedTemplateVersionId)}`
            : `/templates/${t.id}`;
          navigate(path, {
            state: {
              selectionMode: true,
              backTo: '/documentos/nuevo',
              moduleId: selectedModuleId,
              processId: selectedProcessId,
              templateVersionId: selectedTemplateVersionId,
            },
          });
        }}
        filtersPanel={
          <>
            <FilterField label="Visibilidad">
              <Select
                fieldSize="sm"
                value={filterUi.visibility}
                onChange={(e) => applyFilters({ visibility_level: e.target.value || undefined })}
              >
                <option value="">Todas</option>
                {VISIBILITY_OPTIONS.map((o) => (
                  <option key={o.value} value={o.value}>{o.label}</option>
                ))}
              </Select>
            </FilterField>
            <FilterField label="Estado">
              <Select fieldSize="sm" value="published" disabled>
                <option value="published">Publicada</option>
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
            <FilterField label="Fecha límite de validación">
              <DatePicker
                value={filterUi.deliveryDeadline || null}
                onChange={(d) => applyFilters({ delivery_deadline: d ?? undefined })}
                placeholder="Seleccionar fecha…"
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
