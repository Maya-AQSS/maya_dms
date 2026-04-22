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
 */
export async function fetchDocuments(): Promise<Document[]> {
  const body = await apiGetJson<DocumentsApiResponse>('documents');
  return body.data;
}

/**
 * GET /api/v1/documents/{id} — detalle con bloques para previsualización / editor.
 */
export async function fetchDocument(documentId: string): Promise<DocumentDetail> {
  const body = await apiGetJson<DocumentDetailApiResponse>(`documents/${encodeURIComponent(documentId)}`);
  return body.data;
}

/**
 * GET /api/v1/documents/creation-options?module_id={id}
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
