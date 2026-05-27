import { useCallback, useEffect, useState } from 'react';
import { useNavigate, useParams, Link } from 'react-router-dom';
import { Button, PageTitle } from '@ceedcv-maya/shared-ui-react';
import { useUserProfile } from '../../user-profile';
import { useProcessesQuery } from '../../../hooks/useProcesses';
import { DMS_PERMISSIONS } from '../../../permissions';
import { fetchProcess, deleteProcess, updateProcess } from '../../../api/processes';
import { ProcessFormModal } from '../components/ProcessFormModal';
import type { Process } from '../../../types/processes';
import type { ProcessPayload } from '../hooks/useProcessesCrud';
import { ApiHttpError } from '../../../api/http';

function formatError(err: unknown): string {
  if (err instanceof ApiHttpError) {
    if (err.status === 404) return 'Proceso no encontrado.';
    if (err.status === 403) return 'Sin permiso para acceder a este proceso.';
    return err.message || `Error HTTP ${err.status}`;
  }
  return err instanceof Error ? err.message : 'Error desconocido';
}

function useProcess(id: string | undefined) {
  const [process, setProcess] = useState<Process | null>(null);
  const [loading, setLoading] = useState(Boolean(id));
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!id) {
      setProcess(null);
      setLoading(false);
      return;
    }
    try {
      setError(null);
      setLoading(true);
      const res = await fetchProcess(id);
      setProcess(res.data);
    } catch (e) {
      setError(formatError(e));
      setProcess(null);
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  return { process, loading, error, refetch: load };
}

function ColorBadge({ color }: { color: string | null }) {
  if (!color) return <span className="text-text-muted dark:text-text-dark-muted">Sin color</span>;
  return (
    <div className="flex items-center gap-2">
      <span
        className="inline-block h-5 w-5 rounded-full border border-ui-border shadow-sm"
        style={{ backgroundColor: color }}
        title={color}
      />
      <span className="font-mono text-sm text-text-secondary dark:text-text-dark-secondary">
        {color}
      </span>
    </div>
  );
}

interface DetailRowProps {
  label: string;
  children: React.ReactNode;
}

function DetailRow({ label, children }: DetailRowProps) {
  return (
    <div className="grid grid-cols-3 gap-4 py-3 border-b border-ui-border dark:border-ui-dark-border last:border-0">
      <dt className="text-sm font-medium text-text-muted dark:text-text-dark-muted">{label}</dt>
      <dd className="col-span-2 text-sm text-text-primary dark:text-text-dark-primary">{children}</dd>
    </div>
  );
}

export function ProcessShowPage() {
  const { processId } = useParams<{ processId: string }>();
  const navigate = useNavigate();
  const { hasPermission } = useUserProfile();

  const { process, loading, error, refetch } = useProcess(processId);
  const processesQuery = useProcessesQuery();
  const allProcesses = processesQuery.data?.data ?? [];

  const [editOpen, setEditOpen] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionInfo, setActionInfo] = useState<string | null>(null);
  const [deleting, setDeleting] = useState(false);

  const mayUpdate = hasPermission(DMS_PERMISSIONS.processUpdate);
  const mayDelete = hasPermission(DMS_PERMISSIONS.processDelete);

  const handleSave = async (payload: ProcessPayload) => {
    if (!process) return;
    try {
      setActionError(null);
      await updateProcess(process.id, payload);
      setActionInfo('Cambios guardados.');
      setEditOpen(false);
      await refetch();
    } catch (e) {
      setActionError(formatError(e));
      throw e;
    }
  };

  const handleDelete = async () => {
    if (!process) return;
    const confirmed = window.confirm(
      `¿Eliminar el proceso "${process.name}" (${process.code})? Esta acción no se puede deshacer.`,
    );
    if (!confirmed) return;
    try {
      setDeleting(true);
      setActionError(null);
      await deleteProcess(process.id);
      navigate('/admin/procesos');
    } catch (e) {
      setActionError(formatError(e));
      setDeleting(false);
    }
  };

  if (loading) {
    return <p className="p-4 text-sm text-text-muted dark:text-text-dark-muted">Cargando proceso…</p>;
  }

  if (error || !process) {
    return (
      <div className="m-4 rounded border border-danger bg-danger-light p-3 text-sm text-danger-dark">
        {error ?? 'No se ha podido cargar el proceso.'}
      </div>
    );
  }

  return (
    <>
      <PageTitle
        title={process.name}
        subtitle={`Código: ${process.code}`}
        actions={
          <div className="flex gap-2">
            {mayUpdate && (
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setEditOpen(true)}
              >
                Editar
              </Button>
            )}
            {mayDelete && (
              <Button
                type="button"
                variant="danger"
                size="sm"
                onClick={() => void handleDelete()}
                disabled={deleting}
              >
                {deleting ? 'Eliminando…' : 'Eliminar'}
              </Button>
            )}
          </div>
        }
      />

      {actionError && (
        <div className="my-3 rounded border border-danger bg-danger-light p-3 text-sm text-danger-dark">
          <span>{actionError}</span>
          <button
            type="button"
            onClick={() => setActionError(null)}
            className="ml-3 underline"
          >
            cerrar
          </button>
        </div>
      )}

      {actionInfo && (
        <div className="my-3 rounded border border-success bg-success-light p-3 text-sm text-success-dark">
          <span>{actionInfo}</span>
          <button
            type="button"
            onClick={() => setActionInfo(null)}
            className="ml-3 underline"
          >
            cerrar
          </button>
        </div>
      )}

      {/* Detail card */}
      <div className="mt-4 rounded-xl border border-ui-border dark:border-ui-dark-border bg-ui-card dark:bg-ui-dark-card shadow-sm overflow-hidden">
        <dl>
          <DetailRow label="Código">{process.code}</DetailRow>
          <DetailRow label="Nombre">{process.name}</DetailRow>
          <DetailRow label="Alias">{process.alias}</DetailRow>
          <DetailRow label="Color">
            <ColorBadge color={process.color} />
          </DetailRow>
          <DetailRow label="Descripción">
            {process.description ? (
              <span className="whitespace-pre-wrap">{process.description}</span>
            ) : (
              <span className="text-text-muted dark:text-text-dark-muted italic">Sin descripción</span>
            )}
          </DetailRow>
          <DetailRow label="Proceso padre">
            {process.process_parent_id ? (
              <Link
                to={`/admin/procesos/${process.process_parent_id}`}
                className="text-odoo-purple hover:underline"
              >
                Ver proceso padre
              </Link>
            ) : (
              <span className="text-text-muted dark:text-text-dark-muted">Sin padre (proceso raíz)</span>
            )}
          </DetailRow>
        </dl>
      </div>

      {/* Back link */}
      <div className="mt-6">
        <Link
          to="/admin/procesos"
          className="text-sm text-odoo-purple hover:underline"
        >
          ← Volver a Procesos
        </Link>
      </div>

      {/* Edit modal */}
      <ProcessFormModal
        open={editOpen}
        onClose={() => setEditOpen(false)}
        onSave={handleSave}
        initial={process}
        processes={allProcesses}
      />
    </>
  );
}
