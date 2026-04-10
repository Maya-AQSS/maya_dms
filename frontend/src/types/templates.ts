/** Valores de visibility_level en la API (alineados con el backend). */
export type TemplateVisibilityLevel =
  | 'global'
  | 'study_type'
  | 'study'
  | 'module'
  | 'group'
  | 'personal';

export type TemplateStatus = 'draft' | 'published' | 'archived';

export type ReviewMode = 'sequential' | 'parallel';

export type Template = {
  id: string;
  name: string;
  description: string | null;
  visibility_level: TemplateVisibilityLevel;
  delivery_deadline: string | null;
  study_type_id: string | null;
  study_id: string | null;
  module_id: string | null;
  group_id: string | null;
  organization_id: string | null;
  created_by: string;
  status: TemplateStatus;
  version: number;
  review_stages: number;
  review_mode: ReviewMode;
  created_at?: string;
  updated_at?: string;
};

export type TemplatesListMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type TemplatesListResponse = {
  data: Template[];
  meta: TemplatesListMeta;
};

export type TemplateListFilters = {
  visibility_level?: string;
  status?: string;
  study_type_id?: string;
  study_id?: string;
  module_id?: string;
  group_id?: string;
  page?: number;
  per_page?: number;
};
