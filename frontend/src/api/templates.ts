import type {
  ReviewMode,
  Template,
  TemplateListFilters,
  TemplateStatus,
  TemplatesListResponse,
  TemplateVisibilityLevel,
} from '../types/templates';
import { apiFetchJson, apiGetJson, buildApiUrl, getBearerToken, ApiHttpError } from './http';
import { fetchAllPaginatedPages, normalizePaginatedResponse } from './paginatedList';

/**
 * POST /api/v1/templates/{id}/cover-images — multipart upload de imagen para un
 * bloque de portada. Devuelve `src` (path interno para el render) + `url`
 * (firmada, para mostrar en el editor). Fetch directo porque apiFetchJson
 * serializa el body como JSON (no detecta FormData).
 */
export async function uploadCoverImage(
  templateId: string,
  file: File,
): Promise<{ data: { src: string; url: string } }> {
  const form = new FormData();
  form.append('file', file);

  const token = await getBearerToken();
  const response = await fetch(buildApiUrl(`templates/${templateId}/cover-images`), {
    method: 'POST',
    headers: token ? { Authorization: `Bearer ${token}` } : undefined,
    body: form,
  });

  if (!response.ok) {
    let message = response.statusText;
    try {
      const body = (await response.json()) as { message?: string };
      if (body?.message) message = body.message;
    } catch {
      /* keep statusText */
    }
    throw new ApiHttpError(message, response.status);
  }

  return (await response.json()) as { data: { src: string; url: string } };
}

/**
 * GET /api/v1/templates/{id}/pdf — descarga binaria autenticada del PDF/UA de la
 * plantilla. El backend lo genera de forma síncrona (WeasyPrint), igual que el
 * PDF de muestra de themes. Fetch + blob + `<a>` sintético (JWT en header).
 */
export async function downloadTemplatePdf(templateId: string, filename: string): Promise<void> {
  const token = await getBearerToken();
  const response = await fetch(buildApiUrl(`templates/${encodeURIComponent(templateId)}/pdf`), {
    headers: token ? { Authorization: `Bearer ${token}` } : undefined,
  });
  if (!response.ok) {
    let message = response.statusText;
    try {
      const body = (await response.json()) as { message?: string };
      if (body?.message) message = body.message;
    } catch {
      /* keep statusText */
    }
    throw new ApiHttpError(message, response.status);
  }
  const blob = await response.blob();
  const url = URL.createObjectURL(blob);
  try {
    const a = document.createElement('a');
    a.href = url;
    a.download = filename.endsWith('.pdf') ? filename : `${filename}.pdf`;
    document.body.appendChild(a);
    a.click();
    a.remove();
  } finally {
    URL.revokeObjectURL(url);
  }
}

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
  document_review_mode?: ReviewMode;
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
  document_review_mode?: ReviewMode;
  created_by?: string;
};

function buildListQuery(filters: TemplateListFilters): string {
  const q = new URLSearchParams();
  if (filters.visibility_level) {
    q.set('visibility_level', filters.visibility_level);
  }
  if (filters.status) {
    q.set('status', filters.status);
  }
  if (filters.usable_for_documents) {
    q.set('usable_for_documents', '1');
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
  if (filters.search) {
    q.set('search', filters.search);
  }
  if (filters.sort_by) {
    q.set('sort_by', filters.sort_by);
  }
  if (filters.sort_dir) {
    q.set('sort_dir', filters.sort_dir);
  }
  if (filters.favorite_ids) {
    q.set('favorite_ids', filters.favorite_ids);
  }
  if (filters.process_id) {
    q.set('process_id', filters.process_id);
  }
  if (filters.page) {
    q.set('page', String(filters.page));
  }
  if (filters.per_page) {
    q.set('per_page', String(filters.per_page));
  }
  const s = q.toString();
  return s ? `?${s}` : '';
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

/** POST /api/v1/templates/{id}/new-version — publicada → borrador (misma plantilla). */
export async function startTemplateNewVersion(id: string): Promise<{ data: Template }> {
  return apiFetchJson<{ data: Template }>(`templates/${id}/new-version`, { method: 'POST', body: {} });
}

/** DELETE /api/v1/templates/{id}/versions/{versionId} — descarta borrador/en revisión y restaura última publicada. */
export async function discardTemplateWorkingVersion(
  templateId: string,
  versionId: string,
): Promise<{ data: Template }> {
  return apiFetchJson<{ data: Template }>(
    `templates/${encodeURIComponent(templateId)}/versions/${encodeURIComponent(versionId)}`,
    { method: 'DELETE' },
  );
}

/** POST /api/v1/templates/{id}/publish */
export async function publishTemplate(
  id: string,
  changelog?: string | null,
): Promise<{ data: Template }> {
  return apiFetchJson<{ data: Template }>(`templates/${id}/publish`, {
    method: 'POST',
    body: { changelog: changelog ?? null },
  });
}

/** POST /api/v1/templates/{id}/submit-review */
export async function submitTemplateForReview(
  id: string,
  changelog: string,
): Promise<{ data: Template }> {
  return apiFetchJson<{ data: Template }>(`templates/${id}/submit-review`, {
    method: 'POST',
    body: { changelog },
  });
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

