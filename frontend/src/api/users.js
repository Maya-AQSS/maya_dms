import { apiGetJson } from './http';
/** GET /api/v1/users?search={query}&per_page=20 */
export async function searchUsers(query, excludeUserId) {
    const q = new URLSearchParams({ search: query, per_page: '20' });
    if (excludeUserId) {
        q.set('exclude_user_id', excludeUserId);
    }
    return apiGetJson(`users?${q.toString()}`);
}
/** GET /api/v1/users/reviewer-candidates?search={query?}&per_page=50 */
export async function searchTemplateReviewerCandidates(query = '', excludeUserId) {
    const q = new URLSearchParams({ per_page: '50' });
    const trimmed = query.trim();
    if (trimmed.length > 0) {
        q.set('search', trimmed);
    }
    if (excludeUserId) {
        q.set('exclude_user_id', excludeUserId);
    }
    return apiGetJson(`users/reviewer-candidates?${q.toString()}`);
}
/** GET /api/v1/users/document-reviewer-candidates?search={query?}&per_page=50 */
export async function searchDocumentReviewerCandidates(query = '', excludeUserId) {
    const q = new URLSearchParams({ per_page: '50' });
    const trimmed = query.trim();
    if (trimmed.length > 0) {
        q.set('search', trimmed);
    }
    if (excludeUserId) {
        q.set('exclude_user_id', excludeUserId);
    }
    return apiGetJson(`users/document-reviewer-candidates?${q.toString()}`);
}
/** GET /api/v1/me */
export async function fetchMe() {
    return apiGetJson('me');
}
