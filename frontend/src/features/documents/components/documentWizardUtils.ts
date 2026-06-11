import type { DocumentStatus } from '../../../types/documents';
import type { DocumentReview } from '../../../api/documents';
import type { Template } from '../../../types/templates';

export type Step = 'properties' | 'migration' | 'blocks' | 'summary';
export type SummaryConfirmAction = 'save' | 'submit' | null;
export type BlockViewTab = 'content' | 'description';
export type ReviewModeView = 'sequential' | 'parallel';
export type VisibilityRuleMode =
  | 'global'
  | 'study_type'
  | 'study'
  | 'module'
  | 'team'
  | 'personal'
  | 'unknown';
export type ReviewerView = {
  id: string;
  name: string;
  resolved: boolean;
};

export const DOCUMENT_STATUS_LABELS: Record<DocumentStatus, string> = {
  draft: 'Borrador',
  in_review: 'En revisión',
  published: 'Publicado',
  rejected: 'Rechazado',
  archived: 'Archivado',
};

export function dateIsoToInput(value: string | null | undefined): string {
  if (!value) return '';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '';
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

export function validationSuccessBannerMessage(
  updated: { title: string; status: DocumentStatus },
  action: 'approve' | 'reject',
): string {
  if (action === 'reject') {
    return 'Rechazo registrado. El documento ha vuelto a borrador para que el titular pueda corregirlo.';
  }
  if (updated.status === 'published') {
    return `Validación realizada. El documento «${updated.title}» ha sido publicado.`;
  }
  return 'Validación realizada. Este documento se ha pasado al siguiente validador.';
}

export function effectiveDocumentReviewMode(
  template: Pick<Template, 'review_mode' | 'document_review_mode'>,
): ReviewModeView {
  const mode = template.document_review_mode ?? template.review_mode;

  return mode === 'sequential' ? 'sequential' : 'parallel';
}

export function pickActionableDocumentReview(
  reviews: DocumentReview[],
  reviewerUserId: string,
  reviewMode: 'sequential' | 'parallel',
): DocumentReview | null {
  const pending = reviews.filter((r) => r.status === 'pending');
  if (pending.length === 0) return null;
  const mine = pending.filter((r) => r.reviewer_id === reviewerUserId);
  if (mine.length === 0) return null;
  if (reviewMode !== 'sequential') return mine[0] ?? null;
  const minStage = Math.min(...pending.map((r) => r.stage));
  return mine.find((r) => r.stage === minStage) ?? null;
}

export function isUuidLike(value: string | null | undefined): value is string {
  if (!value) return false;
  return /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(
    value.trim(),
  );
}
