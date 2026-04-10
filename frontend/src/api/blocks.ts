import type {
  BlocksListResponse,
  BulkUpdateBlockPayload,
  CreateBlockPayload,
  TemplateBlock,
  UpdateBlockPayload,
} from '../types/blocks';
import { apiFetchJson, apiGetJson } from './http';

/** GET /api/v1/templates/{templateId}/blocks */
export async function fetchBlocks(templateId: string): Promise<BlocksListResponse> {
  return apiGetJson<BlocksListResponse>(`templates/${templateId}/blocks`);
}

/** POST /api/v1/templates/{templateId}/blocks */
export async function createBlock(
  templateId: string,
  payload: CreateBlockPayload,
): Promise<{ data: TemplateBlock }> {
  return apiFetchJson<{ data: TemplateBlock }>(`templates/${templateId}/blocks`, {
    method: 'POST',
    body: payload,
  });
}

/** PUT /api/v1/blocks/{blockId} */
export async function updateBlock(
  blockId: string,
  payload: UpdateBlockPayload,
): Promise<{ data: TemplateBlock }> {
  return apiFetchJson<{ data: TemplateBlock }>(`blocks/${blockId}`, {
    method: 'PUT',
    body: payload,
  });
}

/** DELETE /api/v1/blocks/{blockId} */
export async function deleteBlock(blockId: string): Promise<void> {
  await apiFetchJson<undefined>(`blocks/${blockId}`, { method: 'DELETE' });
}

/** PUT /api/v1/blocks/bulk */
export async function bulkUpdateBlocks(
  payload: BulkUpdateBlockPayload,
): Promise<BlocksListResponse> {
  return apiFetchJson<BlocksListResponse>('blocks/bulk', {
    method: 'PUT',
    body: payload,
  });
}
