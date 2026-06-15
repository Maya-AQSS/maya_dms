import { useEffect, useMemo, useRef, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { buildBackState } from '@ceedcv-maya/shared-hooks-react';
import {
  DataTable,
  FilterField,
  Pagination,
  Select,
  TextInput,
  useTablePreferences,
  type ColumnDef,
} from '@ceedcv-maya/shared-ui-react';
import { useServerProcessesTable } from '../hooks/useServerProcessesTable';
import { useProcessesQuery } from '../../../hooks/useProcesses';
import { ColorBadge } from './ColorBadge';
import { getProcessIcon } from '../../../components/layout/processIcons';
import type { Process } from '../../../types/processes';

export function ProcessesTable() {
  const { t } = useTranslation(['processes', 'common']);
  const navigate = useNavigate();
  const location = useLocation();
  const { hiddenIds, toggleHidden } = useTablePreferences({
    storageKey: 'maya:dms:processes-table',
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
  } = useServerProcessesTable();

  // Búsqueda con debounce → param server-side `search`.
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

  // Si la página actual queda fuera de rango, corrige a la última.
  useEffect(() => {
    if (meta && meta.last_page >= 1 && page > meta.last_page) {
      onPageChange(meta.last_page);
    }
  }, [meta, page, onPageChange]);

  // Obtener procesos raíz para el selector (usamos la API sin paginar para la lista desplegable).
  const processesQuery = useProcessesQuery(undefined, { enabled: canIndex && rows.length > 0 });
  const topLevelProcesses = useMemo(
    () => (processesQuery.data?.data ?? []).filter((p) => p.process_parent_id === null),
    [processesQuery.data?.data],
  );

  const columns: ColumnDef<Process>[] = useMemo(
    () => [
      {
        id: 'code',
        header: t('processes:table.columnCode'),
        sortable: true,
        alwaysVisible: true,
        cell: (p) => <span className="font-mono text-sm font-medium">{p.code}</span>,
      },
      {
        id: 'name',
        header: t('processes:table.columnName'),
        sortable: true,
        alwaysVisible: true,
        cell: (p) => <span className="font-medium truncate">{p.name}</span>,
      },
      {
        id: 'alias',
        header: t('processes:table.columnAlias'),
        sortable: true,
        cell: (p) => (
          <span className="text-sm text-text-secondary dark:text-text-dark-secondary">{p.alias}</span>
        ),
      },
      {
        id: 'icon',
        header: t('processes:table.columnIcon'),
        cell: (p) => {
          if (!p.icon) return <span className="text-text-muted dark:text-text-dark-muted">—</span>;
          const bgStyle = p.color ? { backgroundColor: p.color + '33' } : { backgroundColor: 'rgba(0,0,0,0.06)' };
          const iconStyle = p.color ? { color: p.color } : undefined;
          return (
            <span
              className="w-6 h-6 inline-flex items-center justify-center rounded-full text-text-secondary dark:text-text-dark-secondary"
              style={bgStyle}
              title={p.icon}
            >
              <span style={iconStyle}>{getProcessIcon(p.icon)}</span>
            </span>
          );
        },
      },
      {
        id: 'color',
        header: t('processes:table.columnColor'),
        cell: (p) => <ColorBadge color={p.color} size="sm" />,
      },
    ],
    [t],
  );

  if (!canIndex) {
    return (
      <p className="text-sm text-text-secondary dark:text-text-dark-secondary py-4 text-center">
        {t('processes:table.noIndexPermission')}
      </p>
    );
  }

  if (error) {
    return (
      <p className="text-sm text-danger dark:text-danger py-4 text-center">
        Error: {error.message}
      </p>
    );
  }

  return (
    <div className="space-y-4">
      <DataTable<Process>
        columns={columns}
        rows={rows}
        loading={loading}
        rowKey={(p) => p.id}
        hiddenColumnIds={hiddenIds}
        onToggleHiddenColumn={toggleHidden}
        sortBy={sortBy}
        onSortChange={onSortChange}
        pageSize={pageSize}
        onPageSizeChange={onPageSizeChange}
        filtersLabel={t('processes:table.filtersLabel')}
        columnsLabel={t('processes:table.columnsLabel')}
        clearFiltersLabel={t('processes:table.clearFiltersLabel')}
        pageSizeLabel={t('processes:table.pageSizeLabel')}
        emptyMessage={t('processes:table.emptyMessage')}
        filtersActiveCount={filtersActiveCount}
        onClearFilters={clearFilters}
        filtersStorageKey="maya:dms:processes-table"
        onRowClick={(p) => navigate(`/admin/processes/${p.id}`, { state: buildBackState(location) })}
        filtersPanel={
          <>
            <FilterField label={t('processes:table.searchLabel')}>
              <TextInput
                fieldSize="sm"
                type="search"
                placeholder={t('processes:table.searchPlaceholder')}
                value={searchInput}
                onChange={handleSearchChange}
              />
            </FilterField>
            <FilterField label={t('processes:table.parentLabel')}>
              <Select
                fieldSize="sm"
                value={filters.parent_id ?? ''}
                onChange={(e) => {
                  setFilter('parent_id', e.target.value || undefined);
                }}
              >
                <option value="">{t('common:filters.all')}</option>
                <option value="root">{t('processes:table.onlyRoot')}</option>
                {topLevelProcesses.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.code} — {p.name}
                  </option>
                ))}
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
              ? t('processes:table.paginationInfo', {
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
