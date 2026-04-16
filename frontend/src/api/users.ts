import type { UsersSearchResponse } from '../types/users';
import { apiGetJson } from './http';

export type { User } from '../types/users';

/** GET /api/v1/users?search={query}&per_page=20 */
export async function searchUsers(query: string): Promise<UsersSearchResponse> {
  const q = new URLSearchParams({ search: query, per_page: '20' });
  return apiGetJson<UsersSearchResponse>(`users?${q.toString()}`);
}
