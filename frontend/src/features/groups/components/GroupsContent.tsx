import { useState } from 'react';
import { useGroups } from '../hooks/useGroups';
import { GroupCard } from './GroupCard';

/**
 * Vista de gestión de grupos: datos vía {@link useGroups}; sin llamadas HTTP directas.
 */
export function GroupsContent() {
  const {
    groups,
    meta,
    loading,
    listError,
    actionError,
    clearActionError,
    refetch,
    createGroup,
    updateGroup,
    deleteGroup,
    addMember,
    removeMember,
  } = useGroups(50);

  const [newName, setNewName] = useState('');
  const [newDesc, setNewDesc] = useState('');
  const [creating, setCreating] = useState(false);

  const handleCreate = async () => {
    if (!newName.trim()) return;
    setCreating(true);
    try {
      await createGroup({
        name: newName.trim(),
        description: newDesc.trim() === '' ? null : newDesc.trim(),
      });
      setNewName('');
      setNewDesc('');
    } finally {
      setCreating(false);
    }
  };

  return (
    <div className="p-6 space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
            Grupos internos
          </h2>
          <p className="text-xs text-text-muted dark:text-text-dark-muted mt-1">
            Listado según tu visibilidad en la API. Crear, editar y miembros dependen de los permisos de tu cuenta.
          </p>
        </div>
        <button
          type="button"
          onClick={() => void refetch()}
          disabled={loading}
          className="rounded border border-ui-border dark:border-ui-dark-border px-3 py-1.5 text-xs text-text-secondary dark:text-text-dark-secondary hover:bg-ui-body dark:hover:bg-ui-dark-bg disabled:opacity-50"
        >
          Actualizar
        </button>
      </div>

      {listError && (
        <div className="rounded-lg border border-warning/40 bg-warning-light/40 dark:bg-warning-dark/10 px-4 py-3 text-sm text-warning-dark dark:text-warning-light">
          {listError}
        </div>
      )}

      {actionError && (
        <div className="rounded-lg border border-odoo-purple/30 bg-odoo-purple/5 px-4 py-3 text-sm text-text-primary dark:text-text-dark-primary flex justify-between gap-4">
          <span>{actionError}</span>
          <button type="button" onClick={clearActionError} className="text-xs text-text-link shrink-0">
            Cerrar
          </button>
        </div>
      )}

      <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card p-4">
        <h3 className="text-xs font-semibold uppercase tracking-wide text-text-secondary dark:text-text-dark-secondary mb-3">
          Nuevo grupo
        </h3>
        <div className="flex flex-col sm:flex-row flex-wrap gap-3 items-end">
          <div className="flex-1 min-w-[180px] w-full">
            <label className="block text-xs text-text-muted mb-1">Nombre</label>
            <input
              type="text"
              value={newName}
              onChange={(e) => setNewName(e.target.value)}
              className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-3 py-2 text-sm"
              placeholder="Nombre del grupo"
            />
          </div>
          <div className="flex-[2] min-w-[220px] w-full">
            <label className="block text-xs text-text-muted mb-1">Descripción</label>
            <input
              type="text"
              value={newDesc}
              onChange={(e) => setNewDesc(e.target.value)}
              className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-3 py-2 text-sm"
              placeholder="Opcional"
            />
          </div>
          <button
            type="button"
            disabled={creating || !newName.trim()}
            onClick={() => void handleCreate()}
            className="rounded bg-odoo-purple px-4 py-2 text-sm font-medium text-white hover:opacity-90 disabled:opacity-50"
          >
            Crear grupo
          </button>
        </div>
      </div>

      {loading && groups.length === 0 ? (
        <p className="text-sm text-text-muted dark:text-text-dark-muted">Cargando grupos…</p>
      ) : null}

      {!loading && groups.length === 0 && !listError ? (
        <p className="text-sm text-text-muted dark:text-text-dark-muted text-center py-8">
          No hay grupos visibles para tu usuario.
        </p>
      ) : null}

      {meta ? (
        <p className="text-xs text-text-muted dark:text-text-dark-muted">
          Página {meta.current_page} de {meta.last_page} — {meta.total} grupos
        </p>
      ) : null}

      <div className="space-y-4">
        {groups.map((g) => (
          <GroupCard
            key={g.id}
            group={g}
            onUpdate={async (id, n, d) => {
              await updateGroup(id, { name: n, description: d });
            }}
            onDelete={deleteGroup}
            onAddMember={addMember}
            onRemoveMember={removeMember}
          />
        ))}
      </div>
    </div>
  );
}
