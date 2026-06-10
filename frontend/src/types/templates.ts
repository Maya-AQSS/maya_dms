export type ReviewCycleBlock = {
  id: string;
  sort_order: number;
  title: string | null;
  description: unknown;
  default_content: unknown;
  block_state: string;
};

export type ReviewCycleSnapshot = {
  cycle: number;
  submitted_at: string;
  submitted_by: string;
  blocks: ReviewCycleBlock[];
};

/** Valores de visibility_level en la API (alineados con el backend). */
export type TemplateVisibilityLevel =
  | 'global'
  | 'study_type'
  | 'study'
  | 'module'
  | 'team'
  | 'personal';

export type TemplateStatus = 'draft' | 'in_review' | 'published' | 'archived' | 'rejected';

export type ReviewMode = 'sequential' | 'parallel';

export type TemplateReviewer = {
  user_id: string;
  user_name?: string;
  stage: number;
  status?: 'pending' | 'approved' | 'rejected';
};

export type TemplateDocumentReviewerUser = {
  user_id: string;
  user_name?: string | null;
  stage?: number | null;
};

export type Template = {
  id: string;
  name: string;
  description: string | null;
  visibility_level: TemplateVisibilityLevel;
  delivery_deadline: string | null;
  study_type_id: string | null;
  study_id: string | null;
  module_id: string | null;
  team_id: string | null;
  process_id: string | null;
  team?: {
    id: string;
    name: string;
    is_department: boolean;
    description?: string | null;
  } | null;
  created_by: string;
  author_name?: string | null;
  status: TemplateStatus;
  version: number;
  review_stages: number;
  review_mode: ReviewMode;
  /** Modo de validación de documentos; si no está definido, el frontend usa review_mode. */
  document_review_mode?: ReviewMode | null;
  reviewers?: TemplateReviewer[];
  document_reviewers?: string[];
  document_reviewer_users?: TemplateDocumentReviewerUser[];
  has_review_comments?: boolean;
  latest_published_version_id?: string | null;
  latest_published_version_number?: number | null;
  list_variant?: 'live' | 'published_fallback';
  list_row_id?: string;
  /** Alineado con `TemplatePolicy::clone` en el servidor. */
  can_clone?: boolean;
  working_version_id?: string | null;
  review_history?: ReviewCycleSnapshot[] | null;
  created_at?: string;
  updated_at?: string;
  latest_published_name?: string | null;
  latest_published_at?: string | null;
  blocks_at_previous_submission?: Array<{
    id: string;
    title: string;
    default_content: unknown;
    block_state: string;
    sort_order: number;
  }> | null;
  /** Theme asignado (nullable = sin theme). */
  theme_id?: string | null;
  /** Mini-payload del theme cuando viene incluido en la respuesta. */
  theme?: ThemeMini | null;
  /** Changelog del envío a validación (versión de trabajo); precarga el modal al reenviar. */
  submission_changelog?: string | null;
};

/**
 * Mini-payload del theme — espejo de `ThemeMini` del backend (no es el theme
 * completo, sólo lo necesario para el selector + mini-preview).
 */
export interface ThemeMini {
  id: string;
  name: string;
  palette: {
    primary: string | null;
    secondary: string | null;
    accent: string | null;
    background: string | null;
    text: string | null;
  };
  typography: {
    heading_font: string | null;
    body_font: string | null;
  };
}

export type TemplatesListMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

/** Listado de plantillas: `data` agregada de todas las páginas; `meta` para paginación en cliente. */
export type TemplatesListResponse = {
  data: Template[];
  meta?: TemplatesListMeta;
};

export type TemplateListFilters = {
  visibility_level?: string;
  status?: string;
  usable_for_documents?: boolean;
  study_type_id?: string;
  study_id?: string;
  module_id?: string;
  team_id?: string;
  author_name?: string;
  /** Búsqueda server-side por nombre de plantilla o nombre del autor. */
  search?: string;
  /** Columna de ordenación server-side (whitelist backend: name, delivery_deadline, created_at, updated_at). */
  sort_by?: string;
  /** Dirección de ordenación server-side. */
  sort_dir?: 'asc' | 'desc';
  /** CSV de ids de versión favoritos (filtro "solo favoritos" resuelto server-side). */
  favorite_ids?: string;
  /** Y-m-d: plantillas con fecha límite de validación en esa fecha o anterior (inclusive). */
  delivery_deadline?: string;
  /** Filtro por día de publicación de la última versión publicada (Y-m-d). */
  published_on?: string;
  process_id?: string;
  page?: number;
  per_page?: number;
};
