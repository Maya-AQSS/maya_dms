export type BlockState = 'editable' | 'modifiable' | 'locked' | 'optional';

export type TemplateBlock = {
  id: string;
  template_id: string;
  type: string;
  title: string | null;
  default_content: unknown | null;
  description: string | null;
  block_state: BlockState;
  mandatory: boolean;
  sort_order: number;
  created_at?: string;
  updated_at?: string;
};

export type BlocksListResponse = {
  data: TemplateBlock[];
};

export type CreateBlockPayload = {
  type: string;
  title?: string | null;
  default_content?: unknown | null;
  description?: string | null;
  block_state?: BlockState;
  mandatory?: boolean;
  sort_order?: number;
};

export type UpdateBlockPayload = {
  type?: string;
  title?: string | null;
  default_content?: unknown | null;
  description?: string | null;
  block_state?: BlockState;
  mandatory?: boolean;
  sort_order?: number;
};

export type BulkUpdateBlockPayload = {
  ids: string[];
  block_state?: BlockState;
  mandatory?: boolean;
};

export const BLOCK_STATE_LABELS: Record<BlockState, string> = {
  editable: 'Editable',
  modifiable: 'Modificable',
  locked: 'Bloqueado',
  optional: 'Opcional',
};
