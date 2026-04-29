import type { MeProfileResponse, UsersSearchResponse } from'../types/users';
import { apiGetJson } from'./http';

export type { MeProfile, User, UserTeam } from'../types/users';

/** GET /api/v1/users?search={query}&per_page=20 */
export async function searchUsers(query: string, excludeUserId?: string): Promise<UsersSearchResponse> {
 const q = new URLSearchParams({ search: query, per_page:'20' });
 if (excludeUserId) {
 q.set('exclude_user_id', excludeUserId);
 }
 return apiGetJson<UsersSearchResponse>(`users?${q.toString()}`);
}

/** GET /api/v1/users/reviewer-candidates?search={query?}&per_page=50 */
export async function searchTemplateReviewerCandidates(query ='',
 excludeUserId?: string,
): Promise<UsersSearchResponse> {
 const q = new URLSearchParams({ per_page:'50' });
 const trimmed = query.trim();
 if (trimmed.length > 0) {
 q.set('search', trimmed);
 }
 if (excludeUserId) {
 q.set('exclude_user_id', excludeUserId);
 }
 return apiGetJson<UsersSearchResponse>(`users/reviewer-candidates?${q.toString()}`);
}

/** GET /api/v1/users/document-reviewer-candidates?search={query?}&per_page=50 */
export async function searchDocumentReviewerCandidates(query ='',
 excludeUserId?: string,
): Promise<UsersSearchResponse> {
 const q = new URLSearchParams({ per_page:'50' });
 const trimmed = query.trim();
 if (trimmed.length > 0) {
 q.set('search', trimmed);
 }
 if (excludeUserId) {
 q.set('exclude_user_id', excludeUserId);
 }
 return apiGetJson<UsersSearchResponse>(`users/document-reviewer-candidates?${q.toString()}`);
}

/** GET /api/v1/me */
export async function fetchMe(): Promise<MeProfileResponse> {
 return apiGetJson<MeProfileResponse>('me');
}
