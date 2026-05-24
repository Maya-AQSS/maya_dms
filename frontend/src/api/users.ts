import { createProfileApi } from '@maya/shared-profile-react';
import type { MeProfile, UsersSearchResponse } from '../types/users';
import { apiFetchJson, apiGetJson } from './http';

export type { MeProfile, User, UserTeam } from '../types/users';

/** Contexto académico para acotar candidatos a validador según visibilidad de plantilla. */
export type ReviewerCandidateAcademicContext = {
  visibility_level?: string;
  study_type_id?: string;
  study_id?: string;
  module_id?: string;
  team_id?: string;
};

const profileApi = createProfileApi<MeProfile>({ apiFetchJson, apiGetJson });

/** GET /api/v1/users?search={query}&per_page=20 */
export async function searchUsers(query: string, excludeUserId?: string): Promise<UsersSearchResponse> {
  const q = new URLSearchParams({ search: query, per_page: '20' });
  if (excludeUserId) {
    q.set('exclude_user_id', excludeUserId);
  }
  return apiGetJson<UsersSearchResponse>(`users?${q.toString()}`);
}

/** GET /api/v1/users/reviewer-candidates?search={query?}&per_page=50 */
export async function searchTemplateReviewerCandidates(
  query = '',
  excludeUserId?: string,
  academicContext?: ReviewerCandidateAcademicContext,
): Promise<UsersSearchResponse> {
  const q = new URLSearchParams({ per_page: '50' });
  const trimmed = query.trim();
  if (trimmed.length > 0) {
    q.set('search', trimmed);
  }
  if (excludeUserId) {
    q.set('exclude_user_id', excludeUserId);
  }
  appendReviewerAcademicContext(q, academicContext);
  return apiGetJson<UsersSearchResponse>(`users/reviewer-candidates?${q.toString()}`);
}

/** GET /api/v1/users/document-reviewer-candidates?search={query?}&per_page=50 */
export async function searchDocumentReviewerCandidates(
  query = '',
  excludeUserId?: string,
  academicContext?: ReviewerCandidateAcademicContext,
): Promise<UsersSearchResponse> {
  const q = new URLSearchParams({ per_page: '50' });
  const trimmed = query.trim();
  if (trimmed.length > 0) {
    q.set('search', trimmed);
  }
  if (excludeUserId) {
    q.set('exclude_user_id', excludeUserId);
  }
  appendReviewerAcademicContext(q, academicContext);
  return apiGetJson<UsersSearchResponse>(`users/document-reviewer-candidates?${q.toString()}`);
}

function appendReviewerAcademicContext(
  params: URLSearchParams,
  academicContext?: ReviewerCandidateAcademicContext,
): void {
  if (!academicContext?.visibility_level) {
    return;
  }
  params.set('visibility_level', academicContext.visibility_level);
  if (academicContext.study_type_id) {
    params.set('study_type_id', academicContext.study_type_id);
  }
  if (academicContext.study_id) {
    params.set('study_id', academicContext.study_id);
  }
  if (academicContext.module_id) {
    params.set('module_id', academicContext.module_id);
  }
  if (academicContext.team_id) {
    params.set('team_id', academicContext.team_id);
  }
}

/** GET /api/v1/users/owner-candidates?search={query}&per_page=20 */
export async function searchOwnerCandidates(query: string, excludeUserId?: string): Promise<UsersSearchResponse> {
  const q = new URLSearchParams({ search: query, per_page: '20' });
  if (excludeUserId) {
    q.set('exclude_user_id', excludeUserId);
  }
  return apiGetJson<UsersSearchResponse>(`users/owner-candidates?${q.toString()}`);
}

/**
 * GET /api/v1/me — devuelve el perfil envuelto en `{ data }` por compatibilidad
 * con los consumidores históricos de DMS (DocumentPreviewPage, DocumentWizard…).
 */
export async function fetchMe(): Promise<{ data: MeProfile }> {
  const data = await profileApi.fetchMe();
  return { data };
}

/**
 * PUT /api/v1/me/locale — delega en el helper compartido. El backend reporta
 * `meta.locale_persisted = false` mientras el writer sigue siendo no-op,
 * pero el cambio se propaga al resto del ecosistema vía la cookie
 * `maya_session_overrides` (gestionada por `useLocale.setLocale`).
 */
export const updateMyLocale = profileApi.updateMyLocale;
