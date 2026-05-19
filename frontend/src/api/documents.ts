import type { Document, DocumentDetail } from '../types/documents';
import { apiFetchJson, apiGetJson } from './http';

type DocumentsApiResponse = { data: Document[] };
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
 * GET /api/v1/documents — listado de documentos del usuario autenticado.
 *
 * @param filters Filtros opcionales (e.g. process_id para acotar al proceso activo).
 * @returns Lista de documentos del usuario autenticado.
 */
export async function fetchDocuments(filters: { process_id?: string } = {}): Promise<Document[]> {
  const params = new URLSearchParams();
  if (filters.process_id) params.set('process_id', filters.process_id);
  const qs = params.toString();
  const body = await apiGetJson<DocumentsApiResponse>(qs ? `documents?${qs}` : 'documents');
  return body.data;
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

/** Metadatos de una fila del historial (`GET documents/{id}/versions`). */
export type DocumentVersionSummary = {
  id: string;
  document_id: string;
  version_number: number;
  trigger_event: string;
  triggered_by: string;
  published_by_name?: string | null;
  author_name?: string | null;
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
}): Promise<Document> {
  const body = await apiFetchJson<DocumentMutationApiResponse>('documents', {
    method: 'POST',
    body: payload,
  });
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

/** POST /api/v1/documents/{id}/submit */
export async function submitDocumentForReview(documentId: string): Promise<Document> {
  const body = await apiFetchJson<DocumentSubmitApiResponse>(
    `documents/${encodeURIComponent(documentId)}/submit`,
    { method: 'POST', body: {} },
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
