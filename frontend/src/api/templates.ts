import type {
  ReviewMode,
  Template,
  TemplateListFilters,
  TemplateStatus,
  TemplatesListResponse,
  TemplateVisibilityLevel,
} from '../types/templates';
import { apiFetchJson, apiGetJson, apiErrorFromResponse, buildApiUrl, getBearerToken } from './http';
import { downloadAuthenticatedBlob } from './blobDownload';
import { postNewVersion } from './newVersion';
import { fetchAllPaginatedPages, normalizePaginatedResponse } from './paginatedList';
// buildQueryString canónico compartido (0.16): misma semántica que el builder
// local eliminado (omite null/undefined/''/false/0, true→'1'); añade soporte de
// arrays (join ','), que estos call sites no usan.
import { buildQueryString } from '@ceedcv-maya/shared-auth-react';

/**
 * POST /api/v1/templates/{id}/cover-images — multipart upload de imagen para un
 * bloque de portada. Devuelve `src` (path interno para el render) + `url`
 * (firmada, para mostrar en el editor). Fetch directo porque apiFetchJson
 * serializa el body como JSON (no detecta FormData).
 */
export async function uploadCoverImage(
  templateId: string,
  file: File,
): Promise<{ src: string; url: string }> {
  const form = new FormData();
  form.append('file', file);

  const token = await getBearerToken();
  const response = await fetch(buildApiUrl(`templates/${templateId}/cover-images`), {
    method: 'POST',
    headers: token ? { Authorization: `Bearer ${token}` } : undefined,
    body: form,
  });

  if (!response.ok) {
    throw await apiErrorFromResponse(response);
  }

  const body = (await response.json()) as { data: { src: string; url: string } };
  return body.data;
}

/**
 * GET /api/v1/templates/{id}/pdf — descarga binaria autenticada del PDF/UA de la
 * plantilla. El backend lo genera de forma síncrona (WeasyPrint), igual que el
 * PDF de muestra de themes.
 */
export async function downloadTemplatePdf(templateId: string, filename: string): Promise<void> {
  await downloadAuthenticatedBlob(`templates/${encodeURIComponent(templateId)}/pdf`, filename);
}

export type { Template, TemplateListFilters, TemplatesListResponse } from '../types/templates';

export type CreateTemplatePayload = {
  name: string;
  process_id: string;
  description?: string | null;
  visibility_level?: TemplateVisibilityLevel;
  delivery_deadline?: string | null;
  document_delivery_deadline?: string | null;
  study_type_id?: string | null;
  study_id?: string | null;
  module_id?: string | null;
  team_id?: string | null;
  review_stages?: number;
  review_mode?: ReviewMode;
  document_review_mode?: ReviewMode;
};

export type UpdateTemplatePayload = {
  name?: string;
  description?: string | null;
  visibility_level?: TemplateVisibilityLevel;
  delivery_deadline?: string | null;
  document_delivery_deadline?: string | null;
  study_type_id?: string | null;
  study_id?: string | null;
  module_id?: string | null;
  team_id?: string | null;
  status?: TemplateStatus;
  review_stages?: number;
  review_mode?: ReviewMode;
  document_review_mode?: ReviewMode;
  created_by?: string;
};

function buildListQuery(filters: TemplateListFilters): string {
  return buildQueryString({ ...filters });
}

/**
 * GET /api/v1/templates — una sola página (server-side: filtros, sort y paginación
 * los resuelve el backend). Usado por la tabla de plantillas vía useServerTable.
 */
export async function fetchTemplatesPage(
  filters: TemplateListFilters = {},
): Promise<TemplatesListResponse> {
  const body = await apiGetJson<unknown>(`templates${buildListQuery(filters)}`);
  const page = normalizePaginatedResponse<Template>(body);
  return {
    data: page.data,
    meta: {
      current_page: page.current_page,
      last_page: page.last_page,
      per_page: page.per_page,
      total: page.total,
    },
  };
}

/** GET /api/v1/templates — agrega todas las páginas del listado paginado (ADR-C). */
export async function fetchTemplates(filters: TemplateListFilters = {}): Promise<TemplatesListResponse> {
  const { page: _page, per_page, ...rest } = filters;
  const pageSize = per_page ?? 100;
  const result = await fetchAllPaginatedPages<Template>(
    async (page, perPage) => {
      const body = await apiGetJson<unknown>(
        `templates${buildListQuery({ ...rest, page, per_page: perPage })}`,
      );
      return normalizePaginatedResponse<Template>(body);
    },
    pageSize,
  );

  return {
    data: result.data,
    meta: {
      current_page: result.current_page,
      last_page: result.last_page,
      per_page: result.per_page,
      total: result.total,
    },
  };
}

