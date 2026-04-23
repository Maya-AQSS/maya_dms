import type { BlockState } from './blocks';

export type DocumentStatus = 'draft' | 'in_review' | 'published';

export type Document = {
  id: string;
  template_id: string;
  template_version_id: string | null;
  title: string;
  study_type_id: string | null;
  study_id: string | null;
  module_id: string | null;
  delivery_deadline?: string | null;
  created_by: string;
  owner_id: string;
  status: DocumentStatus;
  current_version: number;
  submitted_at: string | null;
  published_at: string | null;
  created_at?: string;
  updated_at?: string;
  /** Metadatos de compartición (DocumentResource); opcional en listados antiguos. */
  is_shared_with_me?: boolean;
  share_permission?: string | null;
  team?: unknown;
};

/**
 * Bloque tal como lo devuelve `GET /documents/{id}` en `data.blocks` (mezcla definición + contenido del documento).
 */
export type DocumentDisplayBlock = {
  document_block_id: string | null;
  template_block_id: string;
  type: string;
  title: string | null;
  description?: string | null;
  default_content: unknown | null;
  block_state: BlockState;
  mandatory: boolean;
  sort_order: number;
  content: unknown | null;
  is_filled: boolean;
};

export type DocumentDetail = Document & {
  blocks: DocumentDisplayBlock[];
};
