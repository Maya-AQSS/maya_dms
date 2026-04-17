import type { MeProfileResponse, UsersSearchResponse } from '../types/users';
import { apiGetJson } from './http';

export type { MeProfile, User, UserTeam } from '../types/users';

/** GET /api/v1/users?search={query}&per_page=20 */
export async function searchUsers(query: string): Promise<UsersSearchResponse> {
  const q = new URLSearchParams({ search: query, per_page: '20' });
  return apiGetJson<UsersSearchResponse>(`users?${q.toString()}`);
}

/** GET /api/v1/me */
export async function fetchMe(): Promise<MeProfileResponse> {
  return apiGetJson<MeProfileResponse>('me');
}
