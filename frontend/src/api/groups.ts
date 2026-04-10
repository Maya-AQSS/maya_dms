import type { Group, GroupsListResponse } from '../types/groups';
import { apiFetchJson, apiGetJson } from './http';

export type { Group, GroupMember, GroupsListResponse } from '../types/groups';

export type CreateGroupPayload = {
  name: string;
  description?: string | null;
};

export type UpdateGroupPayload = {
  name?: string;
  description?: string | null;
};

/** GET /api/v1/groups */
export async function fetchGroups(perPage = 15): Promise<GroupsListResponse> {
  const q = new URLSearchParams({ per_page: String(perPage) });
  return apiGetJson<GroupsListResponse>(`groups?${q.toString()}`);
}

/** GET /api/v1/groups/{id} */
export async function fetchGroup(id: string): Promise<{ data: Group }> {
  return apiGetJson<{ data: Group }>(`groups/${id}`);
}

/** POST /api/v1/groups */
export async function createGroup(payload: CreateGroupPayload): Promise<{ data: Group }> {
  return apiFetchJson<{ data: Group }>('groups', { method: 'POST', body: payload });
}

/** PUT /api/v1/groups/{id} */
export async function updateGroup(id: string, payload: UpdateGroupPayload): Promise<{ data: Group }> {
  return apiFetchJson<{ data: Group }>(`groups/${id}`, { method: 'PUT', body: payload });
}

/** DELETE /api/v1/groups/{id} */
export async function deleteGroup(id: string): Promise<void> {
  await apiFetchJson<void>(`groups/${id}`, { method: 'DELETE' });
}

/** POST /api/v1/groups/{id}/members */
export async function addGroupMembers(
  groupId: string,
  body: { user_id?: string; user_ids?: string[]; role?: 'member' | 'admin' },
): Promise<{ message: string }> {
  return apiFetchJson<{ message: string }>(`groups/${groupId}/members`, { method: 'POST', body });
}

/** DELETE /api/v1/groups/{id}/members/{userId} */
export async function removeGroupMember(groupId: string, userId: string): Promise<void> {
  await apiFetchJson<void>(`groups/${groupId}/members/${encodeURIComponent(userId)}`, {
    method: 'DELETE',
  });
}
