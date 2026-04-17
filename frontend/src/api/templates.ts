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
  const perPage = filters.per_page ?? 20;
  q.set('per_page', String(Math.min(Math.max(perPage, 1), 20)));
  if (filters.page != null && filters.page > 0) {
    q.set('page', String(filters.page));
  }
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
