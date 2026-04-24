import { apiGetJson } from './http';

export type TemplateReviewInboxItem = {
  template_id: string;
  title: string;
  author_id: string;
  /** Nombre del autor si existe fila en `users`; si no, el front usa `author_id`. */
  author_name?: string | null;
  delivery_deadline: string | null;
  days_remaining: number | null;
  status: string;
  review_stage: number;
};

export type DocumentReviewInboxItem = {
  document_id: string;
  review_id: string;
  title: string;
  owner_id: string;
  /** Nombre del titular si existe fila en `users`; si no, el front usa `owner_id`. */
  owner_name?: string | null;
  delivery_deadline: string | null;
  days_remaining: number | null;
  status: string;
  review_stage: number;
  review_mode: string;
};

export type DashboardPayload = {
  stats: unknown[];
  recent_documents: unknown[];
  template_review_inbox: TemplateReviewInboxItem[];
  document_review_inbox: DocumentReviewInboxItem[];
};

type DashboardResponse = {
  data: DashboardPayload;
};

/** GET /api/v1/dashboard */
export async function fetchDashboard(): Promise<DashboardPayload> {
  const response = await apiGetJson<DashboardResponse>('dashboard');
  return response.data;
}
