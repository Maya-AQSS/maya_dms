import type {
  ReviewMode,
  Template,
  TemplateListFilters,
  TemplateStatus,
  TemplatesListResponse,
  TemplateVisibilityLevel,
} from '../types/templates';
import { apiFetchJson, apiGetJson } from './http';

export type { Template, TemplateListFilters, TemplatesListResponse } from '../types/templates';

export type CreateTemplatePayload = {
  name: string;
  process_id: string;
  description?: string | null;
  visibility_level?: TemplateVisibilityLevel;
  delivery_deadline?: string | null;
  study_type_id?: string | null;
  study_id?: string | null;
  module_id?: string | null;
  team_id?: string | null;
  review_stages?: number;
  review_mode?: ReviewMode;
};

export type UpdateTemplatePayload = {
  name?: string;
  description?: string | null;
  visibility_level?: TemplateVisibilityLevel;
  delivery_deadline?: string | null;
  study_type_id?: string | null;
  study_id?: string | null;
  module_id?: string | null;
  team_id?: string | null;
  status?: TemplateStatus;
  review_stages?: number;
  review_mode?: ReviewMode;
};

function buildListQuery(filters: TemplateListFilters): string {
  const q = new URLSearchParams();
  if (filters.visibility_level) {
    q.set('visibility_level', filters.visibility_level);
  }
  if (filters.status) {
    q.set('status', filters.status);
  }
  if (filters.study_type_id) {
    q.set('study_type_id', filters.study_type_id);
  }
  if (filters.study_id) {
    q.set('study_id', filters.study_id);
  }
  if (filters.module_id) {
    q.set('module_id', filters.module_id);
  }
  if (filters.team_id) {
    q.set('team_id', filters.team_id);
  }
  if (filters.author_name) {
    q.set('author_name', filters.author_name);
  }
  if (filters.delivery_deadline) {
    q.set('delivery_deadline', filters.delivery_deadline);
  }
  if (filters.process_id) {
    q.set('process_id', filters.process_id);
  }
  const s = q.toString();
  return s ? `?${s}` : '';
}

/** GET /api/v1/templates */
export async function fetchTemplates(filters: TemplateListFilters = {}): Promise<TemplatesListResponse> {
  return apiGetJson<TemplatesListResponse>(`templates${buildListQuery(filters)}`);
}

/** GET /api/v1/templates/{id} */
export async function fetchTemplate(id: string): Promise<{ data: Template }> {
  return apiGetJson<{ data: Template }>(`templates/${id}`);
}

/** Bloque dentro del snapshot de una versión publicada (GET template-versions). */
export type TemplateVersionSnapshotBlock = {
  id: string;
  type: string;
  title: string;
  default_content: unknown;
  block_state?: string;
  mandatory?: boolean;
  sort_order: number;
};

export type TemplateVersionDetail = {
  id: string;
  template_id: string;
  version_number: number;
  blocks_snapshot: TemplateVersionSnapshotBlock[];
  changelog: string | null;
  published_by: string | null;
  published_at: string | null;
  created_at?: string;
  updated_at?: string;
};

/** GET /api/v1/template-versions/{id} — snapshot publicado (incluye bloques). */
export async function fetchTemplateVersion(versionId: string): Promise<TemplateVersionDetail> {
  const body = await apiGetJson<{ data: TemplateVersionDetail }>(
    `template-versions/${encodeURIComponent(versionId)}`,
  );
  return body.data;
}

export type TemplateVersionSummary = {
  id: string;
  template_id: string;
  version_number: number;
  published_at: string | null;
  published_by: string | null;
  changelog: string | null;
};

/** GET /api/v1/templates/{id}/versions — listado de versiones publicadas (sin bloques). */
export async function fetchTemplateVersionSummaries(templateId: string): Promise<TemplateVersionSummary[]> {
  const body = await apiGetJson<{ data: TemplateVersionSummary[] }>(
    `templates/${encodeURIComponent(templateId)}/versions`,
  );
  return body.data;
}

/** POST /api/v1/templates */
export async function createTemplate(payload: CreateTemplatePayload): Promise<{ data: Template }> {
  return apiFetchJson<{ data: Template }>('templates', { method: 'POST', body: payload });
}

/** PATCH /api/v1/templates/{id} */
export async function updateTemplate(
  id: string,
  payload: UpdateTemplatePayload,
): Promise<{ data: Template }> {
  return apiFetchJson<{ data: Template }>(`templates/${id}`, { method: 'PATCH', body: payload });
}

/**
 * DELETE: 204 = eliminación física; 200 con body = archivada por documentos vinculados.
 */
export async function deleteTemplate(
  id: string,
): Promise<{ hardDeleted: true } | { hardDeleted: false; data: Template }> {
  const result = await apiFetchJson<{ data: Template } | undefined>(`templates/${id}`, {
    method: 'DELETE',
  });
  if (result === undefined) {
    return { hardDeleted: true };
  }
  return { hardDeleted: false, data: result.data };
}

/** POST /api/v1/templates/{id}/clone */
export async function cloneTemplate(id: string): Promise<{ data: Template }> {
  return apiFetchJson<{ data: Template }>(`templates/${id}/clone`, { method: 'POST', body: {} });
}

/** POST /api/v1/templates/{id}/publish */
export async function publishTemplate(id: string): Promise<{ data: Template }> {
  return apiFetchJson<{ data: Template }>(`templates/${id}/publish`, { method: 'POST', body: {} });
}

/** POST /api/v1/templates/{id}/submit-review */
export async function submitTemplateForReview(id: string): Promise<{ data: Template }> {
  return apiFetchJson<{ data: Template }>(`templates/${id}/submit-review`, { method: 'POST', body: {} });
}

/** POST /api/v1/templates/{id}/reviewers */
export async function syncTemplateValidators(
  templateId: string,
  userIds: string[],
): Promise<{ message: string }> {
  return apiFetchJson<{ message: string }>(`templates/${templateId}/reviewers`, {
    method: 'POST',
    body: { user_ids: userIds },
  });
}

/** POST /api/v1/templates/{id}/document-reviewers */
export async function syncDocumentReviewers(
  templateId: string,
  userIds: string[],
): Promise<{ message: string }> {
  return apiFetchJson<{ message: string }>(`templates/${templateId}/document-reviewers`, {
    method: 'POST',
    body: { user_ids: userIds },
  });
}
/** POST /api/v1/templates/{id}/approve-review */
export async function approveTemplateReview(id: string): Promise<{ data: Template }> {
  return apiFetchJson<{ data: Template }>(`templates/${id}/approve-review`, {
    method: 'POST',
    body: {},
  });
}

/** POST /api/v1/templates/{id}/reject-review */
export async function rejectTemplateReview(id: string): Promise<{ data: Template }> {
  return apiFetchJson<{ data: Template }>(`templates/${id}/reject-review`, {
    method: 'POST',
    body: {},
  });
}

/** PATCH /api/v1/comments/{id}/resolve */
export async function resolveComment(commentId: string): Promise<{ data: any }> {
  return apiFetchJson<{ data: any }>(`comments/${commentId}/resolve`, {
    method: 'PATCH',
    body: {},
  });
}
