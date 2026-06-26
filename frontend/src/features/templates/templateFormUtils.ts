import type { Template, TemplateVisibilityLevel } from '../../types/templates';

export function academicScopeApplies(visibility: TemplateVisibilityLevel): boolean {
  return visibility !== 'personal' && visibility !== 'global';
}

export function initialTemplateAcademicField(
  visibility: TemplateVisibilityLevel | undefined,
  value: string | null | undefined,
): string {
  if (!academicScopeApplies(visibility ?? 'personal')) {
    return '';
  }

  return value || '';
}

export type TemplateAcademicFormValues = {
  studyTypeId: string;
  studyId: string;
  moduleId: string;
  teamId: string;
};

export function templateAcademicPayload(
  visibility: TemplateVisibilityLevel,
  values: TemplateAcademicFormValues,
): {
  study_type_id: string | null;
  study_id: string | null;
  module_id: string | null;
  team_id: string | null;
} {
  if (!academicScopeApplies(visibility)) {
    return {
      study_type_id: null,
      study_id: null,
      module_id: null,
      team_id: null,
    };
  }

  return {
    study_type_id: values.studyTypeId || null,
    study_id: values.studyId || null,
    module_id: values.moduleId || null,
    team_id: values.teamId || null,
  };
}

export const emptyTemplateAcademicFormValues: TemplateAcademicFormValues = {
  studyTypeId: '',
  studyId: '',
  moduleId: '',
  teamId: '',
};

export function isoToDatetimeLocal(iso: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function datetimeLocalToIso(s: string): string | null {
  const t = s.trim();
  if (!t) return null;
  const d = new Date(t);
  if (Number.isNaN(d.getTime())) return null;
  return d.toISOString();
}

export type TemplateEditFields = {
  name: string;
  description: string;
  visibilityLevel: TemplateVisibilityLevel;
  deliveryDeadline: string;
  documentDeliveryDeadline: string;
  studyTypeId: string;
  studyId: string;
  moduleId: string;
  teamId: string;
  status: Template['status'];
  reviewStages: string;
  reviewMode: Template['review_mode'];
};

export function templateEditIsDirty(t: Template, fields: TemplateEditFields): boolean {
  const descDraftNorm = fields.description.trim();
  const descStoredNorm = (t.description ?? '').trim();
  if (descDraftNorm !== descStoredNorm) return true;
  if (fields.name.trim() !== t.name) return true;
  if (fields.visibilityLevel !== t.visibility_level) return true;
  if (fields.deliveryDeadline !== isoToDatetimeLocal(t.delivery_deadline)) return true;
  const docDeadlineIso = t.document_delivery_deadline
    ? t.document_delivery_deadline.split('T')[0]
    : '';
  if (fields.documentDeliveryDeadline !== docDeadlineIso) return true;
  if (fields.studyTypeId.trim() !== (t.study_type_id ?? '')) return true;
  if (fields.studyId.trim() !== (t.study_id ?? '')) return true;
  if (fields.moduleId.trim() !== (t.module_id ?? '')) return true;
  if (fields.teamId.trim() !== (t.team_id ?? '')) return true;
  if (fields.status !== t.status) return true;
  if ((Number.parseInt(fields.reviewStages, 10) || 0) !== t.review_stages) return true;
  if (fields.reviewMode !== t.review_mode) return true;
  return false;
}
