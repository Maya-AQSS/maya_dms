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
  created_at?: string;
  updated_at?: string;
  latest_published_name?: string | null;
  blocks_at_previous_submission?: Array<{
    id: string;
    title: string;
    default_content: unknown;
    block_state: string;
    sort_order: number;
  }> | null;
};

export type TemplatesListMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

/** El backend devuelve solo `data`; la `meta` de paginación se calcula en cliente. */
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
  delivery_deadline?: string;
  process_id?: string;
  page?: number;
  per_page?: number;
};
