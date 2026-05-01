import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { fetchTemplates } from '../api/templates';
import { VISIBILITY_OPTIONS, visibilityLabel } from '../features/templates/constants';
import type { Template, TemplateListFilters, TemplatesListMeta, TemplateVisibilityLevel } from '../types/templates';
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

const COLUMNS: ColumnDef<Template>[] = [
  {
    id: 'name',
    header: 'Nombre',
    cell: (t) => <span className="font-medium">{t.name}</span>,
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
];

export function NuevaProgramacionSelectorPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const locationState = location.state as { moduleId?: string; processId?: string } | null;
  const selectedModuleId = locationState?.moduleId;
  const selectedProcessId = locationState?.processId;

  const { hiddenIds, toggleHidden, sortBy, setSortBy, pageSize, setPageSize } = useTablePreferences({
    storageKey: 'maya:dms:nueva-programacion-selector',
  });

  const [filters, setFilters] = useState<TemplateListFilters>({
    status: 'published',
    per_page: pageSize,
  });
  const [templates, setTemplates] = useState<Template[]>([]);
  const [meta, setMeta] = useState<TemplatesListMeta | null>(null);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState<string | null>(null);
  const [authorInput, setAuthorInput] = useState('');
  const authorDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    let cancelled = false;
    const load = async () => {
      setLoading(true);
      setListError(null);
      try {
        const res = await fetchTemplates({
          ...filters,
          status: 'published',
          ...(selectedProcessId ? { process_id: selectedProcessId } : {}),
        });
        if (!cancelled) {
          setTemplates(res.data);
          setMeta(res.meta);
        }
      } catch (e) {
        if (!cancelled) {
          setListError(e instanceof Error ? e.message : 'No se pudieron cargar las plantillas.');
          setTemplates([]);
          setMeta(null);
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    void load();
    return () => { cancelled = true; };
  }, [filters, selectedProcessId]);

  useEffect(() => {
    if (filters.per_page !== pageSize) {
      setFilters((f) => ({ ...f, per_page: pageSize, page: 1 }));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pageSize]);

  const applyFilters = (patch: Partial<TemplateListFilters>) => {
    setFilters((f) => ({ ...f, ...patch, status: 'published', page: 1 }));
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
    setFilters({ status: 'published', per_page: pageSize, page: 1 });
  };

  const filterUi = useMemo(
    () => ({
      visibility: filters.visibility_level ?? '',
      deliveryDeadline: filters.delivery_deadline ?? '',
    }),
    [filters],
  );

  const filtersActiveCount = [
    filters.visibility_level,
    filters.author_name,
    filters.delivery_deadline,
  ].filter(Boolean).length;

  return (
    <div className="min-h-full overflow-y-auto p-6 space-y-4">
      <PageTitle
        title="Nueva Programación"
        subtitle="Selecciona una plantilla"
        onBack={() => navigate('/procesos', { state: { tab: 'documents' } })}
        backLabel="Documentos"
      />

      {listError && (
        <div className="rounded-lg border border-warning/40 bg-warning-light/40 dark:bg-warning-dark/10 px-4 py-3 text-sm text-warning-dark dark:text-warning-light">
          {listError}
        </div>
      )}

      <DataTable
        columns={COLUMNS}
        rows={templates}
        loading={loading}
        rowKey={(t) => t.id}
        hiddenColumnIds={hiddenIds}
        onToggleHiddenColumn={toggleHidden}
        pageSize={pageSize}
        onPageSizeChange={setPageSize}
        sortBy={sortBy}
        onSortChange={setSortBy}
        emptyMessage="No hay plantillas publicadas con los filtros actuales."
        filtersActiveCount={filtersActiveCount}
        onClearFilters={clearFilters}
        filtersStorageKey="maya:dms:nueva-programacion-selector"
        onRowClick={(t) =>
          navigate(`/templates/${t.id}`, {
            state: {
              selectionMode: true,
              backTo: '/nueva-programacion',
              moduleId: selectedModuleId,
            },
          })
        }
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