/** GET /api/v1/templates/{id} */
export async function fetchTemplate(id: string): Promise<Template> {
  const body = await apiGetJson<{ data: Template }>(`templates/${id}`);
  return body.data;
}

/** Bloque dentro del snapshot de una versión publicada (GET template-versions). */
export type TemplateVersionSnapshotBlock = {
  id: string;
  type: string;
  title: string;
  default_content: unknown;
  block_state?: string;
  block_type?: import('../types/blocks').BlockType;
  mandatory?: boolean;
  sort_order: number;
};

export type TemplateVersionDetail = {
  id: string;
  template_id: string;
  version_number: number;
  template_snapshot?: {
    name?: string;
    created_by?: string;
    visibility_level?: string;
    delivery_deadline?: string | null;
    updated_at?: string | null;
  } | null;
  blocks_snapshot: TemplateVersionSnapshotBlock[];
  changelog: string | null;
  published_by: string | null;
  published_by_name?: string | null;
  author_name?: string | null;
  reviewer_names?: string[] | null;
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
  published_by_name?: string | null;
  author_name?: string | null;
  reviewer_names?: string[] | null;
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
export async function createTemplate(payload: CreateTemplatePayload): Promise<Template> {
  const body = await apiFetchJson<{ data: Template }>('templates', { method: 'POST', body: payload });
  return body.data;
}

/** PATCH /api/v1/templates/{id} */
export async function updateTemplate(
  id: string,
  payload: UpdateTemplatePayload,
): Promise<Template> {
  const body = await apiFetchJson<{ data: Template }>(`templates/${id}`, { method: 'PATCH', body: payload });
  return body.data;
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
export async function cloneTemplate(id: string): Promise<Template> {
  const body = await apiFetchJson<{ data: Template }>(`templates/${id}/clone`, { method: 'POST', body: {} });
  return body.data;
}

/** POST /api/v1/templates/{id}/new-version — publicada → borrador (misma plantilla). */
export async function startTemplateNewVersion(id: string): Promise<Template> {
  const body = await postNewVersion<{ data: Template }>(`templates/${id}/new-version`);
  return body.data;
}

/** DELETE /api/v1/templates/{id}/versions/{versionId} — descarta borrador/en revisión y restaura última publicada. */
export async function discardTemplateWorkingVersion(
  templateId: string,
  versionId: string,
): Promise<Template> {
  const body = await apiFetchJson<{ data: Template }>(
    `templates/${encodeURIComponent(templateId)}/versions/${encodeURIComponent(versionId)}`,
    { method: 'DELETE' },
  );
  return body.data;
}

/** POST /api/v1/templates/{id}/publish */
export async function publishTemplate(
  id: string,
  changelog?: string | null,
): Promise<Template> {
  const body = await apiFetchJson<{ data: Template }>(`templates/${id}/publish`, {
    method: 'POST',
    body: { changelog: changelog ?? null },
  });
  return body.data;
}

/** POST /api/v1/templates/{id}/submit-review */
export async function submitTemplateForReview(
  id: string,
  changelog: string,
): Promise<Template> {
  const body = await apiFetchJson<{ data: Template }>(`templates/${id}/submit-review`, {
    method: 'POST',
    body: { changelog },
  });
  return body.data;
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
export async function approveTemplateReview(id: string): Promise<Template> {
  const body = await apiFetchJson<{ data: Template }>(`templates/${id}/approve-review`, {
    method: 'POST',
    body: {},
  });
  return body.data;
}

/** POST /api/v1/templates/{id}/reject-review */
export async function rejectTemplateReview(id: string): Promise<Template> {
  const body = await apiFetchJson<{ data: Template }>(`templates/${id}/reject-review`, {
    method: 'POST',
    body: {},
  });
  return body.data;
}

/**
 * GET /api/v1/templates/{id}/versions/{versionId}/pdf — descarga binaria autenticada
 * del PDF de una versión histórica publicada. Mismo patrón que downloadTemplatePdf
 * y downloadDocumentPdf.
 */
export async function downloadTemplateVersionPdf(
  templateId: string,
  versionId: string,
  filename: string,
): Promise<void> {
  await downloadAuthenticatedBlob(
    `templates/${encodeURIComponent(templateId)}/versions/${encodeURIComponent(versionId)}/pdf`,
    filename,
  );
}

