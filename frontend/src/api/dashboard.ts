import { apiGetJson } from './http';

export type TemplateReviewInboxItem = {
  template_id: string;
  title: string;
  author_id: string;
  delivery_deadline: string | null;
  days_remaining: number | null;
  status: string;
  review_stage: number;
};

export type DashboardPayload = {
  stats: unknown[];
  recent_documents: unknown[];
  template_review_inbox: TemplateReviewInboxItem[];
};

type DashboardResponse = {
  data: DashboardPayload;
};

/** GET /api/v1/dashboard */
export async function fetchDashboard(): Promise<DashboardPayload> {
  const response = await apiGetJson<DashboardResponse>('dashboard');
  return response.data;
}
