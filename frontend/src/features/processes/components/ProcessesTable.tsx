import { useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import {
  Button,
  ConfirmDialog,
  DataTable,
  FilterField,
  Pagination,
  Select,
  TextInput,
  useConfirm,
  useTablePreferences,
  type ColumnDef,
} from '@ceedcv-maya/shared-ui-react';
import { useUserProfile } from '../../user-profile';
import { DMS_PERMISSIONS } from '../../../permissions';
import { useProcessesQuery } from '../../../hooks/useProcesses';
import {
  createProcess,
  updateProcess,
  deleteProcess,
} from '../../../api/processes';
import { ApiHttpError } from '../../../api/http';
import { ProcessFormModal } from './ProcessFormModal';
import { normalizeForSearch } from '../../../utils/normalizeForSearch';
import type { Process } from '../../../types/processes';
import type { ProcessPayload } from '../../../api/processes';

interface ProcessesTableProps {
  canCreate: boolean;
}

function formatError(err: unknown): string {
  if (err instanceof ApiHttpError) {
    if (err.status === 409)
      return err.message || 'El proceso tiene dependientes y no puede eliminarse.';
    if (err.status === 422) return err.message || 'Datos no válidos.';
    if (err.status === 403) return err.message || 'Sin permiso para esta acción.';
    return err.message || `Error HTTP ${err.status}`;
  }
  return err instanceof Error ? err.message : 'Error desconocido';
}

function ColorBadge({ color }: { color: string | null }) {
  if (!color) return <span className="text-text-muted dark:text-text-dark-muted">—</span>;
  return (
    <div className="flex items-center gap-2">
      <span
        className="inline-block h-4 w-4 rounded-full border border-ui-border"
        style={{ backgroundColor: color }}
        title={color}
      />
      <span className="text-xs font-mono text-text-secondary dark:text-text-dark-secondary">
        {color}
      </span>
    </div>
  );
}

export function ProcessesTable({ canCreate }: ProcessesTableProps) {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { hasPermission } = useUserProfile();
  const { confirmState, confirm, closeConfirm } = useConfirm();
  const { hiddenIds, toggleHidden, sortBy, setSortBy, pageSize, setPageSize } =
    useTablePreferences({ storageKey: 'maya:dms:processes-table' });

  const processesQuery = useProcessesQuery();
  const allProcesses: Process[] = processesQuery.data?.data ?? [];
  const listLoading = processesQuery.isLoading;

  const [actionError, setActionError] = useState<string | null>(null);
  const [actionInfo, setActionInfo] = useState<string | null>(null);

  const [modalOpen, setModalOpen] = useState(false);
  const [editTarget, setEditTarget] = useState<Process | null>(null);

  // Client-side filters
  const [nameInput, setNameInput] = useState('');
  const [nameFilter, setNameFilter] = useState('');
  const nameDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const [parentFilter, setParentFilter] = useState('');
  const [page, setPage] = useState(1);

  const mayUpdate = hasPermission(DMS_PERMISSIONS.processUpdate);
  const mayDelete = hasPermission(DMS_PERMISSIONS.processDelete);

  const invalidate = () =>
    void queryClient.invalidateQueries({ queryKey: ['processes'] });

  const handleSave = async (payload: ProcessPayload) => {
    try {
      setActionError(null);
      if (editTarget) {
        await updateProcess(editTarget.id, payload);
        setActionInfo('Cambios guardados.');
      } else {
        await createProcess(payload);
        setActionInfo('Proceso creado correctamente.');
      }
      invalidate();
    } catch (e) {
      setActionError(formatError(e));
      throw e;
    }
  };

  const handleDelete = async (process: Process) => {
    try {
      setActionError(null);
      await deleteProcess(process.id);
      setActionInfo('Proceso eliminado.');
      invalidate();
    } catch (e) {
      setActionError(formatError(e));
    }
  };

  const handleNameChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setNameInput(value);
    if (nameDebounceRef.current) clearTimeout(nameDebounceRef.current);
    nameDebounceRef.current = setTimeout(() => {
      setNameFilter(value);
      setPage(1);
    }, 400);
  };

  const handleParentChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    setParentFilter(e.target.value);
    setPage(1);
  };

  const clearFilters = () => {
    if (nameDebounceRef.current) clearTimeout(nameDebounceRef.current);
    setNameInput('');
    setNameFilter('');
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
        cell: (p) => (
          <span className="font-mono text-sm font-medium">{p.code}</span>
        ),
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
          <span className="text-sm text-text-secondary dark:text-text-dark-secondary">
            {p.alias}
          </span>
        ),
      },
      {
        id: 'color',
        header: 'Color',
        cell: (p) => <ColorBadge color={p.color} />,
      },
      {
        id: 'description',
        header: 'Descripción',
        cell: (p) =>
          p.description ? (
            <span
              className="text-sm text-text-muted dark:text-text-dark-muted truncate max-w-xs block"
              title={p.description}
            >
              {p.description}
            </span>
          ) : (
            <span className="text-text-muted dark:text-text-dark-muted">—</span>
          ),
      },
      {
        id: 'actions',
        header: '',
        cell: (p) => (
          <div className="flex justify-end gap-2">
            {mayUpdate && (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={(e) => {
                  e.stopPropagation();
                  setEditTarget(p);
                  setModalOpen(true);
                }}
              >
                Editar
              </Button>
            )}
            {mayDelete && (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={(e) => {
                  e.stopPropagation();
                  confirm({
                    title: 'Eliminar proceso',
                    description: `¿Eliminar "${p.name}" (${p.code})? Esta acción no se puede deshacer.`,
                    confirmLabel: 'Eliminar',
                    variant: 'danger',
                    onConfirm: () => handleDelete(p),
                  });
                }}
              >
                Eliminar
              </Button>
            )}
          </div>
        ),
      },
    ],
    [mayUpdate, mayDelete, confirm],
  );

  return (
    <div className="space-y-4">
      {actionError && (
        <div className="rounded-lg border border-danger/40 bg-danger-light/40 px-4 py-3 text-sm text-danger-dark flex justify-between gap-4">
          <span>{actionError}</span>
          <Button type="button" variant="ghost" size="xs" onClick={() => setActionError(null)} className="shrink-0">
            cerrar
          </Button>
        </div>
      )}

      {actionInfo && (
        <div className="rounded-lg border border-success/40 bg-success-light/40 px-4 py-3 text-sm text-success-dark flex justify-between gap-4">
          <span>{actionInfo}</span>
          <Button type="button" variant="ghost" size="xs" onClick={() => setActionInfo(null)} className="shrink-0">
            cerrar
          </Button>
        </div>
      )}

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
        filtersSlot={
          canCreate ? (
            <Button
              type="button"
              variant="primary"
              size="sm"
              onClick={() => {
                setEditTarget(null);
                setModalOpen(true);
              }}
            >
              + Nuevo Proceso
            </Button>
          ) : undefined
        }
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

      <ConfirmDialog {...confirmState} onCancel={closeConfirm} />

      <ProcessFormModal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        onSave={handleSave}
        initial={editTarget}
        processes={allProcesses}
      />
    </div>
  );
}
