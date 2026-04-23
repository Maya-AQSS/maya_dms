import type { Document, DocumentDetail } from '../types/documents';
import { apiFetchJson, apiGetJson } from './http';

type DocumentsApiResponse = { data: Document[] };
type DocumentDetailApiResponse = { data: DocumentDetail };
type CreationMode = 'none' | 'auto' | 'select';

export type DocumentCreationOption = {
  template_id: string;
  template_version_id: string;
  name: string;
  description: string | null;
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
 * @returns Lista de documentos del usuario autenticado.
 */
export async function fetchDocuments(): Promise<Document[]> {
  const body = await apiGetJson<DocumentsApiResponse>('documents');
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
  template_version_id?: string;
}): Promise<Document> {
  const body = await apiFetchJson<CreateFromModuleResponse>('documents/create-from-module', {
    method: 'POST',
    body: payload,
  });
  return body.data;
}

type DocumentMutationApiResponse = { data: Document };

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
}): Promise<Document> {
  const body = await apiFetchJson<DocumentMutationApiResponse>(
    `documents/${encodeURIComponent(documentId)}`,
    { method: 'PATCH', body: payload },
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
