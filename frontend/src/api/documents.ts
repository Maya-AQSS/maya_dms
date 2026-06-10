import type { Document, DocumentDetail } from '../types/documents';
import { apiFetchJson, apiGetJson, buildApiUrl, getBearerToken, ApiHttpError } from './http';
import { fetchAllPaginatedPages, normalizePaginatedResponse } from './paginatedList';
import {
  migrationPayloadSchema,
  type DocumentMigrationPayload,
} from '../features/documents/schemas/migrationPayload';

export type TemplateVersionStatus = {
  current_version: { id: string; version_number: number } | null;
  latest_version: { id: string; version_number: number; changelog: string } | null;
  has_update: boolean;
  changelog: string | null;
};

export type DocumentListFilters = {
  process_id?: string;
  status?: string;
  template_id?: string;
  created_by?: string;
  /** Búsqueda server-side por título del documento. */
  search?: string;
  /** Y-m-d: documentos creados en esa fecha o después. */
  from?: string;
  /** Y-m-d: documentos creados en esa fecha o antes. */
  to?: string;
  /** Columna de ordenación server-side (whitelist backend: title, status, delivery_deadline, created_at, updated_at). */
  sort_by?: string;
  sort_dir?: 'asc' | 'desc';
  /** CSV de ids de documento favoritos (filtro "solo favoritos" resuelto server-side). */
  favorite_ids?: string;
  page?: number;
  per_page?: number;
};

/** Metadatos de paginación server-side de documentos. */
export type DocumentsListMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type DocumentsPageResponse = {
  data: Document[];
  meta: DocumentsListMeta;
};

function buildDocumentsListQuery(filters: DocumentListFilters): string {
  const q = new URLSearchParams();
  if (filters.process_id) q.set('process_id', filters.process_id);
  if (filters.status) q.set('status', filters.status);
  if (filters.template_id) q.set('template_id', filters.template_id);
  if (filters.created_by) q.set('created_by', filters.created_by);
  if (filters.search) q.set('search', filters.search);
  if (filters.from) q.set('from', filters.from);
  if (filters.to) q.set('to', filters.to);
  if (filters.sort_by) q.set('sort_by', filters.sort_by);
  if (filters.sort_dir) q.set('sort_dir', filters.sort_dir);
  if (filters.favorite_ids) q.set('favorite_ids', filters.favorite_ids);
  if (filters.page) q.set('page', String(filters.page));
  if (filters.per_page) q.set('per_page', String(filters.per_page));
  const s = q.toString();
  return s ? `?${s}` : '';
}

/**
 * GET /api/v1/documents — una sola página (server-side: filtros, sort y paginación
 * los resuelve el backend). Usado por la tabla de documentos vía useServerTable.
 */
