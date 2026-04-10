import { useState } from 'react';
import type { Group } from '../../../types/groups';
import { useGroups } from '../hooks/useGroups';

function MemberBadge({ userId, role }: { userId: string; role: string }) {
  return (
    <span className="inline-flex items-center gap-1 rounded border border-ui-border dark:border-ui-dark-border px-2 py-0.5 text-xs text-text-secondary dark:text-text-dark-secondary">
      <span className="font-mono truncate max-w-[140px]" title={userId}>
        {userId.slice(0, 8)}…
      </span>
      <span className="text-text-muted dark:text-text-dark-muted">({role})</span>
    </span>
  );
}

function GroupCard({
  group,
  onUpdate,
  onDelete,
  onAddMember,
  onRemoveMember,
}: {
  group: Group;
  onUpdate: (id: string, name: string, description: string | null) => Promise<void>;
  onDelete: (id: string) => Promise<void>;
  onAddMember: (groupId: string, userId: string) => Promise<void>;
  onRemoveMember: (groupId: string, userId: string) => Promise<void>;
}) {
  const [editing, setEditing] = useState(false);
  const [name, setName] = useState(group.name);
  const [description, setDescription] = useState(group.description ?? '');
  const [memberUserId, setMemberUserId] = useState('');
  const [busy, setBusy] = useState(false);

  const members = group.members ?? [];

  const handleSave = async () => {
    setBusy(true);
    try {
      await onUpdate(group.id, name, description === '' ? null : description);
      setEditing(false);
    } finally {
      setBusy(false);
    }
  };

  const handleDelete = async () => {
    if (!window.confirm(`¿Eliminar el grupo «${group.name}»? Se desvincularán los miembros.`)) {
      return;
    }
    setBusy(true);
    try {
      await onDelete(group.id);
    } finally {
      setBusy(false);
    }
  };

  const handleAddMember = async () => {
    const uid = memberUserId.trim();
    if (!uid) return;
    setBusy(true);
    try {
      await onAddMember(group.id, uid);
      setMemberUserId('');
    } finally {
      setBusy(false);
    }
  };

  const handleRemoveMember = async (userId: string) => {
    if (!window.confirm('¿Quitar este usuario del grupo?')) return;
    setBusy(true);
    try {
      await onRemoveMember(group.id, userId);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="rounded-lg border border-ui-border dark:border-ui-dark-border bg-ui-card dark:bg-ui-dark-card p-4 shadow-card">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0 flex-1">
          {editing ? (
            <div className="space-y-2 max-w-lg">
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-3 py-1.5 text-sm text-text-primary dark:text-text-dark-primary"
                placeholder="Nombre"
              />
              <textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                rows={2}
                className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-3 py-1.5 text-sm text-text-primary dark:text-text-dark-primary"
                placeholder="Descripción (opcional)"
              />
              <div className="flex gap-2">
                <button
                  type="button"
                  disabled={busy || !name.trim()}
                  onClick={() => void handleSave()}
                  className="rounded bg-odoo-purple px-3 py-1 text-xs font-medium text-white hover:opacity-90 disabled:opacity-50"
                >
                  Guardar
                </button>
                <button
                  type="button"
                  disabled={busy}
                  onClick={() => {
                    setEditing(false);
                    setName(group.name);
                    setDescription(group.description ?? '');
                  }}
                  className="rounded border border-ui-border px-3 py-1 text-xs text-text-secondary dark:text-text-dark-secondary"
                >
                  Cancelar
                </button>
              </div>
            </div>
          ) : (
            <>
              <h3 className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
                {group.name}
              </h3>
              {group.description ? (
                <p className="mt-1 text-xs text-text-secondary dark:text-text-dark-secondary">
                  {group.description}
                </p>
              ) : null}
              <p className="mt-2 text-xs text-text-muted dark:text-text-dark-muted">
                Propietario: <span className="font-mono">{group.owner_id}</span>
              </p>
            </>
          )}
        </div>
        {!editing && (
          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              disabled={busy}
              onClick={() => setEditing(true)}
              className="rounded border border-ui-border dark:border-ui-dark-border px-2 py-1 text-xs text-text-secondary dark:text-text-dark-secondary hover:bg-ui-body dark:hover:bg-ui-dark-bg"
            >
              Editar
            </button>
            <button
              type="button"
              disabled={busy}
              onClick={() => void handleDelete()}
              className="rounded border border-warning/40 px-2 py-1 text-xs text-warning-dark hover:bg-warning-light/30"
            >
              Eliminar
            </button>
          </div>
        )}
      </div>

      <div className="mt-4 border-t border-ui-border-l dark:border-ui-dark-border-l pt-3">
        <p className="text-xs font-medium uppercase tracking-wide text-text-secondary dark:text-text-dark-secondary mb-2">
          Miembros ({members.length})
        </p>
        <div className="flex flex-wrap gap-2 mb-3">
          {members.length === 0 ? (
            <span className="text-xs text-text-muted dark:text-text-dark-muted">Sin miembros además del propietario.</span>
          ) : (
            members.map((m) => (
              <span key={m.id} className="inline-flex items-center gap-1">
                <MemberBadge userId={m.user_id} role={m.role} />
                <button
                  type="button"
                  disabled={busy}
                  onClick={() => void handleRemoveMember(m.user_id)}
                  className="text-xs text-text-link dark:text-text-dark-link hover:underline disabled:opacity-40"
                  title="Quitar miembro"
                >
                  ✕
                </button>
              </span>
            ))
          )}
        </div>
        <div className="flex flex-wrap items-end gap-2">
          <div className="flex-1 min-w-[200px]">
            <label className="block text-xs text-text-muted dark:text-text-dark-muted mb-1">
              Añadir usuario (UUID)
            </label>
            <input
              type="text"
              value={memberUserId}
              onChange={(e) => setMemberUserId(e.target.value)}
              placeholder="user_id"
              className="w-full rounded border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-3 py-1.5 text-xs font-mono text-text-primary dark:text-text-dark-primary"
            />
          </div>
          <button
            type="button"
            disabled={busy || !memberUserId.trim()}
            onClick={() => void handleAddMember()}
            className="rounded bg-odoo-teal/90 px-3 py-1.5 text-xs font-medium text-white hover:opacity-90 disabled:opacity-50"
          >
            Añadir miembro
          </button>
        </div>
      </div>
    </div>
  );
}

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
