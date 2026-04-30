import { apiFetchJson, apiGetJson } from './http';
/**
 * GET /api/v1/documents — listado de documentos del usuario autenticado.
 *
 * @returns Lista de documentos del usuario autenticado.
 */
export async function fetchDocuments() {
    const body = await apiGetJson('documents');
    return body.data;
}
/**
 * GET /api/v1/documents/{id} — detalle con bloques para previsualización / editor.
 *
 * @param documentId - ID del documento.
 * @returns Detalle del documento con bloques para previsualización / editor.
 */
export async function fetchDocument(documentId) {
    const body = await apiGetJson(`documents/${encodeURIComponent(documentId)}`);
    return body.data;
}
/**
 * GET /api/v1/documents/creation-options?module_id={id}
 *
 * @param moduleId - ID del módulo.
 * @returns Opciones de creación de documentos para el módulo.
 */
export async function fetchDocumentCreationOptions(moduleId) {
    const body = await apiGetJson(`documents/creation-options?module_id=${encodeURIComponent(moduleId)}`);
    return body.data;
}
/**
 * POST /api/v1/documents/create-from-module
 *
 * @param payload - Datos para crear un documento desde un módulo.
 * @returns Documento creado.
 */
export async function createDocumentFromModule(payload) {
    const body = await apiFetchJson('documents/create-from-module', {
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
export async function createDocument(payload) {
    const body = await apiFetchJson('documents', {
        method: 'POST',
        body: payload,
    });
    return body.data;
}
/**
 * PATCH /api/v1/documents/{id} — hoy el backend valida al menos `title`.
 *
 * @param documentId - ID del documento.
 * @param payload - Datos para actualizar el documento.
 * @returns Documento actualizado.
 */
export async function updateDocument(documentId, payload) {
    const body = await apiFetchJson(`documents/${encodeURIComponent(documentId)}`, { method: 'PATCH', body: payload });
    return body.data;
}
/** POST /api/v1/documents/{id}/submit */
export async function submitDocumentForReview(documentId) {
    const body = await apiFetchJson(`documents/${encodeURIComponent(documentId)}/submit`, { method: 'POST', body: {} });
    return body.data;
}
/** GET /api/v1/documents/{id}/reviews */
export async function fetchDocumentReviews(documentId) {
    const body = await apiGetJson(`documents/${encodeURIComponent(documentId)}/reviews`);
    return body.data;
}
/** POST /api/v1/documents/{id}/reviews/{review}/approve */
export async function approveDocumentReview(documentId, reviewId, changelog) {
    const body = await apiFetchJson(`documents/${encodeURIComponent(documentId)}/reviews/${encodeURIComponent(reviewId)}/approve`, { method: 'POST', body: { changelog: changelog ?? null } });
    return body.data;
}
/** POST /api/v1/documents/{id}/reviews/{review}/reject — `rejection_reason` obligatorio (mín. 5 caracteres). */
export async function rejectDocumentReview(documentId, reviewId, rejectionReason) {
    const body = await apiFetchJson(`documents/${encodeURIComponent(documentId)}/reviews/${encodeURIComponent(reviewId)}/reject`, { method: 'POST', body: { rejection_reason: rejectionReason } });
    return body.data;
}
/**
 * PUT /api/v1/documents/{document}/blocks/{block} — actualiza el JSON de contenido del bloque.
 *
 * @param documentId - ID del documento.
 * @param documentBlockId - ID del bloque.
 * @param content - Contenido del bloque.
 * @returns Contenido del bloque actualizado.
 */
/** DELETE /api/v1/documents/{id} — 204 sin body. */
export async function deleteDocument(documentId) {
    await apiFetchJson(`documents/${encodeURIComponent(documentId)}`, { method: 'DELETE' });
}
export async function updateDocumentBlock(documentId, documentBlockId, content) {
    const body = await apiFetchJson(`documents/${encodeURIComponent(documentId)}/blocks/${encodeURIComponent(documentBlockId)}`, { method: 'PUT', body: { content } });
    return body.data;
}
