import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  DataTable,
  FilterField,
  Pagination,
  Select,
  TextInput,
  useDebounce,
  useTablePreferences,
  type ColumnDef,
} from '@ceedcv-maya/shared-ui-react';
import { useProcessesQuery } from '../../../hooks/useProcesses';
import { ColorBadge } from './ColorBadge';
import { getProcessIcon } from '../../../components/layout/processIcons';
import { normalizeForSearch } from '../../../utils/normalizeForSearch';
import type { Process } from '../../../types/processes';

export function ProcessesTable() {
  const navigate = useNavigate();
  const { hiddenIds, toggleHidden, sortBy, setSortBy, pageSize, setPageSize } =
    useTablePreferences({ storageKey: 'maya:dms:processes-table' });

  const processesQuery = useProcessesQuery();
  const allProcesses: Process[] = processesQuery.data?.data ?? [];
  const listLoading = processesQuery.isLoading;

  const [nameInput, setNameInput] = useState('');
  const nameFilter = useDebounce(nameInput, 400);
  const [parentFilter, setParentFilter] = useState('');
  const [page, setPage] = useState(1);

  const handleNameChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setNameInput(e.target.value);
    setPage(1);
  };

  const handleParentChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    setParentFilter(e.target.value);
    setPage(1);
  };

  const clearFilters = () => {
    setNameInput('');
    setParentFilter('');
    setPage(1);
  };

  const filtered = useMemo(() => {
    let list = allProcesses;
    if (nameFilter.trim()) {
      const needle = normalizeForSearch(nameFilter.trim());
      list = list.filter(
        (p) =>
          normalizeForSearch(p.name).includes(needle) ||
          normalizeForSearch(p.code).includes(needle) ||
          normalizeForSearch(p.alias).includes(needle),
      );
    }
    if (parentFilter === 'root') {
      list = list.filter((p) => p.process_parent_id === null);
    } else if (parentFilter) {
      list = list.filter((p) => p.process_parent_id === parentFilter);
    }
    return list;
  }, [allProcesses, nameFilter, parentFilter]);

  const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
  const safePage = Math.min(page, totalPages);
  const pageSlice = filtered.slice((safePage - 1) * pageSize, safePage * pageSize);
  const filtersActiveCount = [nameFilter, parentFilter].filter(Boolean).length;

  const topLevelProcesses = useMemo(
    () => allProcesses.filter((p) => p.process_parent_id === null),
    [allProcesses],
  );

  const columns: ColumnDef<Process>[] = useMemo(
    () => [
      {
        id: 'code',
        header: 'Código',
        sortable: true,
        alwaysVisible: true,
        cell: (p) => <span className="font-mono text-sm font-medium">{p.code}</span>,
      },
      {
        id: 'name',
        header: 'Nombre',
        sortable: true,
        alwaysVisible: true,
        cell: (p) => <span className="font-medium truncate">{p.name}</span>,
      },
      {
        id: 'alias',
        header: 'Alias',
        cell: (p) => (
          <span className="text-sm text-text-secondary dark:text-text-dark-secondary">{p.alias}</span>
        ),
      },
      {
        id: 'icon',
        header: 'Icono',
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
        header: 'Color',
        cell: (p) => <ColorBadge color={p.color} size="sm" />,
      },
    ],
    [],
  );

  return (
    <div className="space-y-4">
      <DataTable<Process>
        columns={columns}
        rows={pageSlice}
        loading={listLoading}
        rowKey={(p) => p.id}
        hiddenColumnIds={hiddenIds}
        onToggleHiddenColumn={toggleHidden}
        sortBy={sortBy}
        onSortChange={setSortBy}
        pageSize={pageSize}
        onPageSizeChange={(size) => {
          setPageSize(size);
          setPage(1);
        }}
        filtersLabel="Filtros"
        columnsLabel="Columnas"
        clearFiltersLabel="Limpiar filtros"
        pageSizeLabel="Por página"
        emptyMessage="No se encontraron procesos."
        filtersActiveCount={filtersActiveCount}
        onClearFilters={clearFilters}
        filtersStorageKey="maya:dms:processes-table"
        onRowClick={(p) => navigate(`/admin/procesos/${p.id}`)}
        filtersPanel={
          <>
            <FilterField label="Buscar">
              <TextInput
                fieldSize="sm"
                type="search"
                placeholder="Código, nombre o alias…"
                value={nameInput}
                onChange={handleNameChange}
              />
            </FilterField>
            <FilterField label="Proceso padre">
              <Select
                fieldSize="sm"
                value={parentFilter}
                onChange={handleParentChange}
              >
                <option value="">Todos</option>
                <option value="root">Solo raíz (sin padre)</option>
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

      <Pagination
        currentPage={safePage}
        totalPages={totalPages}
        onChange={setPage}
        info={`${filtered.length} proceso${filtered.length !== 1 ? 's' : ''}`}
      />
    </div>
  );
}
