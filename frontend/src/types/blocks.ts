export type BlockState = 'editable' | 'modifiable' | 'locked' | 'optional';
export type BlockKind = 'content' | 'cover' | 'blank' | 'toc';

export type TemplateBlock = {
  id: string;
  template_id: string;
  type: string;
  title: string | null;
  default_content: unknown | null;
  description: unknown | null;
  block_state: BlockState;
  mandatory: boolean;
  sort_order: number;
  kind?: BlockKind;
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
  description?: unknown | null;
  block_state?: BlockState;
  mandatory?: boolean;
  sort_order?: number;
  kind?: BlockKind;
};

export type UpdateBlockPayload = {
  type?: string;
  title?: string | null;
  default_content?: unknown | null;
  description?: unknown | null;
  block_state?: BlockState;
  mandatory?: boolean;
  sort_order?: number;
  kind?: BlockKind;
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

export const BLOCK_KIND_LABELS: Record<BlockKind, string> = {
  content: 'Contenido',
  cover: 'Portada',
  blank: 'Página en blanco',
  toc: 'Índice',
};
