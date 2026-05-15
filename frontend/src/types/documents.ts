import type { BlockState } from './blocks';
import type { TemplateVisibilityLevel } from './templates';

export type DocumentReviewCycleBlock = {
  document_block_id: string;
  template_block_id: string;
  sort_order: number;
  content: unknown;
};

export type DocumentReviewCycleSnapshot = {
  cycle: number;
  submitted_at: string;
  submitted_by: string;
  blocks: DocumentReviewCycleBlock[];
};

export type DocumentStatus = 'draft' | 'in_review' | 'published';

export type Document = {
  id: string;
  template_id: string;
  template_version_id: string | null;
  /** Número de versión publicada de la plantilla anclada (p. ej. 1); null si no hay ancla. */
  template_version_number?: number | null;
  title: string;
  study_type_id: string | null;
  study_id: string | null;
  module_id: string | null;
  team_id: string | null;
  delivery_deadline?: string | null;
  created_by: string;
  owner_id: string;
  status: DocumentStatus;
  current_version: number;
  submitted_at: string | null;
  published_at: string | null;
  created_at?: string;
  updated_at?: string;
  owner_name?: string | null;
  /** Heredada de la plantilla anclada; null si la plantilla no está cargada en la respuesta. */
  visibility_level?: TemplateVisibilityLevel | null;
  /** Metadatos de compartición (DocumentResource); opcional en listados antiguos. */
  is_shared_with_me?: boolean;
  share_permission?: string | null;
  team?: unknown;
  has_review_comments?: boolean;
  can_clone?: boolean;
  /** Modo de revisión resuelto desde el snapshot anclado; coincide con lo que aplica el backend al aprobar/rechazar. */
  review_mode?: 'sequential' | 'parallel';
  working_version_id?: string | null;
  review_history?: DocumentReviewCycleSnapshot[] | null;
  latest_published_version_id?: string | null;
  latest_published_version_number?: number | null;
  latest_published_title?: string | null;
  list_variant?: 'live' | 'published_fallback';
  list_row_id?: string;
};

/**
 * Bloque tal como lo devuelve `GET /documents/{id}` en `data.blocks` (mezcla definición + contenido del documento).
 */
export type DocumentDisplayBlock = {
  document_block_id: string | null;
  template_block_id: string;
  type: string;
  title: string | null;
  /** Puede ser texto, JSON string o estructura BlockNote (objeto/array) según plantilla / snapshot. */
  description?: unknown;
  default_content: unknown | null;
  block_state: BlockState;
  mandatory: boolean;
  sort_order: number;
  content: unknown | null;
  is_filled: boolean;
  /** True cuando es un bloque opcional que el usuario eliminó explícitamente. Solo aparece en la vista diff. */
  is_deleted?: boolean;
};

export type DocumentDetail = Document & {
  blocks: DocumentDisplayBlock[];
};
