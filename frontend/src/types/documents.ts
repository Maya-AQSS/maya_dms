export type DocumentStatus = 'draft' | 'in_review' | 'published';

export type Document = {
  id: string;
  template_id: string;
  template_version_id: string | null;
  title: string;
  study_type_id: string | null;
  study_id: string | null;
  module_id: string | null;
  created_by: string;
  owner_id: string;
  status: DocumentStatus;
  current_version: number;
  submitted_at: string | null;
  published_at: string | null;
  created_at?: string;
  updated_at?: string;
};
