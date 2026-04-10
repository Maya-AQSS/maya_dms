import type { Document } from '../types/document';
import { apiGetJson } from './http';

type DocumentsApiResponse = {
  data: Document[];
};

export async function fetchDocuments(): Promise<Document[]> {
  const body = await apiGetJson<DocumentsApiResponse>('documents');
  return body.data;
}
