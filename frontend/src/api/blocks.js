import { apiFetchJson, apiGetJson } from './http';
/** GET /api/v1/templates/{templateId}/blocks */
export async function fetchBlocks(templateId) {
    return apiGetJson(`templates/${templateId}/blocks`);
}
/** POST /api/v1/templates/{templateId}/blocks */
export async function createBlock(templateId, payload) {
    return apiFetchJson(`templates/${templateId}/blocks`, {
        method: 'POST',
        body: payload,
    });
}
/** PUT /api/v1/blocks/{blockId} */
export async function updateBlock(blockId, payload) {
    return apiFetchJson(`blocks/${blockId}`, {
        method: 'PUT',
        body: payload,
    });
}
/** DELETE /api/v1/blocks/{blockId} */
export async function deleteBlock(blockId) {
    await apiFetchJson(`blocks/${blockId}`, { method: 'DELETE' });
}
/** PUT /api/v1/blocks/bulk */
export async function bulkUpdateBlocks(payload) {
    return apiFetchJson('blocks/bulk', {
        method: 'PUT',
        body: payload,
    });
}
/** PATCH /api/v1/templates/{templateId}/blocks/reorder */
export async function reorderBlocksForTemplate(templateId, blockIds) {
    await apiFetchJson(`templates/${templateId}/blocks/reorder`, {
        method: 'PATCH',
        body: { block_ids: blockIds },
    });
}