export async function fetchDocumentsPage(
  filters: DocumentListFilters = {},
): Promise<DocumentsPageResponse> {
  const body = await apiGetJson<unknown>(`documents${buildDocumentsListQuery(filters)}`);
  const page = normalizePaginatedResponse<Document>(body);
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
type DocumentDetailApiResponse = { data: DocumentDetail };
type CreationMode = 'none' | 'auto' | 'select';

export type DocumentCreationOption = {
  template_id: string;
  template_version_id: string;
  process_id: string;
  name: string;
  description: string | null;
  visibility_level?: string;
  team_id?: string | null;
  team_name?: string | null;
};

export type DocumentCreationOptionsResponse = {
  data: {
    can_create: boolean;
    mode: CreationMode;
    message: string | null;
    options: DocumentCreationOption[];
  };
};

type CreateFromModuleResponse = { data: Document };

/**
 * GET /api/v1/documents — listado del usuario; agrega todas las páginas (ADR-C).
 *
 * @param filters Filtros opcionales (p. ej. `process_id` para acotar al proceso activo).
 */
export async function fetchDocuments(filters: DocumentListFilters = {}): Promise<Document[]> {
  const { page: _page, per_page, process_id } = filters;
  const pageSize = per_page ?? 100;
  const result = await fetchAllPaginatedPages<Document>(
    async (page, perPage) => {
      const params = new URLSearchParams();
      if (process_id) params.set('process_id', process_id);
      params.set('page', String(page));
      params.set('per_page', String(perPage));
      const qs = params.toString();
      const body = await apiGetJson<unknown>(qs ? `documents?${qs}` : 'documents');
      return normalizePaginatedResponse<Document>(body);
    },
    pageSize,
  );

  return result.data;
}

/**
 * GET /api/v1/documents/{id} — detalle con bloques para previsualización / editor.
 * 
 * @param documentId - ID del documento.
 * @returns Detalle del documento con bloques para previsualización / editor.
 */
export async function fetchDocument(documentId: string): Promise<DocumentDetail> {
  const body = await apiGetJson<DocumentDetailApiResponse>(`documents/${encodeURIComponent(documentId)}`);
  return body.data;
}

/** Validador del pool de un documento (`GET documents/{id}/reviewers`). */
export type DocumentReviewerEntry = {
  id: string;
  name: string | null;
  stage: number | null;
};

/** Respuesta de `GET documents/{id}/reviewers`. */
export type DocumentReviewerPool = {
  kind: 'document' | 'template_fallback' | 'none';
  review_mode: 'sequential' | 'parallel';
  reviewers: DocumentReviewerEntry[];
};

/**
 * GET /api/v1/documents/{id}/reviewers — pool de validadores del documento,
 * resuelto en backend desde la versión de plantilla anclada (misma fuente que
 * el envío a validar). No requiere acceso de lectura a la plantilla.
 */
export async function fetchDocumentReviewers(documentId: string): Promise<DocumentReviewerPool> {
  const body = await apiGetJson<{ data: DocumentReviewerPool }>(
    `documents/${encodeURIComponent(documentId)}/reviewers`,
  );
  return body.data;
}

/** Metadatos de una fila del historial (`GET documents/{id}/versions`). */
export type DocumentVersionSummary = {
  id: string;
  document_id: string;
  version_number: number;
  trigger_event: string;
  triggered_by: string;
  published_by_name?: string | null;
  author_name?: string | null;
  reviewer_names?: string[] | null;
  changelog: string | null;
  notes: string | null;
  created_at: string | null;
};

/** GET /api/v1/documents/{id}/versions — metadatos de versiones publicadas (sin snapshot completo). */
export async function fetchDocumentVersionSummaries(documentId: string): Promise<DocumentVersionSummary[]> {
  const body = await apiGetJson<{ data: DocumentVersionSummary[] }>(
    `documents/${encodeURIComponent(documentId)}/versions`,
  );
  return body.data;
}

/** Detalle de una versión publicada del documento (`GET documents/{id}/versions/{version}`). */
export type DocumentVersionDetail = {
  id: string;
  document_id: string;
  version_number: number;
  trigger_event: string;
  triggered_by: string;
  published_by_name?: string | null;
  author_name?: string | null;
  owner_name?: string | null;
  changelog: string | null;
  snapshot_data: Record<string, unknown>;
  created_at: string | null;
};

/** GET /api/v1/documents/{documentId}/versions/{versionId} — snapshot completo (solo lectura). */
export async function fetchDocumentVersionDetail(
  documentId: string,
  versionId: string,
): Promise<DocumentVersionDetail> {
  const body = await apiGetJson<{ data: DocumentVersionDetail }>(
    `documents/${encodeURIComponent(documentId)}/versions/${encodeURIComponent(versionId)}`,
  );
  return body.data;
}

/**
 * GET /api/v1/documents/creation-options?module_id={id}
 * 
 * @param moduleId - ID del módulo.
 * @returns Opciones de creación de documentos para el módulo.
 */
export async function fetchDocumentCreationOptions(
  moduleId: string,
): Promise<DocumentCreationOptionsResponse['data']> {
  const body = await apiGetJson<DocumentCreationOptionsResponse>(
    `documents/creation-options?module_id=${encodeURIComponent(moduleId)}`,
  );
  return body.data;
}

/**
 * POST /api/v1/documents/create-from-module
 * 
 * @param payload - Datos para crear un documento desde un módulo.
 * @returns Documento creado.
 */
export async function createDocumentFromModule(payload: {
  module_id: string;
  process_id: string;
  template_version_id?: string;
  delivery_deadline?: string | null;
}): Promise<Document> {
  const body = await apiFetchJson<CreateFromModuleResponse>('documents/create-from-module', {
    method: 'POST',
    body: payload,
  });
  return body.data;
}

/**
 * POST /api/v1/documents
 * 
 * @param payload - Datos para crear un documento.
 * @returns Documento creado.
 */
export async function createDocument(payload: {
  template_id: string;
  process_id: string;
  title: string;
  study_type_id?: string | null;
  study_id?: string | null;
  module_id?: string | null;
  team_id?: string | null;
  template_version_id?: string | null;
  delivery_deadline?: string | null;
  /** Paso de migración: contenido a precargar por template_block_id. */
  migrated_blocks?: Record<string, unknown>;
}): Promise<Document> {
  const body = await apiFetchJson<DocumentMutationApiResponse>('documents', {
    method: 'POST',
    body: payload,
  });
  return body.data;
}

/**
 * GET /api/v1/documents/{id}/template-version-status — indica si existe una
 * versión publicada de plantilla más reciente que la anclada al documento.
 */
export async function fetchTemplateVersionStatus(
  documentId: string,
): Promise<TemplateVersionStatus> {
  const body = await apiGetJson<{ data: TemplateVersionStatus }>(
    `documents/${encodeURIComponent(documentId)}/template-version-status`,
  );
  return body.data;
}

/**
 * GET /api/v1/documents/{id}/migration-payload — payload del paso de migración:
 * bloques de la versión nueva comparados con la versión del documento origen.
 */
export async function fetchDocumentMigrationPayload(
  sourceDocumentId: string,
): Promise<DocumentMigrationPayload> {
  const body = await apiGetJson<{ data: unknown }>(
    `documents/${encodeURIComponent(sourceDocumentId)}/migration-payload`,
  );
  return migrationPayloadSchema.parse(body.data);
}

/**
 * POST /api/v1/documents/{id}/apply-template-migration — actualiza in-situ el
 * documento (en ciclo de nueva versión) a la versión de plantilla destino.
 */
export async function applyTemplateMigration(
  documentId: string,
  payload: {
    target_template_version_id: string;
    migrated_blocks: Record<string, unknown>;
    removed_block_actions: Record<string, 'delete' | 'keep'>;
  },
): Promise<DocumentDetail> {
  const body = await apiFetchJson<DocumentDetailApiResponse>(
    `documents/${encodeURIComponent(documentId)}/apply-template-migration`,
    { method: 'POST', body: payload },
  );
  return body.data;
}

type DocumentMutationApiResponse = { data: Document };
type DocumentSubmitApiResponse = { data: Document };

/** Fila de `document_reviews` (GET documents/{id}/reviews). */
export type DocumentReview = {
  id: string;
  document_id: string;
  reviewer_id: string;
  reviewer_name?: string | null;
  stage: number;
  status: string;
  rejection_reason?: string | null;
  reviewed_at?: string | null;
};

type DocumentReviewsApiResponse = { data: DocumentReview[] };

/**
 * PATCH /api/v1/documents/{id} — hoy el backend valida al menos `title`.
 * 
 * @param documentId - ID del documento.
 * @param payload - Datos para actualizar el documento.
 * @returns Documento actualizado.
 */
export async function updateDocument(documentId: string, payload: {
  title: string;
  delivery_deadline?: string | null;
  study_type_id?: string | null;
  study_id?: string | null;
  module_id?: string | null;
}): Promise<Document> {
  const body = await apiFetchJson<DocumentMutationApiResponse>(
    `documents/${encodeURIComponent(documentId)}`,
    { method: 'PATCH', body: payload },
  );
  return body.data;
}

/** POST /api/v1/documents/{id}/delegate */
export async function delegateDocument(documentId: string, newOwnerId: string): Promise<Document> {
  const body = await apiFetchJson<{ data: Document }>(
    `documents/${encodeURIComponent(documentId)}/delegate`,
    { method: 'POST', body: { new_owner_id: newOwnerId } },
  );
  return body.data;
}

/** POST /api/v1/documents/{id}/submit */
export async function submitDocumentForReview(documentId: string, changelog: string): Promise<Document> {
  const body = await apiFetchJson<DocumentSubmitApiResponse>(
    `documents/${encodeURIComponent(documentId)}/submit`,
    { method: 'POST', body: { changelog } },
  );
  return body.data;
}

/** POST /api/v1/documents/{id}/new-version — publicado → borrador (mismo expediente). */
export async function startDocumentNewVersion(documentId: string): Promise<DocumentDetail> {
  const body = await apiFetchJson<DocumentDetailApiResponse>(
    `documents/${encodeURIComponent(documentId)}/new-version`,
    { method: 'POST', body: {} },
  );
  return body.data;
}

/** POST /api/v1/documents/{id}/clone */
export async function cloneDocument(documentId: string): Promise<DocumentDetail> {
  const body = await apiFetchJson<DocumentDetailApiResponse>(
    `documents/${encodeURIComponent(documentId)}/clone`,
    { method: 'POST', body: {} },
  );
  return body.data;
}

/** DELETE /api/v1/documents/{id}/versions/{versionId} — descarta borrador/en revisión y restaura última publicada. */
export async function discardDocumentWorkingVersion(
  documentId: string,
  versionId: string,
): Promise<DocumentDetail> {
  const body = await apiFetchJson<DocumentDetailApiResponse>(
    `documents/${encodeURIComponent(documentId)}/versions/${encodeURIComponent(versionId)}`,
    { method: 'DELETE' },
  );
  return body.data;
}

/** GET /api/v1/documents/{id}/reviews */
export async function fetchDocumentReviews(documentId: string): Promise<DocumentReview[]> {
  const body = await apiGetJson<DocumentReviewsApiResponse>(
    `documents/${encodeURIComponent(documentId)}/reviews`,
  );
  return body.data;
}

/** POST /api/v1/documents/{id}/reviews/{review}/approve */
export async function approveDocumentReview(
  documentId: string,
  reviewId: string,
  changelog?: string | null,
): Promise<Document> {
  const body = await apiFetchJson<DocumentMutationApiResponse>(
    `documents/${encodeURIComponent(documentId)}/reviews/${encodeURIComponent(reviewId)}/approve`,
    { method: 'POST', body: { changelog: changelog ?? null } },
  );
  return body.data;
}

/** POST /api/v1/documents/{id}/reviews/{review}/reject — `rejection_reason` obligatorio (mín. 5 caracteres). */
export async function rejectDocumentReview(
  documentId: string,
  reviewId: string,
  rejectionReason: string | null,
): Promise<Document> {
  const body = await apiFetchJson<DocumentMutationApiResponse>(
    `documents/${encodeURIComponent(documentId)}/reviews/${encodeURIComponent(reviewId)}/reject`,
    { method: 'POST', body: { rejection_reason: rejectionReason } },
  );
  return body.data;
}

type DocumentBlockUpdateApiResponse = { data: Record<string, unknown> };

/**
 * PUT /api/v1/documents/{document}/blocks/{block} — actualiza el JSON de contenido del bloque.
 * 
 * @param documentId - ID del documento.
 * @param documentBlockId - ID del bloque.
 * @param content - Contenido del bloque.
 * @returns Contenido del bloque actualizado.
 */
/** DELETE /api/v1/documents/{id} — 204 sin body. */
export async function deleteDocument(documentId: string): Promise<void> {
  await apiFetchJson<undefined>(`documents/${encodeURIComponent(documentId)}`, { method: 'DELETE' });
}

export async function updateDocumentBlock(
  documentId: string,
  documentBlockId: string,
  content: unknown,
): Promise<Record<string, unknown>> {
  const body = await apiFetchJson<DocumentBlockUpdateApiResponse>(
    `documents/${encodeURIComponent(documentId)}/blocks/${encodeURIComponent(documentBlockId)}`,
    { method: 'PUT', body: { content } },
  );
  return body.data;
}

/** DELETE /api/v1/documents/{id}/blocks/{block} — elimina un bloque opcional (204 sin body). */
export async function deleteDocumentBlock(documentId: string, documentBlockId: string): Promise<void> {
  await apiFetchJson<undefined>(
    `documents/${encodeURIComponent(documentId)}/blocks/${encodeURIComponent(documentBlockId)}`,
    { method: 'DELETE' },
  );
}

/* ─── Export PDF/UA ───────────────────────────────────────────────────── */

export type DocumentExportState = 'none' | 'queued' | 'processing' | 'ready' | 'failed';

export interface DocumentExportPayload {
  state: DocumentExportState;
  document_id: string;
  path?: string;
  error?: string;
  queued_at?: string;
  ready_at?: string;
}

/**
 * Prefijo de ruta del export: el HEAD vivo cuelga de `documents/{id}`; una
 * versión histórica cuelga de `documents/{id}/versions/{versionId}`.
 */
function exportBasePath(documentId: string, versionId?: string): string {
  const base = `documents/${encodeURIComponent(documentId)}`;
  return versionId ? `${base}/versions/${encodeURIComponent(versionId)}` : base;
}

/** POST /api/v1/documents/{id}[/versions/{versionId}]/export-pdf — encola la generación (idempotente). */
export async function exportDocumentPdf(documentId: string, versionId?: string): Promise<DocumentExportPayload> {
  const res = await apiFetchJson<{ data: DocumentExportPayload }>(
    `${exportBasePath(documentId, versionId)}/export-pdf`,
    { method: 'POST' },
  );
  return res.data;
}

/** GET /api/v1/documents/{id}[/versions/{versionId}]/export-status — polling del estado. */
export async function getDocumentExportStatus(documentId: string, versionId?: string): Promise<DocumentExportPayload> {
  const res = await apiGetJson<{ data: DocumentExportPayload }>(
    `${exportBasePath(documentId, versionId)}/export-status`,
  );
  return res.data;
}

/**
 * GET /api/v1/documents/{id}/pdf — descarga binaria autenticada.
 *
 * El endpoint devuelve `application/pdf`. Como necesitamos pasar el JWT en
 * un header, usamos fetch + blob y disparamos la descarga con un `<a>`
 * sintético (mismo patrón que las demás descargas autenticadas del proyecto).
 */
export async function downloadDocumentPdf(documentId: string, filename: string, versionId?: string): Promise<void> {
  const token = await getBearerToken();
  const response = await fetch(buildApiUrl(`${exportBasePath(documentId, versionId)}/pdf`), {
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
