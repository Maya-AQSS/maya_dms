import { useState } from 'react';
import { Button, FieldLabel, TextInput } from '../../../ui';
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
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => void refetch()}
          disabled={loading}
        >
          Actualizar
        </Button>
      </div>

      {listError && (
        <div className="rounded-lg border border-warning/40 bg-warning-light/40 dark:bg-warning-dark/10 px-4 py-3 text-sm text-warning-dark dark:text-warning-light">
          {listError}
        </div>
      )}

      {actionError && (
        <div className="rounded-lg border border-odoo-purple/30 bg-odoo-purple/5 px-4 py-3 text-sm text-text-primary dark:text-text-dark-primary dark:border-odoo-dark-purple/40 dark:bg-odoo-dark-purple/15 flex justify-between gap-4">
          <span>{actionError}</span>
          <Button type="button" variant="ghost" size="xs" onClick={clearActionError} className="shrink-0">
            Cerrar
          </Button>
        </div>
      )}

      <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card p-5">
        <h3 className="text-xs font-semibold uppercase tracking-wide text-text-secondary dark:text-text-dark-secondary mb-3">
          Nuevo grupo
        </h3>
        <div className="flex flex-col sm:flex-row flex-wrap gap-3 items-end">
          <div className="flex-1 min-w-[180px] w-full">
            <FieldLabel>Nombre</FieldLabel>
            <TextInput
              type="text"
              fieldSize="comfortable"
              value={newName}
              onChange={(e) => setNewName(e.target.value)}
              placeholder="Nombre del grupo"
            />
          </div>
          <div className="flex-[2] min-w-[220px] w-full">
            <FieldLabel>Descripción</FieldLabel>
            <TextInput
              type="text"
              fieldSize="comfortable"
              value={newDesc}
              onChange={(e) => setNewDesc(e.target.value)}
              placeholder="Opcional"
            />
          </div>
          <Button
            type="button"
            variant="primary"
            size="md"
            loading={creating}
            disabled={!newName.trim()}
            onClick={() => void handleCreate()}
          >
            {creating ? 'Creando…' : 'Crear grupo'}
          </Button>
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
