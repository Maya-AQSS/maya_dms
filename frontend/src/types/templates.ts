/** Valores de visibility_level en la API (alineados con el backend). */
export type TemplateVisibilityLevel =
  | 'global'
  | 'study_type'
  | 'study'
  | 'module'
  | 'team'
  | 'personal';

export type TemplateStatus = 'draft' | 'in_review' | 'published' | 'archived';

export type ReviewMode = 'sequential' | 'parallel';

export type TemplateReviewer = {
  user_id: string;
  user_name?: string;
  stage: number;
  status?: 'pending' | 'approved' | 'rejected';
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
  created_by: string;
  author_name?: string | null;
  status: TemplateStatus;
  version: number;
  review_stages: number;
  review_mode: ReviewMode;
  reviewers?: TemplateReviewer[];
  document_reviewers?: string[];
  has_review_comments?: boolean;
  /** Alineado con `TemplatePolicy::clone` en el servidor. */
  can_clone?: boolean;
  created_at?: string;
  updated_at?: string;
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
