import { useMemo, useState } from 'react';
import { Button, ConfirmDialog } from '../../../ui';
import type { Group } from '../../../types/groups';
import { FieldLabel, TextArea, TextInput } from '../../../ui';

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

function groupEditIsDirty(group: Group, name: string, description: string): boolean {
  const draftDesc = description.trim() === '' ? '' : description.trim();
  const storedDesc = (group.description ?? '').trim();
  return name.trim() !== group.name || draftDesc !== storedDesc;
}

type ConfirmKind = 'idle' | 'delete' | 'discard' | 'removeMember';

type Props = {
  group: Group;
  onUpdate: (id: string, name: string, description: string | null) => Promise<void>;
  onDelete: (id: string) => Promise<void>;
  onAddMember: (groupId: string, userId: string) => Promise<void>;
  onRemoveMember: (groupId: string, userId: string) => Promise<void>;
};

export function GroupCard({ group, onUpdate, onDelete, onAddMember, onRemoveMember }: Props) {
  const [editing, setEditing] = useState(false);
  const [name, setName] = useState(group.name);
  const [description, setDescription] = useState(group.description ?? '');
  const [memberUserId, setMemberUserId] = useState('');
  const [busy, setBusy] = useState(false);
  const [confirmKind, setConfirmKind] = useState<ConfirmKind>('idle');
  const [removeMemberUserId, setRemoveMemberUserId] = useState<string | null>(null);
  const [dialogLoading, setDialogLoading] = useState(false);

  const members = group.members ?? [];

  const editDirty = useMemo(
    () => groupEditIsDirty(group, name, description),
    [group, name, description],
  );

  const resetFromProps = () => {
    setName(group.name);
    setDescription(group.description ?? '');
  };

  const closeDialog = () => {
    if (dialogLoading) return;
    setConfirmKind('idle');
    setRemoveMemberUserId(null);
  };

  const handleSave = async () => {
    setBusy(true);
    try {
      await onUpdate(group.id, name, description === '' ? null : description);
      setEditing(false);
    } finally {
      setBusy(false);
    }
  };

  const confirmDelete = async () => {
    setDialogLoading(true);
    try {
      await onDelete(group.id);
    } catch {
      /* useGroups ya expone actionError */
    } finally {
      setDialogLoading(false);
      setConfirmKind('idle');
      setRemoveMemberUserId(null);
    }
  };

  const confirmRemoveMember = async () => {
    const uid = removeMemberUserId;
    if (!uid) return;
    setDialogLoading(true);
    try {
      await onRemoveMember(group.id, uid);
    } catch {
      /* useGroups */
    } finally {
      setDialogLoading(false);
      setConfirmKind('idle');
      setRemoveMemberUserId(null);
    }
  };

  const confirmDiscard = () => {
    resetFromProps();
    setEditing(false);
    setConfirmKind('idle');
    setRemoveMemberUserId(null);
  };

  const dialogOpen = confirmKind !== 'idle';

  return (
    <div className="rounded-lg border border-ui-border dark:border-ui-dark-border bg-ui-card dark:bg-ui-dark-card p-5 shadow-card">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0 flex-1">
          {editing ? (
            <div className="space-y-2 max-w-lg">
              <TextInput
                type="text"
                fieldSize="md"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Nombre"
              />
              <TextArea
                fieldSize="md"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                rows={2}
                placeholder="Descripción (opcional)"
              />
              <div className="flex gap-2">
                <Button
                  type="button"
                  variant="primary"
                  size="sm"
                  disabled={busy || !name.trim()}
                  onClick={() => void handleSave()}
                >
                  Guardar
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  disabled={busy}
                  onClick={() => {
                    if (!editDirty) {
                      setEditing(false);
                      resetFromProps();
                      return;
                    }
                    setConfirmKind('discard');
                  }}
                >
                  Cancelar
                </Button>
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
            <Button
              type="button"
              variant="outline"
              size="xs"
              disabled={busy || dialogOpen}
              onClick={() => setEditing(true)}
            >
              Editar
            </Button>
            <Button
              type="button"
              variant="outlineWarning"
              size="xs"
              disabled={busy || dialogOpen}
              onClick={() => setConfirmKind('delete')}
            >
              Eliminar
            </Button>
          </div>
        )}
      </div>

      <div className="mt-4 border-t border-ui-border-l dark:border-ui-dark-border-l pt-3">
        <p className="text-xs font-medium uppercase tracking-wide text-text-secondary dark:text-text-dark-secondary mb-2">
          Miembros ({members.length})
        </p>
        <div className="flex flex-wrap gap-2 mb-3">
          {members.length === 0 ? (
            <span className="text-xs text-text-muted dark:text-text-dark-muted">
              Sin miembros además del propietario.
            </span>
          ) : (
            members.map((m) => (
              <span key={m.id} className="inline-flex items-center gap-1">
                <MemberBadge userId={m.user_id} role={m.role} />
                <Button
                  type="button"
                  variant="ghost"
                  size="xs"
                  disabled={busy || dialogOpen}
                  onClick={() => {
                    setRemoveMemberUserId(m.user_id);
                    setConfirmKind('removeMember');
                  }}
                  className="min-w-0"
                  title="Quitar miembro"
                >
                  ✕
                </Button>
              </span>
            ))
          )}
        </div>
        <div className="flex flex-wrap items-end gap-2">
          <div className="flex-1 min-w-[200px]">
            <FieldLabel>Añadir usuario (UUID)</FieldLabel>
            <TextInput
              type="text"
              fieldSize="mono"
              value={memberUserId}
              onChange={(e) => setMemberUserId(e.target.value)}
              placeholder="user_id"
            />
          </div>
          <Button
            type="button"
            variant="teal"
            size="sm"
            disabled={busy || !memberUserId.trim() || dialogOpen}
            className="opacity-90 hover:opacity-100"
            onClick={async () => {
              const uid = memberUserId.trim();
              if (!uid) return;
              setBusy(true);
              try {
                await onAddMember(group.id, uid);
                setMemberUserId('');
              } finally {
                setBusy(false);
              }
            }}
          >
            Añadir miembro
          </Button>
        </div>
      </div>

      <ConfirmDialog
        open={confirmKind === 'delete'}
        title="¿Eliminar grupo?"
        description={
          <>
            Se eliminará el grupo «<strong className="font-medium">{group.name}</strong>» y se desvincularán los
            miembros.
          </>
        }
        confirmLabel="Eliminar"
        variant="danger"
        loading={dialogLoading}
        onCancel={closeDialog}
        onConfirm={confirmDelete}
      />
      <ConfirmDialog
        open={confirmKind === 'removeMember'}
        title="¿Quitar miembro?"
        description={
          removeMemberUserId ? (
            <>
              El usuario <span className="font-mono text-xs">{removeMemberUserId}</span> dejará de pertenecer a este
              grupo.
            </>
          ) : null
        }
        confirmLabel="Quitar"
        cancelLabel="Cancelar"
        variant="danger"
        loading={dialogLoading}
        onCancel={closeDialog}
        onConfirm={confirmRemoveMember}
      />
      <ConfirmDialog
        open={confirmKind === 'discard'}
        title="¿Descartar cambios?"
        description="Los cambios que no hayas guardado se perderán."
        confirmLabel="Descartar"
        cancelLabel="Seguir editando"
        variant="danger"
        onCancel={closeDialog}
        onConfirm={confirmDiscard}
      />
    </div>
  );
}
