import { apiFetchJson, apiGetJson } from './http';

export type FavoritesPayload = {
  template_ids: string[];
  document_ids: string[];
};

/** GET /api/v1/favorites */
export async function fetchFavorites(): Promise<{ data: FavoritesPayload }> {
  return apiGetJson<{ data: FavoritesPayload }>('favorites');
}

/** POST /api/v1/favorites/templates/{id} */
export async function addTemplateFavorite(templateId: string): Promise<void> {
  await apiFetchJson(`favorites/templates/${templateId}`, { method: 'POST' });
}

/** DELETE /api/v1/favorites/templates/{id} */
export async function removeTemplateFavorite(templateId: string): Promise<void> {
  await apiFetchJson(`favorites/templates/${templateId}`, { method: 'DELETE' });
}

/** POST /api/v1/favorites/documents/{id} */
export async function addDocumentFavorite(documentId: string): Promise<void> {
  await apiFetchJson(`favorites/documents/${documentId}`, { method: 'POST' });
}

/** DELETE /api/v1/favorites/documents/{id} */
export async function removeDocumentFavorite(documentId: string): Promise<void> {
  await apiFetchJson(`favorites/documents/${documentId}`, { method: 'DELETE' });
}
