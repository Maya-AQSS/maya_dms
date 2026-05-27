import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Button,
  ConfirmDialog,
  DataTable,
  Pagination,
  useConfirm,
  type ColumnDef,
} from '@ceedcv-maya/shared-ui-react';
import { useUserProfile } from '../../user-profile';
import { DMS_PERMISSIONS } from '../../../permissions';
import { useProcessesCrud } from '../hooks/useProcessesCrud';
import { ProcessFormModal } from './ProcessFormModal';
import type { Process } from '../../../types/processes';
import type { ProcessPayload } from '../hooks/useProcessesCrud';

interface ProcessesTableProps {
  canCreate: boolean;
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
  const { hasPermission } = useUserProfile();
  const { confirmState, confirm, closeConfirm } = useConfirm();

  const {
    items,
    meta,
    loading,
    listError,
    actionError,
    actionInfo,
    clearActionError,
    clearActionInfo,
    applyFilters,
    goToPage,
    createProcess,
    updateProcess,
    deleteProcess,
  } = useProcessesCrud();

  // All processes (unfiltered) for the modal's parent select
  const allProcesses = items;

  // Modal state
  const [modalOpen, setModalOpen] = useState(false);
  const [editTarget, setEditTarget] = useState<Process | null>(null);

  // Search debounce
  const [searchInput, setSearchInput] = useState('');
  const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const handleSearchChange = (value: string) => {
    setSearchInput(value);
    if (searchDebounceRef.current) clearTimeout(searchDebounceRef.current);
    searchDebounceRef.current = setTimeout(() => {
      applyFilters({ search: value.trim() || undefined });
    }, 300);
  };

  useEffect(() => {
    return () => {
      if (searchDebounceRef.current) clearTimeout(searchDebounceRef.current);
    };
  }, []);

  // Parent filter
  const [parentFilter, setParentFilter] = useState<string>('');
  const handleParentFilterChange = (value: string) => {
    setParentFilter(value);
    applyFilters({
      parent_id: value === '' ? undefined : value,
    });
  };

  // Top-level processes for the parent filter select
  const topLevelProcesses = items.filter((p) => p.process_parent_id === null);

  const openCreate = () => {
    setEditTarget(null);
    setModalOpen(true);
  };

  const openEdit = (process: Process) => {
    setEditTarget(process);
    setModalOpen(true);
  };

  const handleSave = async (payload: ProcessPayload) => {
    if (editTarget) {
      await updateProcess(editTarget.id, payload);
    } else {
      await createProcess(payload);
    }
  };

  const mayUpdate = hasPermission(DMS_PERMISSIONS.processUpdate);
  const mayDelete = hasPermission(DMS_PERMISSIONS.processDelete);

  const columns: ColumnDef<Process>[] = [
    {
      id: 'code',
      header: 'Código',
      cell: (process) => (
        <span className="font-mono text-sm font-medium text-text-primary dark:text-text-dark-primary">
          {process.code}
        </span>
      ),
    },
    {
      id: 'name',
      header: 'Nombre',
      cell: (process) => (
        <button
          type="button"
          onClick={() => navigate(`/admin/procesos/${process.id}`)}
          className="text-left font-medium text-odoo-purple hover:underline"
        >
          {process.name}
        </button>
      ),
    },
    {
      id: 'alias',
      header: 'Alias',
      cell: (process) => (
        <span className="text-sm text-text-secondary dark:text-text-dark-secondary">
          {process.alias}
        </span>
      ),
    },
    {
      id: 'color',
      header: 'Color',
      cell: (process) => <ColorBadge color={process.color} />,
    },
    {
      id: 'description',
      header: 'Descripción',
      cell: (process) =>
        process.description ? (
          <span
            className="text-sm text-text-muted dark:text-text-dark-muted truncate max-w-xs block"
            title={process.description}
          >
            {process.description}
          </span>
        ) : (
          <span className="text-text-muted dark:text-text-dark-muted">—</span>
        ),
    },
    {
      id: 'actions',
      header: '',
      cell: (process) => (
        <div className="flex justify-end gap-2">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => navigate(`/admin/procesos/${process.id}`)}
          >
            Ver
          </Button>
          {mayUpdate && (
            <Button type="button" variant="ghost" size="sm" onClick={() => openEdit(process)}>
              Editar
            </Button>
          )}
          {mayDelete && (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() =>
                confirm({
                  title: 'Eliminar proceso',
                  description: `¿Eliminar el proceso "${process.name}" (${process.code})? Esta acción no se puede deshacer.`,
                  confirmLabel: 'Eliminar',
                  variant: 'danger',
                  onConfirm: async () => {
                    await deleteProcess(process.id);
                  },
                })
              }
            >
              Eliminar
            </Button>
          )}
        </div>
      ),
    },
  ];

  return (
    <>
      {/* Filters bar */}
      <div className="mb-4 flex flex-wrap gap-3 items-center">
        <input
          type="search"
          value={searchInput}
          onChange={(e) => handleSearchChange(e.target.value)}
          placeholder="Buscar por código, nombre o alias…"
          className="border border-ui-border dark:border-ui-dark-border rounded-lg px-3 py-2 text-sm bg-ui-card dark:bg-ui-dark-card text-text-primary dark:text-text-dark-primary placeholder:text-text-muted dark:placeholder:text-text-dark-muted focus:outline-none focus:ring-2 focus:ring-odoo-purple/40 focus:border-odoo-purple transition-colors w-72"
        />

        <select
          value={parentFilter}
          onChange={(e) => handleParentFilterChange(e.target.value)}
          className="border border-ui-border dark:border-ui-dark-border rounded-lg px-3 py-2 text-sm bg-ui-card dark:bg-ui-dark-card text-text-primary dark:text-text-dark-primary focus:outline-none focus:ring-2 focus:ring-odoo-purple/40 focus:border-odoo-purple transition-colors"
        >
          <option value="">Todos los procesos</option>
          <option value="root">Solo raíz (sin padre)</option>
          {topLevelProcesses.map((p) => (
            <option key={p.id} value={p.id}>
              {p.code} — {p.name}
            </option>
          ))}
        </select>

        {canCreate && (
          <div className="ml-auto">
            <Button type="button" variant="primary" size="sm" onClick={openCreate}>
              + Nuevo Proceso
            </Button>
          </div>
        )}
      </div>

      {/* Error / info banners */}
      {listError && (
        <div className="my-3 rounded border border-danger bg-danger-light p-3 text-sm text-danger-dark">
          {listError}
        </div>
      )}

      {actionError && (
        <div className="my-3 rounded border border-danger bg-danger-light p-3 text-sm text-danger-dark">
          <span>{actionError}</span>
          <button type="button" onClick={clearActionError} className="ml-3 underline">
            cerrar
          </button>
        </div>
      )}

      {actionInfo && (
        <div className="my-3 rounded border border-success bg-success-light p-3 text-sm text-success-dark">
          <span>{actionInfo}</span>
          <button type="button" onClick={clearActionInfo} className="ml-3 underline">
            cerrar
          </button>
        </div>
      )}

      <DataTable<Process>
        columns={columns}
        rows={items}
        rowKey={(p) => p.id}
        loading={loading}
        emptyMessage="No se encontraron procesos."
      />

      {meta && meta.total > meta.per_page && (
        <Pagination
          currentPage={meta.current_page}
          totalPages={meta.last_page}
          onChange={goToPage}
        />
      )}

      <ConfirmDialog {...confirmState} onCancel={closeConfirm} />

      <ProcessFormModal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        onSave={handleSave}
        initial={editTarget}
        processes={allProcesses}
      />
    </>
  );
}
