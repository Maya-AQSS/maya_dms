import { useEffect, useRef, useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTemplates } from '../hooks/useTemplates';
import { STATUS_OPTIONS, VISIBILITY_OPTIONS, visibilityLabel } from '../constants';
import { Button, FilterField, Select, TextInput } from '../../../ui';
import { DataTable, DatePicker, Pagination, useTablePreferences, type ColumnDef } from '@maya/shared-ui-react';
import type { Template, TemplateStatus, TemplateVisibilityLevel } from '../../../types/templates';

const STATUS_BADGE: Record<TemplateStatus, string> = {
  draft: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
  in_review: 'bg-amber-200 text-amber-900 dark:bg-amber-800/40 dark:text-amber-200',
  published: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
  archived: 'bg-ui-border text-text-secondary dark:bg-ui-dark-border dark:text-text-dark-secondary',
};

const STATUS_LABEL: Record<TemplateStatus, string> = {
  draft: 'Borrador',
  in_review: 'En revisión',
  published: 'Publicada',
  archived: 'Archivada',
};

const VISIBILITY_BADGE: Record<TemplateVisibilityLevel, string> = {
  personal:   'bg-ui-border text-text-secondary dark:bg-ui-dark-border dark:text-text-dark-secondary',
  global:     'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
  study_type: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
  study:      'bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-300',
  module:     'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
  team:       'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300',
};

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
      <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${VISIBILITY_BADGE[t.visibility_level]}`}>
        {visibilityLabel(t.visibility_level)}
      </span>
    ),
  },
  {
    id: 'author_name',
    header: 'Autor',
    cell: (t) => <span className="text-xs text-text-secondary dark:text-text-dark-secondary">{t.author_name ?? '—'}</span>,
  },
  {
    id: 'status',
    header: 'Estado',
    cell: (t) => {
      const status = t.status as TemplateStatus;
      return (
        <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${STATUS_BADGE[status] ?? ''}`}>
          {STATUS_LABEL[status] ?? status}
        </span>
      );
    },
  },
  {
    id: 'delivery_deadline',
    header: 'Fecha límite',
    cell: (t) => <span className="text-xs text-text-secondary dark:text-text-dark-secondary">{formatDate(t.delivery_deadline)}</span>,
  },
];

export function TemplatesTable() {
  const navigate = useNavigate();
  const { hiddenIds, toggleHidden, sortBy, setSortBy, pageSize, setPageSize } = useTablePreferences({
    storageKey: 'maya:dms:templates-table',
  });
  const {
    templates,
    meta,
    filters,
    loading,
    listError,
    actionError,
    clearActionError,
    applyFilters,
    goToPage,
  } = useTemplates();

  const [nameInput, setNameInput] = useState('');
  const [nameFilter, setNameFilter] = useState('');
  const nameDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Sync user-selected pageSize into the templates hook (server-side per_page).
  useEffect(() => {
    if (filters.per_page !== pageSize) {
      applyFilters({ per_page: pageSize });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pageSize]);
  const [authorInput, setAuthorInput] = useState(filters.author_name ?? '');
  const authorDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const filteredTemplates = useMemo(() => {
    if (!nameFilter) return templates;
    const needle = nameFilter.toLowerCase();
    return templates.filter((t) => (t.name ?? '').toLowerCase().includes(needle));
  }, [templates, nameFilter]);

  const filterUi = useMemo(
    () => ({
      visibility: filters.visibility_level ?? '',
      status: filters.status ?? '',
      deliveryDeadline: filters.delivery_deadline ?? '',
    }),
    [filters],
  );

  const filtersActiveCount = [
    nameFilter,
    filters.visibility_level,
    filters.status,
    filters.author_name,
    filters.delivery_deadline,
  ].filter(Boolean).length;

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

  const clearFilters = () => {
    if (nameDebounceRef.current) clearTimeout(nameDebounceRef.current);
    if (authorDebounceRef.current) clearTimeout(authorDebounceRef.current);
    setNameInput('');
    setNameFilter('');
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

      <DataTable
        columns={COLUMNS}
        rows={filteredTemplates}
        loading={loading}
        rowKey={(t) => t.id}
        hiddenColumnIds={hiddenIds}
        onToggleHiddenColumn={toggleHidden}
        pageSize={pageSize}
        onPageSizeChange={(size) => {
          setPageSize(size)
          applyFilters({ per_page: size })
        }}
        sortBy={sortBy}
        onSortChange={setSortBy}
        emptyMessage="No hay plantillas con los filtros actuales."
        filtersActiveCount={filtersActiveCount}
        onClearFilters={clearFilters}
        filtersStorageKey="maya:dms:templates-table"
        onRowClick={(t) => navigate(`/templates/${t.id}`)}
        filtersPanel={
          <>
            <FilterField label="Nombre">
              <TextInput
                fieldSize="sm"
                type="search"
                placeholder="Buscar por nombre..."
                value={nameInput}
                onChange={handleNameChange}
              />
            </FilterField>
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
              <Select
                fieldSize="sm"
                value={filterUi.status}
                onChange={(e) => applyFilters({ status: e.target.value || undefined })}
              >
                {STATUS_OPTIONS.map((o) => (
                  <option key={o.value || 'all'} value={o.value}>{o.label}</option>
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
            <FilterField label="Fecha límite">
              <DatePicker
                value={filterUi.deliveryDeadline || null}
                onChange={(d) => applyFilters({ delivery_deadline: d ?? undefined })}
                placeholder="Seleccionar fecha..."
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
          info={`Página ${meta.current_page} de ${meta.last_page} — ${meta.total} plantillas`}
        />
      )}
    </div>
  );
}
