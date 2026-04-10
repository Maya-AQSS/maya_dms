export interface Document {
  id: string;
  title: string;
  status: 'published' | 'draft' | 'in_review';
  study_id: string;
  course_module_id: string | null;
  study_name: string;
  module_name: string;
  updated_at: string;
}
