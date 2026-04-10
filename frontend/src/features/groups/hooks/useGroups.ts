import { useCallback, useEffect, useState } from 'react';
import { ApiHttpError } from '../../../api/http';
import {
  addGroupMembers,
  createGroup as createGroupRequest,
  deleteGroup as deleteGroupRequest,
  fetchGroups,
  removeGroupMember,
  updateGroup as updateGroupRequest,
} from '../../../api/groups';
import type { CreateGroupPayload, UpdateGroupPayload } from '../../../api/groups';
import type { Group, GroupsListMeta } from '../../../types/groups';

function formatActionError(err: unknown): string {
  if (err instanceof ApiHttpError) {
    if (err.status === 403) {
      return 'No tienes permiso para esta acción (se requiere rol de gestión de grupos).';
    }
    if (err.status === 401) {
      return 'Sesión no válida o token ausente.';
    }
    return err.message || `Error HTTP ${err.status}`;
  }
  return err instanceof Error ? err.message : 'Error desconocido';
}

/**
 * Estado y acciones sobre grupos: la capa api/ realiza HTTP; aquí solo orquestación y estado.
 */
export function useGroups(perPage = 50) {
  const [groups, setGroups] = useState<Group[]>([]);
  const [meta, setMeta] = useState<GroupsListMeta | null>(null);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      setListError(null);
      setLoading(true);
      const res = await fetchGroups(perPage);
      setGroups(res.data);
      setMeta(res.meta);
    } catch (e) {
      setListError(formatActionError(e));
      setGroups([]);
      setMeta(null);
    } finally {
      setLoading(false);
    }
  }, [perPage]);

  useEffect(() => {
    void load();
  }, [load]);

  const createGroup = useCallback(
    async (payload: CreateGroupPayload) => {
      try {
        setActionError(null);
        await createGroupRequest(payload);
        await load();
      } catch (e) {
        setActionError(formatActionError(e));
        throw e;
      }
    },
    [load],
  );

  const updateGroup = useCallback(
    async (id: string, payload: UpdateGroupPayload) => {
      try {
        setActionError(null);
        await updateGroupRequest(id, payload);
        await load();
      } catch (e) {
        setActionError(formatActionError(e));
        throw e;
      }
    },
    [load],
  );

  const deleteGroup = useCallback(
    async (id: string) => {
      try {
        setActionError(null);
        await deleteGroupRequest(id);
        await load();
      } catch (e) {
        setActionError(formatActionError(e));
        throw e;
      }
    },
    [load],
  );

  const addMember = useCallback(
    async (groupId: string, userId: string) => {
      try {
        setActionError(null);
        await addGroupMembers(groupId, { user_id: userId });
        await load();
      } catch (e) {
        setActionError(formatActionError(e));
        throw e;
      }
    },
    [load],
  );

  const removeMember = useCallback(
    async (groupId: string, userId: string) => {
      try {
        setActionError(null);
        await removeGroupMember(groupId, userId);
        await load();
      } catch (e) {
        setActionError(formatActionError(e));
        throw e;
      }
    },
    [load],
  );

  return {
    groups,
    meta,
    loading,
    listError,
    actionError,
    clearActionError: () => setActionError(null),
    refetch: load,
    createGroup,
    updateGroup,
    deleteGroup,
    addMember,
    removeMember,
  };
}
