import type { Document } from '../types/documents';
import { apiGetJson } from './http';

type DocumentsApiResponse = { data: Document[] };

/**
 * GET /api/v1/documents — listado de documentos del usuario autenticado.
 */
export async function fetchDocuments(): Promise<Document[]> {
  const body = await apiGetJson<DocumentsApiResponse>('documents');
  return body.data;
}
