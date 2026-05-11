import {
  lazy,
  Suspense,
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ChangeEvent,
  type ReactNode,
} from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import {
  approveDocumentReview,
  createDocument,
  deleteDocumentBlock,
  fetchDocument,
  fetchDocumentReviews,
  rejectDocumentReview,
  submitDocumentForReview,
  updateDocument,
  updateDocumentBlock,
  type DocumentReview,
} from '../../../api/documents';
import { ApiHttpError, apiFetchJson } from '../../../api/http';
import { fetchProcesses } from '../../../api/processes';
import { fetchTemplate } from '../../../api/templates';
import { BlockCommentsCard } from '../../templates/components/BlockCommentsCard';
import type { BlockComment } from '../../templates/components/BlockCommentsCard';
import { fetchMe, searchDocumentReviewerCandidates, searchUsers, type UserTeam } from '../../../api/users';
import { useAutoSave } from '../../../hooks/useAutoSave';
import { useDarkMode } from '@maya/shared-layout-react';
import type { DocumentDetail, DocumentDisplayBlock, DocumentStatus } from '../../../types/documents';
import { useHierarchy } from '../../hierarchy';
import type { Template } from '../../../types/templates';
import { BLOCK_UI_STATE_CONFIG, blockToUiState } from '../../templates/blockUiState';
import { normalizeBlockContentForEditor } from '../lib/normalizeBlockContent';
import { BlockContentHtml } from '../../templates/components/BlockContentHtml';
import { visibilityLabel } from '../../templates/constants';
import {
  Button,
  ConfirmDialog,
  DatePicker,
  ErrorBoundary,
  FieldLabel,
  Select,
  TextArea,
  TextInput,
} from '@maya/shared-ui-react';
import { WizardShell, type WizardStepDef } from '../../../components/wizard/WizardShell';
import { BlockListItem } from '../../blocks-ui/BlockListItem';

const BlockNoteEditorPanel = lazy(() =>
  import('../../templates/components/BlockNoteEditorPanel').then(
    (m) => ({ default: m.BlockNoteEditorPanel }),
  )
);

type Step = 'properties' | 'blocks' | 'summary';
type SummaryConfirmAction = 'save' | 'submit' | null;
type BlockViewTab = 'content' | 'description';
type ReviewModeView = 'sequential' | 'parallel';
type VisibilityRuleMode = 'global' | 'study_type' | 'study' | 'module' | 'team' | 'personal' | 'unknown';
type ReviewerView = {
  id: string;
  name: string;
  resolved: boolean;
};

const DOCUMENT_STATUS_LABELS: Record<DocumentStatus, string> = {
  draft: 'Borrador',
  in_review: 'En revisión',
  published: 'Publicado',
};

/** Alineado con `RejectDocumentReviewRequest` (backend): motivo obligatorio no trivial. */
const DOCUMENT_REJECT_REASON_MIN_LEN = 5;

/**
 * Descripción de bloque: el backend puede enviar string, JSON string u objeto BlockNote (`{ type: 'doc', content }`).
 * Renderizar un objeto dentro de `<p>` rompe React (pantalla en blanco).
 */
function DocumentBlockDescriptionView({ description }: { description: unknown }) {
  if (description === null || description === undefined || description === '') {
    return null;
  }

  const wrapProse = (inner: ReactNode) => (
    <div className="prose prose-sm dark:prose-invert max-w-none">{inner}</div>
  );

  if (typeof description === 'string') {
    try {
      const parsed: unknown = JSON.parse(description);
      if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
        const doc = parsed as { type?: string; content?: unknown };
        if (doc.type === 'doc' && Array.isArray(doc.content)) {
          return wrapProse(<BlockContentHtml content={doc.content as unknown[]} />);
        }
      }
      if (Array.isArray(parsed)) {
        return wrapProse(<BlockContentHtml content={parsed as unknown[]} />);
      }
    } catch {
      return (
        <p className="text-sm text-text-secondary dark:text-text-dark-secondary leading-relaxed whitespace-pre-wrap">
          {description}
        </p>
      );
    }
    return (
      <p className="text-sm text-text-secondary dark:text-text-dark-secondary leading-relaxed whitespace-pre-wrap">
        {description}
      </p>
    );
  }

  if (Array.isArray(description)) {
    return wrapProse(<BlockContentHtml content={description as unknown[]} />);
  }

  if (typeof description === 'object') {
    const doc = description as { type?: string; content?: unknown };
    if (doc.type === 'doc' && Array.isArray(doc.content)) {
      return wrapProse(<BlockContentHtml content={doc.content as unknown[]} />);
    }
    return wrapProse(<BlockContentHtml content={[description] as unknown[]} />);
  }

  return (
    <p className="text-sm text-text-secondary dark:text-text-dark-secondary leading-relaxed whitespace-pre-wrap">
      {String(description)}
    </p>
  );
}

function DocSummaryRow({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex flex-col py-1.5 border-b border-ui-border dark:border-ui-dark-border/30 last:border-0">
      <dt className="text-xs font-bold uppercase tracking-wider text-text-secondary dark:text-text-dark-secondary">
        {label}
      </dt>
      <dd className="mt-0.5 text-xs font-medium text-text-primary dark:text-text-dark-primary">
        {value || <span className="text-text-muted italic">—</span>}
      </dd>
    </div>
  );
}

function dateIsoToInput(value: string | null | undefined): string {
  if (!value) return '';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '';
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function blockEditorContent(block: DocumentDisplayBlock): unknown[] {
  const fromDoc = normalizeBlockContentForEditor(block.content);
  if (fromDoc.length > 0) {
    return fromDoc;
  }
  return normalizeBlockContentForEditor(block.default_content);
}

function validationSuccessBannerMessage(
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

function pickActionableDocumentReview(
  reviews: DocumentReview[],
  reviewerUserId: string,
  reviewMode: 'sequential' | 'parallel',
): DocumentReview | null {
  const pending = reviews.filter((r) => r.status === 'pending');
  if (pending.length === 0) return null;
  const mine = pending.filter((r) => r.reviewer_id === reviewerUserId);
  if (mine.length === 0) return null;
  if (reviewMode !== 'sequential') {
    return mine[0] ?? null;
  }
  const minStage = Math.min(...pending.map((r) => r.stage));
  return mine.find((r) => r.stage === minStage) ?? null;
}

type Props = {
  documentId?: string | null;
  templateId?: string | null;
  mode?: 'edit' | 'validate';
};

function isUuidLike(value: string | null | undefined): value is string {
  if (!value) return false;
  return /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(
    value.trim(),
  );
}

/**
 * Asistente de edición de documento (3 pasos, sin usuarios/validadores).
 * Reutiliza estética y piezas de plantillas (BlockNote, preview HTML) sin acoplar al flujo de TemplateWizard.
 */
export function DocumentWizard({ documentId, templateId, mode = 'edit' }: Props) {
  const navigate = useNavigate();
  const location = useLocation();
  const { isDark } = useDarkMode();

  const [step, setStep] = useState<Step>('properties');
  const [completedSteps, setCompletedSteps] = useState<Step[]>([]);

  const [detail, setDetail] = useState<DocumentDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  const [title, setTitle] = useState('');
  const [deliveryDeadline, setDeliveryDeadline] = useState('');
  const [studyTypeId, setStudyTypeId] = useState('');
  const [studyId, setStudyId] = useState('');
  const [moduleId, setModuleId] = useState('');
  const [teamId, setTeamId] = useState('');
  const [availableTeams, setAvailableTeams] = useState<UserTeam[]>([]);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const [submittingForReview, setSubmittingForReview] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  const [activeBlockKey, setActiveBlockKey] = useState<string | null>(null);
  const [summaryBlockKey, setSummaryBlockKey] = useState<string | null>(null);
  const [summaryBlockTab, setSummaryBlockTab] = useState<BlockViewTab>('content');
  const [blockSaveError, setBlockSaveError] = useState<string | null>(null);
  const [summaryError, setSummaryError] = useState<string | null>(null);
  const [documentReviewers, setDocumentReviewers] = useState<ReviewerView[]>([]);
  /** IDs de `template_document_reviewers` (vacío si la plantilla no define pool de documento). */
  const [, setDocumentReviewerPoolIds] = useState<string[]>([]);
  /** IDs de `template_reviewers` (revisores normativos; el backend los usa si no hay pool de documento). */
  const [, setTemplateReviewerPoolIds] = useState<string[]>([]);
  const [reviewerListKind, setReviewerListKind] = useState<'document' | 'template_fallback' | 'none'>('none');
  const [documentReviewMode, setDocumentReviewMode] = useState<ReviewModeView>('parallel');
  const [summaryConfirmAction, setSummaryConfirmAction] = useState<SummaryConfirmAction>(null);

  const [template, setTemplate] = useState<Template | null>(null);
  const [loadingTemplate, setLoadingTemplate] = useState(false);

  const { hierarchy, loading: hierarchyLoading } = useHierarchy();
  const [blockViewTab, setBlockViewTab] = useState<BlockViewTab>('content');
  const [validationReviewLoading, setValidationReviewLoading] = useState(false);
  const [validationSetupError, setValidationSetupError] = useState<string | null>(null);
  const [actionableReviewId, setActionableReviewId] = useState<string | null>(null);
  const [validateConfirm, setValidateConfirm] = useState<null | 'approve' | 'reject'>(null);
  const [validationActionLoading, setValidationActionLoading] = useState(false);
  const [validationModalError, setValidationModalError] = useState<string | null>(null);
  const [rejectReason, setRejectReason] = useState('');
  const [localContent, setLocalContent] = useState<unknown>(null);
  const [showDeleteBlockConfirm, setShowDeleteBlockConfirm] = useState(false);
  const [processSubtitle, setProcessSubtitle] = useState<string | null>(null);
  const activeBlockRef = useRef<DocumentDisplayBlock | null>(null);
  const [isEditorFullscreen, setIsEditorFullscreen] = useState(false);

  // Review comments for creator-edit mode (mirrors TemplateWizard + WizardStep2Blocks)
  const [reviewComments, setReviewComments] = useState<BlockComment[]>([]);
  const [showDocumentCommentPanel, setShowDocumentCommentPanel] = useState(true);

  const handleEditorFullscreenChange = useCallback((v: boolean) => {
    setIsEditorFullscreen(v);
    document.documentElement.classList.toggle('editor-fullscreen', v);
  }, []);

  useEffect(() => {
    return () => document.documentElement.classList.remove('editor-fullscreen');
  }, []);

  const isValidateMode = mode === 'validate';
  const isDraft = !detail || detail.status === 'draft';
  const locationState = location.state as {
    step?: string;
    processId?: string;
    moduleId?: string;
    fromTemplateSelection?: boolean;
    templateVersionId?: string | null;
  } | null;
  const returnToSummary = locationState?.step === 'summary';
  const forcePropertiesStep = locationState?.step === 'properties';
  const locationProcessId = locationState?.processId;
  const locationModuleId = locationState?.moduleId;
  const fromTemplateSelection = locationState?.fromTemplateSelection === true;
  const selectedTemplateVersionId = locationState?.templateVersionId ?? null;
  const selectedTemplateVersionUuid = isUuidLike(selectedTemplateVersionId)
    ? selectedTemplateVersionId
    : null;
  const processBackTo = useMemo(() => {
    const effectiveProcessId = locationProcessId ?? template?.process_id ?? null;
    return effectiveProcessId ? `/procesos/${effectiveProcessId}` : '/dashboard';
  }, [locationProcessId, template?.process_id]);

  const reload = useCallback(async () => {
    if (!documentId) return;
    setLoading(true);
    setLoadError(null);
    try {
      const data = await fetchDocument(documentId);
      setDetail(data);
      setTitle(data.title);
      setDeliveryDeadline(dateIsoToInput(data.delivery_deadline));
      setStudyTypeId(data.study_type_id ?? '');
      setStudyId(data.study_id ?? '');
      setModuleId(data.module_id ?? '');
      setTeamId(data.team_id ?? '');
      setActiveBlockKey((prev: string | null) => {
        if (prev) return prev;
        if (data.blocks.length === 0) return null;
        const first = data.blocks[0];
        return first.document_block_id ?? first.template_block_id;
      });
    } catch (e) {
      setLoadError(e instanceof Error ? e.message : 'No se pudo cargar el documento.');
      setDetail(null);
    } finally {
      setLoading(false);
    }
  }, [documentId]);

  // Load review comments when document has unresolved ones
  useEffect(() => {
    if (!documentId || !detail?.has_review_comments) return;
    let cancelled = false;
    void apiFetchJson<{ data: BlockComment[] }>(`documents/${documentId}/comments`)
      .then((res) => { if (!cancelled) setReviewComments(res.data); })
      .catch(() => {});
    return () => { cancelled = true; };
  }, [documentId, detail?.has_review_comments]);

  const handleReviewReply = useCallback(async (parentCommentId: string, body: string) => {
    if (!documentId) return;
    const parent = reviewComments.find(c => c.id === parentCommentId);
    const res = await apiFetchJson<{ data: BlockComment }>(`documents/${documentId}/comments`, {
      method: 'POST',
      body: { body, parent_id: parentCommentId, blockable_id: parent?.blockable_id ?? null },
    });
    setReviewComments(prev => [...prev, res.data]);
  }, [documentId, reviewComments]);

  const refreshDetail = useCallback(async () => {
    if (!documentId) return;
    try {
      const data = await fetchDocument(documentId);
      setDetail(data);
      setTitle(data.title);
      setDeliveryDeadline(dateIsoToInput(data.delivery_deadline));
      setStudyTypeId(data.study_type_id ?? '');
      setStudyId(data.study_id ?? '');
      setModuleId(data.module_id ?? '');
      setTeamId(data.team_id ?? '');
      setActiveBlockKey((prev: string | null) => {
        if (prev && data.blocks.some((b) => (b.document_block_id ?? b.template_block_id) === prev)) {
          return prev;
        }
        if (data.blocks.length === 0) return null;
        const first = data.blocks[0];
        return first.document_block_id ?? first.template_block_id;
      });
    } catch (e) {
      setBlockSaveError(e instanceof Error ? e.message : 'No se pudo actualizar el documento.');
    }
  }, [documentId]);

  useEffect(() => {
    if (!templateId || documentId) {
      setLoadingTemplate(false);
      return;
    }
    let cancelled = false;
    const load = async () => {
      try {
        setLoadingTemplate(true);
        const res = await fetchTemplate(templateId);
        if (!cancelled) {
          setTemplate(res.data);
        }
      } catch (e) {
        if (!cancelled) {
        }
      } finally {
        if (!cancelled) setLoadingTemplate(false);
      }
    };
    void load();
    return () => { cancelled = true; };
  }, [templateId, documentId]);

  useEffect(() => {
    void reload();
  }, [reload]);

  useEffect(() => {
    let cancelled = false;
    void (async () => {
      try {
        const me = await fetchMe();
        if (!cancelled) setAvailableTeams(me.data.teams ?? []);
      } catch {
        if (!cancelled) setAvailableTeams([]);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    setFormError(null);
    setBlockSaveError(null);
    setValidationSetupError(null);
    setActionableReviewId(null);
    setValidateConfirm(null);
    setValidationModalError(null);
    setRejectReason('');
    if (mode === 'validate') {
      setStep('summary');
      setCompletedSteps(['properties', 'blocks', 'summary']);
    } else {
      setStep('properties');
      setCompletedSteps([]);
    }
  }, [documentId, mode]);

  useEffect(() => {
    if (mode === 'validate') return;
    if (!detail) {
      // Si estamos creando (no hay detail) y viene moduleId por state, lo pre-rellenamos
      const state = location.state as { moduleId?: string } | null;
      if (state?.moduleId) {
        setModuleId(state.moduleId);
      }
      return;
    }
    if (detail.status !== 'draft') {
      setCompletedSteps(['properties', 'blocks']);
      setStep('blocks');
      return;
    }
    if (forcePropertiesStep) {
      setStep('properties');
      setCompletedSteps([]);
      return;
    }
    // Si es borrador pero le falta la fecha límite, forzamos paso de propiedades
    if (!detail.delivery_deadline) {
      setStep('properties');
      setCompletedSteps([]);
    } else {
      setCompletedSteps(['properties']);
      setStep('blocks');
    }
  }, [detail?.id, detail?.status, detail?.delivery_deadline, forcePropertiesStep, mode]);

  useEffect(() => {
    if (mode === 'validate') return;
    if (!detail || !returnToSummary) return;
    setCompletedSteps(['properties', 'blocks']);
    setStep('summary');
  }, [detail, returnToSummary, mode]);

  useEffect(() => {
    if (!isValidateMode) {
      setValidationReviewLoading(false);
      setValidationSetupError(null);
      setActionableReviewId(null);
      return;
    }
    if (!detail || detail.status !== 'in_review') {
      return;
    }
    let cancelled = false;
    setValidationReviewLoading(true);
    setValidationSetupError(null);
    setActionableReviewId(null);
    void (async () => {
      try {
        const [reviews, meRes, templateResp] = await Promise.all([
          fetchDocumentReviews(detail.id),
          fetchMe(),
          fetchTemplate(detail.template_id),
        ]);
        if (cancelled) return;
        const userId = meRes.data.id;
        const reviewMode = templateResp.data.review_mode === 'sequential' ? 'sequential' : 'parallel';
        const actionable = pickActionableDocumentReview(reviews, userId, reviewMode);
        if (!actionable) {
          setValidationSetupError(
            'No tienes una revisión pendiente que puedas tramitar para este documento.',
          );
          setActionableReviewId(null);
        } else {
          setActionableReviewId(actionable.id);
          setValidationSetupError(null);
        }
      } catch (e) {
        if (!cancelled) {
          setValidationSetupError(
            e instanceof ApiHttpError ? e.message : 'No se pudo cargar la información de validación.',
          );
          setActionableReviewId(null);
        }
      } finally {
        if (!cancelled) setValidationReviewLoading(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [isValidateMode, detail?.id, detail?.status]);

  // Auto-selección si solo hay una opción disponible
  useEffect(() => {
    if (documentId || hierarchyLoading || hierarchy.length === 0 || studyTypeId) return;
    if (hierarchy.length === 1) setStudyTypeId(String(hierarchy[0].id));
  }, [documentId, hierarchy, hierarchyLoading, studyTypeId]);

  useEffect(() => {
    if (documentId || !studyTypeId || studyId) return;
    const typeNode = hierarchy.find((t: any) => String(t.id) === studyTypeId);
    if (!typeNode) return;
    if ((typeNode.studies ?? []).length === 1) setStudyId(String(typeNode.studies[0].id));
  }, [documentId, hierarchy, studyTypeId, studyId]);

  useEffect(() => {
    if (documentId || !studyId || moduleId) return;
    const allStudiesFlat = hierarchy.flatMap((t: any) => t.studies ?? []);
    const studyNode = allStudiesFlat.find((s: any) => String(s.id) === studyId);
    if (!studyNode) return;
    if ((studyNode.course_modules ?? []).length === 1) setModuleId(String(studyNode.course_modules[0].id));
  }, [documentId, hierarchy, studyId, moduleId]);

  const sortedBlocks = useMemo(
    () => [...(detail?.blocks ?? [])].sort((a: DocumentDisplayBlock, b: DocumentDisplayBlock) => a.sort_order - b.sort_order),
    [detail?.blocks],
  );

  const activeBlock = useMemo(
    () => sortedBlocks.find((b: DocumentDisplayBlock) => (b.document_block_id ?? b.template_block_id) === activeBlockKey) ?? null,
    [sortedBlocks, activeBlockKey],
  );
  const activeBlockUiState = activeBlock ? blockToUiState(activeBlock) : null;

  const allStudies = hierarchy.flatMap((t) => t.studies);
  const selectedTemplateVisibility = template?.visibility_level ?? detail?.visibility_level ?? null;
  const visibilityRule: VisibilityRuleMode = selectedTemplateVisibility ?? 'unknown';
  const templateStudyTypeId = template?.study_type_id ?? null;
  const templateStudyId = template?.study_id ?? null;
  const templateModuleId = template?.module_id ?? null;

  const selectedStudyNode = allStudies.find((s: any) => String(s.id) === studyId) ?? null;

  const filteredStudies = useMemo(() => {
    if (!studyTypeId) return [];
    const byType = (hierarchy.find((t: any) => String(t.id) === studyTypeId)?.studies ?? []) as any[];
    if (visibilityRule === 'study' || visibilityRule === 'module') {
      return byType.filter((s: any) => String(s.id) === String(templateStudyId ?? ''));
    }
    return byType;
  }, [studyTypeId, hierarchy, visibilityRule, templateStudyId]);

  const filteredModules = useMemo(() => {
    if (!studyId) return [];
    const byStudy = (allStudies.find((s: any) => String(s.id) === studyId)?.course_modules ?? []) as any[];
    if (visibilityRule === 'module') {
      return byStudy.filter((m: any) => String(m.id) === String(templateModuleId ?? ''));
    }
    return byStudy;
  }, [studyId, allStudies, visibilityRule, templateModuleId]);

  const studyTypeEditable = visibilityRule === 'global' || visibilityRule === 'unknown';
  const studyEditable = visibilityRule === 'global' || visibilityRule === 'study_type' || visibilityRule === 'unknown';
  const moduleEditable = visibilityRule === 'global' || visibilityRule === 'study_type' || visibilityRule === 'study' || visibilityRule === 'unknown';
  const teamEditable = visibilityRule === 'global';
  const fixedTeamId = visibilityRule === 'team' ? (template?.team_id ?? '') : '';
  const templateScopeLabel = useMemo(() => {
    if (!template) return null;
    const studyType = hierarchy.find((t: any) => String(t.id) === String(template.study_type_id ?? ''));
    const studies = (studyType?.studies ?? hierarchy.flatMap((t: any) => t.studies ?? [])) as any[];
    const study = studies.find((s: any) => String(s.id) === String(template.study_id ?? ''));
    const modules = (study?.course_modules ?? []) as any[];
    const module = modules.find((m: any) => String(m.id) === String(template.module_id ?? ''));
    if (template.visibility_level === 'study_type') return studyType?.name ?? null;
    if (template.visibility_level === 'study') return study?.name ?? null;
    if (template.visibility_level === 'module') return module?.name ?? null;
    if (template.visibility_level === 'team') return template.team?.name ?? null;
    return null;
  }, [template, hierarchy]);

  const requireStudyType = visibilityRule === 'study_type' || visibilityRule === 'study' || visibilityRule === 'module';
  const requireStudy = visibilityRule === 'study' || visibilityRule === 'module';
  const requireModule = visibilityRule === 'module';
  const isGlobalAcademicMode = visibilityRule !== 'global' ? true : (!teamId);

  useEffect(() => {
    if (!template) return;
    if (visibilityRule === 'team' || visibilityRule === 'personal') {
      setStudyTypeId('');
      setStudyId('');
      setModuleId('');
      if (visibilityRule === 'team' && template.team_id) {
        setTeamId(template.team_id);
      }
      return;
    }
    if (visibilityRule === 'study_type' && templateStudyTypeId) {
      setStudyTypeId(templateStudyTypeId);
      if (studyId && selectedStudyNode && String(selectedStudyNode.study_type_id) !== String(templateStudyTypeId)) {
        setStudyId('');
        setModuleId('');
      }
      return;
    }
    if (visibilityRule === 'study' && templateStudyId) {
      const stFromStudy = allStudies.find((s: any) => String(s.id) === String(templateStudyId))?.study_type_id;
      if (stFromStudy) setStudyTypeId(String(stFromStudy));
      setStudyId(String(templateStudyId));
      if (moduleId) {
        const moduleInStudy = (allStudies.find((s: any) => String(s.id) === String(templateStudyId))?.course_modules ?? [])
          .some((m: any) => String(m.id) === String(moduleId));
        if (!moduleInStudy) setModuleId('');
      }
      return;
    }
    if (visibilityRule === 'module' && templateModuleId) {
      const owningStudy = allStudies.find((s: any) => (s.course_modules ?? []).some((m: any) => String(m.id) === String(templateModuleId))) ?? null;
      if (owningStudy) {
        setStudyTypeId(String(owningStudy.study_type_id));
        setStudyId(String(owningStudy.id));
      }
      setModuleId(String(templateModuleId));
    }
  }, [
    template,
    visibilityRule,
    templateStudyTypeId,
    templateStudyId,
    templateModuleId,
    allStudies,
    studyId,
    moduleId,
    selectedStudyNode,
  ]);

  const selectedSummaryBlock = useMemo(
    () =>
      sortedBlocks.find((b: DocumentDisplayBlock) => (b.document_block_id ?? b.template_block_id) === summaryBlockKey) ??
      sortedBlocks[0] ??
      null,
    [sortedBlocks, summaryBlockKey],
  );

  useEffect(() => {
    if (step !== 'summary' || sortedBlocks.length === 0) {
      return;
    }
    setSummaryBlockKey((prev: string | null) => {
      if (prev && sortedBlocks.some((b: DocumentDisplayBlock) => (b.document_block_id ?? b.template_block_id) === prev)) {
        return prev;
      }
      const first = sortedBlocks[0];
      return first.document_block_id ?? first.template_block_id;
    });
  }, [step, sortedBlocks]);

  useEffect(() => {
    setSummaryBlockTab('content');
  }, [summaryBlockKey]);

  useEffect(() => {
    if (step !== 'summary' || !detail) {
      return;
    }
    let cancelled = false;
    const loadDocumentReviewers = async () => {
      setSummaryError(null);
      setDocumentReviewerPoolIds([]);
      setTemplateReviewerPoolIds([]);
      setReviewerListKind('none');
      try {
        const [templateResp, usersResp] = await Promise.all([
          fetchTemplate(detail.template_id),
          searchDocumentReviewerCandidates(),
        ]);
        if (cancelled) return;
        setDocumentReviewMode(templateResp.data.review_mode ?? 'parallel');

        const docIds = templateResp.data.document_reviewers ?? [];
        const tplRows = templateResp.data.reviewers ?? [];
        const tplUserIds = tplRows.map((r: any) => r.user_id);
        setDocumentReviewerPoolIds(docIds);
        setTemplateReviewerPoolIds(tplUserIds);

        const displayIds = docIds.length > 0 ? docIds : tplUserIds;
        if (docIds.length > 0) {
          setReviewerListKind('document');
        } else if (tplUserIds.length > 0) {
          setReviewerListKind('template_fallback');
        } else {
          setReviewerListKind('none');
        }

        if (displayIds.length === 0) {
          setDocumentReviewers([]);
          return;
        }

        const byId = new Map(usersResp.data.map((u: any) => [u.id, u.name] as const));
        const initial = displayIds.map((id: string) => ({
          id,
          name: byId.get(id) ?? '',
          resolved: byId.has(id),
        }));
        const missing = initial.filter((r) => !r.resolved);
        if (missing.length === 0) {
          setDocumentReviewers(initial);
          return;
        }
        const lookedUp = await Promise.all(
          missing.map(async (r) => {
            try {
              const resp = await searchUsers(r.id);
              const exact = resp.data.find((u) => u.id === r.id);
              if (exact?.name) {
                return { ...r, name: exact.name, resolved: true };
              }
            } catch {
              // noop: fallback below
            }
            return {
              ...r,
              name: `Usuario no encontrado (${r.id.slice(0, 8)}...)`,
              resolved: false,
            };
          }),
        );
        const lookedUpById = new Map(lookedUp.map((r: ReviewerView) => [r.id, r] as const));
        const finalReviewers = initial.map((r: ReviewerView) => lookedUpById.get(r.id) ?? r);
        setDocumentReviewers(finalReviewers);
      } catch (e) {
        if (!cancelled) {
          setSummaryError(e instanceof Error ? e.message : 'No se pudieron cargar los validadores de documento.');
          setDocumentReviewers([]);
          setDocumentReviewerPoolIds([]);
          setTemplateReviewerPoolIds([]);
          setReviewerListKind('none');
        }
      }
    };
    void loadDocumentReviewers();
    return () => {
      cancelled = true;
    };
  }, [step, detail]);

  useEffect(() => {
    const tId = detail?.template_id || templateId;
    if (!tId) {
      setTemplate(null);
      return;
    }
    let cancelled = false;
    const loadTemplate = async () => {
      try {
        const templateResp = await fetchTemplate(tId);
        if (!cancelled) {
          setTemplate(templateResp.data);
        }
      } catch {
        if (!cancelled) {
          setTemplate(null);
        }
      }
    };
    void loadTemplate();
    return () => {
      cancelled = true;
    };
  }, [detail?.template_id, templateId]);

  useEffect(() => {
    const effectiveProcessId = template?.process_id ?? locationProcessId ?? null;
    if (!effectiveProcessId) {
      setProcessSubtitle(null);
      return;
    }
    let cancelled = false;
    void fetchProcesses()
      .then((res) => {
        if (cancelled) return;
        const selectedProcess = res.data.find((p) => p.id === effectiveProcessId) ?? null;
        if (!selectedProcess) {
          setProcessSubtitle(null);
          return;
        }
        setProcessSubtitle(`Proceso: ${selectedProcess.code} — ${selectedProcess.name}`);
      })
      .catch(() => {
        if (!cancelled) setProcessSubtitle(null);
      });
    return () => {
      cancelled = true;
    };
  }, [locationProcessId, template?.process_id]);

  const canEditBlocks = isDraft && activeBlock !== null && activeBlockUiState !== 'locked';
  const canDeleteOptionalBlock = isDraft && activeBlock !== null && activeBlockUiState === 'optional';

  useEffect(() => {
    setBlockViewTab('content');
  }, [activeBlockKey]);

  const doSave = useCallback(async () => {
    const block = activeBlockRef.current;
    if (!block || !isDraft || blockToUiState(block) === 'locked') return;
    const blockId = block.document_block_id;
    if (!blockId) return;
    setBlockSaveError(null);
    try {
      if (!documentId) return;
      await updateDocumentBlock(documentId, blockId, localContent);
      await refreshDetail();
    } catch (e) {
      const msg = e instanceof ApiHttpError ? e.message : e instanceof Error ? e.message : 'Error al guardar el bloque.';
      setBlockSaveError(msg);
      throw e;
    }
  }, [documentId, isDraft, refreshDetail, localContent]);

  const { saveStatus, triggerSave } = useAutoSave(doSave, 1500);

  useEffect(() => {
    activeBlockRef.current = activeBlock;
    if (activeBlock) {
      setLocalContent(normalizeBlockContentForEditor(activeBlock.content).length > 0
        ? activeBlock.content
        : activeBlock.default_content
      );
      setShowDocumentCommentPanel(true); // re-show comment panel on block change
    }
  }, [activeBlock]);

  const handleGoToStep = (s: Step) => {
    if (s === 'properties') setStep(s);
    else if (s === 'blocks' && completedSteps.includes('properties')) setStep(s);
    else if (s === 'summary' && completedSteps.includes('blocks')) setStep(s);
  };

  const handleContinue = async () => {
    setFormError(null);
    setErrors({});
    if (step === 'properties') {
      const newErrors: Record<string, string> = {};
      if ((requireStudyType || (visibilityRule === 'global' && isGlobalAcademicMode && (studyId || moduleId || studyTypeId))) && !studyTypeId) {
        newErrors.studyTypeId = 'Selecciona un tipo de estudio.';
      }
      if ((requireStudy || (visibilityRule === 'global' && isGlobalAcademicMode && (moduleId || studyId))) && !studyId) {
        newErrors.studyId = 'Selecciona un estudio.';
      }
      if (requireModule && !moduleId) newErrors.moduleId = 'Selecciona un módulo.';
      if (!title.trim()) newErrors.title = 'El título es obligatorio.';
      if (!deliveryDeadline) {
        newErrors.deliveryDeadline = 'La fecha de entrega es obligatoria.';
      } else {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const selected = new Date(deliveryDeadline);
        if (selected < today) {
          newErrors.deliveryDeadline = 'La fecha no puede ser anterior a hoy.';
        }
      }

      if (Object.keys(newErrors).length > 0) {
        setErrors(newErrors);
        return;
      }

      setSaving(true);
      try {
        if (!documentId) {
          // Creation Mode: Create everything in one call
          if (!templateId) throw new Error('No se puede crear un documento sin plantilla.');
          if (!template?.process_id) {
            throw new Error('La plantilla seleccionada no tiene proceso asociado.');
          }

          const created = await createDocument({
            template_id: templateId,
            process_id: template.process_id,
            ...(selectedTemplateVersionUuid ? { template_version_id: selectedTemplateVersionUuid } : {}),
            title: title.trim(),
            study_type_id: studyTypeId || undefined,
            study_id: studyId || undefined,
            module_id: moduleId || undefined,
            team_id: teamId || undefined,
            delivery_deadline: deliveryDeadline ? `${deliveryDeadline}T00:00:00Z` : null,
          });

          // Navigate to the editor route with the new ID
          navigate(`/documents/${created.id}/editor`, {
            replace: true,
            state: {
              processId: locationProcessId,
              moduleId: locationModuleId,
              fromTemplateSelection: true,
            },
          });
          setStep('blocks');
          setCompletedSteps((prev: Step[]) => (prev.includes('properties') ? prev : [...prev, 'properties']));
        } else {
          // Edit Mode: Update existing document
          const updated = await updateDocument(documentId, {
            title: title.trim(),
            delivery_deadline: deliveryDeadline ? `${deliveryDeadline}T00:00:00Z` : null,
            study_type_id: studyTypeId || undefined,
            study_id: studyId || undefined,
            module_id: moduleId || undefined,
          });

          setDetail((prev: DocumentDetail | null) => (prev ? { ...prev, ...updated, blocks: prev.blocks } : prev));
          setCompletedSteps((prev: Step[]) => Array.from(new Set([...prev, 'properties'] as Step[])));
          setStep('blocks');
        }
      } catch (e) {
        setFormError(e instanceof Error ? e.message : 'No se pudieron guardar los datos del documento.');
      } finally {
        setSaving(false);
      }
      return;
    }
    if (step === 'blocks') {
      setCompletedSteps((prev: Step[]) => Array.from(new Set([...prev, 'blocks'] as Step[])));
      setStep('summary');
      return;
    }
    if (step === 'summary') {
      navigate(processBackTo, { state: { tab: 'documents' } });
    }
  };

  const handleSubmitForReview = async () => {
    if (!detail || detail.status !== 'draft') {
      return;
    }
    setSummaryError(null);
    setSubmittingForReview(true);
    try {
      const updated = await submitDocumentForReview(detail.id);
      setDetail((prev) => (prev ? { ...prev, ...updated, blocks: prev.blocks } : prev));
      navigate(processBackTo, {
        state: { tab: 'documents', documentSubmittedForReview: true },
      });
    } catch (e) {
      setSummaryError(e instanceof Error ? e.message : 'No se pudo enviar el documento a validar.');
    } finally {
      setSubmittingForReview(false);
    }
  };

  const handleConfirmSummaryAction = async () => {
    if (summaryConfirmAction === 'save') {
      setSummaryConfirmAction(null);
      navigate(processBackTo, { state: { tab: 'documents' } });
      return;
    }
    if (summaryConfirmAction === 'submit') {
      await handleSubmitForReview();
      setSummaryConfirmAction(null);
    }
  };

  const handleApproveValidation = async () => {
    if (!documentId || !actionableReviewId) {
      setValidationModalError('Faltan datos críticos para procesar la revisión.');
      return;
    }
    setValidationModalError(null);
    setSummaryError(null);
    setValidationActionLoading(true);
    try {
      const updated = await approveDocumentReview(documentId, actionableReviewId, null);
      setValidateConfirm(null);
      navigate('/dashboard', {
        state: { documentValidationBanner: validationSuccessBannerMessage(updated, 'approve') },
      });
    } catch (e) {
      setValidationModalError(e instanceof ApiHttpError ? e.message : 'No se pudo aprobar la revisión.');
    } finally {
      setValidationActionLoading(false);
    }
  };

  const handleRejectValidation = async () => {
    if (!documentId || !actionableReviewId) {
      setValidationModalError('Faltan datos críticos para procesar la revisión.');
      return;
    }
    const reason = rejectReason.trim();
    if (reason.length < DOCUMENT_REJECT_REASON_MIN_LEN) {
      setValidationModalError(
        `Indica un motivo de rechazo de al menos ${DOCUMENT_REJECT_REASON_MIN_LEN} caracteres (obligatorio).`,
      );
      return;
    }
    setValidationModalError(null);
    setSummaryError(null);
    setValidationActionLoading(true);
    try {
      const updated = await rejectDocumentReview(documentId, actionableReviewId, reason);
      setValidateConfirm(null);
      setRejectReason('');
      navigate('/dashboard', {
        state: { documentValidationBanner: validationSuccessBannerMessage(updated, 'reject') },
      });
    } catch (e) {
      setValidationModalError(e instanceof ApiHttpError ? e.message : 'No se pudo rechazar la revisión.');
    } finally {
      setValidationActionLoading(false);
    }
  };

  const stepsData: WizardStepDef<Step>[] = [
    { id: 'properties', label: 'Propiedades', sub: 'Título y metadatos' },
    { id: 'blocks', label: 'Bloques', sub: 'Contenido de la programación' },
    { id: 'summary', label: 'Resumen', sub: 'Revisión antes de salir' },
  ];

  const validateModeStepper = (
    <div className="flex items-center justify-center px-6 py-3 bg-white dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border shrink-0">
      <p className="text-xs font-semibold text-text-secondary dark:text-text-dark-secondary text-center">
        Validación — resumen del documento
      </p>
    </div>
  );

  if (loading && !detail && !templateId) {
    return (
      <div className="p-6 text-sm text-text-muted dark:text-text-dark-muted">Cargando documento…</div>
    );
  }

  if (loadingTemplate) {
    return (
      <div className="p-6 text-sm text-text-muted dark:text-text-dark-muted">Cargando plantilla…</div>
    );
  }

  if ((loadError || !detail) && !templateId) {
    return (
      <div className="p-6 space-y-3">
        <p className="text-sm text-warning-dark dark:text-warning-light">{loadError ?? 'Documento no encontrado.'}</p>
        <Button
          type="button"
          variant="secondary"
          onClick={() =>
            navigate(isValidateMode ? '/dashboard' : processBackTo, {
              state: isValidateMode ? {} : { tab: 'documents' },
            })
          }
        >
          {isValidateMode ? 'Volver al panel' : 'Volver al listado'}
        </Button>
      </div>
    );
  }

  if (isValidateMode && (!detail || detail.status !== 'in_review')) {
    return (
      <div className="p-6 space-y-3">
        <p className="text-sm text-warning-dark dark:text-warning-light">
          Este documento no está en revisión. Solo puedes validar programaciones enviadas a revisión.
        </p>
        <Button type="button" variant="secondary" onClick={() => navigate('/dashboard')}>
          Volver al panel
        </Button>
      </div>
    );
  }

  if (isValidateMode && !validationReviewLoading && validationSetupError && !actionableReviewId) {
    return (
      <div className="p-6 space-y-3">
        <p className="text-sm text-warning-dark dark:text-warning-light">{validationSetupError}</p>
        {detail?.status === 'in_review' && (
          <Button type="button" variant="outline" size="sm" onClick={() => void reload()}>
            Actualizar estado
          </Button>
        )}
        <Button type="button" variant="secondary" onClick={() => navigate('/dashboard')}>
          Volver al panel
        </Button>
      </div>
    );
  }

  if (isValidateMode && detail && detail.status === 'in_review' && validationReviewLoading) {
    return (
      <div className="flex flex-col h-[calc(100dvh-7rem)] items-center justify-center overflow-hidden bg-ui-body dark:bg-ui-dark-bg px-6">
        <p className="text-sm text-text-muted dark:text-text-dark-muted">Cargando datos de validación…</p>
      </div>
    );
  }

  const headerActions = (
    <>
      {isValidateMode && (
        <>
          <Button
            type="button"
            variant="secondary"
            size="sm"
            disabled={!actionableReviewId || validationReviewLoading}
            onClick={() => {
              setValidationModalError(null);
              setValidateConfirm('reject');
            }}
          >
            Rechazar
          </Button>
          <Button
            type="button"
            variant="primary"
            size="sm"
            disabled={!actionableReviewId || validationReviewLoading}
            onClick={() => {
              setValidationModalError(null);
              setValidateConfirm('approve');
            }}
          >
            Aprobar
          </Button>
        </>
      )}
      {!isValidateMode && step === 'summary' && (
        <>
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={() => setSummaryConfirmAction('save')}
          >
            Guardar sin enviar
          </Button>
          <Button
            type="button"
            variant="primary"
            size="sm"
            loading={submittingForReview}
            disabled={!isDraft}
            onClick={() => setSummaryConfirmAction('submit')}
          >
            Enviar a validar
          </Button>
        </>
      )}
      {!isValidateMode && step !== 'summary' && (
        <Button
          type="button"
          variant="primary"
          size="sm"
          loading={saving}
          onClick={() => void handleContinue()}
          className="text-xs font-black uppercase tracking-widest px-6 rounded-full shadow-sm"
        >
          Continuar
        </Button>
      )}
    </>
  );

  const draftBanner = !isDraft && !isValidateMode ? (
    <p className="px-6 py-2 text-xs bg-warning-light/20 text-warning-dark dark:bg-warning-dark/20 dark:text-warning-light border-b border-warning/20">
      Este documento no está en borrador: la edición de bloques está deshabilitada; solo puedes revisar el contenido.
    </p>
  ) : null;

  const handleWizardBack = () => {
    if (isValidateMode) {
      navigate('/dashboard');
      return;
    }
    if (step === 'properties' && (!documentId || fromTemplateSelection)) {
      navigate('/documentos/nuevo', {
        state: {
          moduleId: locationModuleId,
          processId: locationProcessId,
        },
      });
      return;
    }
    if (step === 'blocks') {
      setStep('properties');
      return;
    }
    if (step === 'summary') {
      setStep('blocks');
      return;
    }
    // step === 'properties'
    const tId = detail?.template_id || templateId;
    if (tId) {
      const templatePath = selectedTemplateVersionId
        ? `/templates/${tId}?templateVersionId=${encodeURIComponent(selectedTemplateVersionId)}`
        : `/templates/${tId}`;
      navigate(templatePath, {
        state: {
          selectionMode: !documentId,
          backTo: '/documentos/nuevo',
          moduleId: locationModuleId,
          processId: locationProcessId,
          templateVersionId: selectedTemplateVersionId,
        },
      });
    } else if (documentId) {
      navigate(`/documents/${documentId}`);
    } else {
      navigate('/documentos/nuevo');
    }
  };

  return (
    <>
    <WizardShell<Step>
      title={
        detail?.title
          ? `Editando: ${detail.title}`
          : (documentId ? 'Documento' : 'Nuevo documento')
      }
      subtitle={processSubtitle}
      onBack={handleWizardBack}
      backLabel={isValidateMode ? 'Volver al panel principal' : 'Volver'}
      actions={headerActions}
      steps={stepsData}
      currentStep={step}
      completedSteps={completedSteps}
      onGoToStep={handleGoToStep}
      banner={draftBanner}
      stepperOverride={isValidateMode ? validateModeStepper : undefined}
    >
      <>
      {!isValidateMode && step === 'properties' && (
        <div className="flex-1 overflow-y-auto px-4 py-6 bg-ui-body/30 dark:bg-ui-dark-bg space-y-6">
          <div className="max-w-xl mx-auto space-y-6">
            {formError && (
              <div className="rounded-lg border border-danger/30 bg-danger/5 px-4 py-3 text-xs text-danger-dark dark:text-danger">
                {formError}
              </div>
            )}

            <div className="bg-ui-card dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm p-6 space-y-6 animate-in fade-in slide-in-from-top-1">
              {template && (
                <div>
                  <p className="text-xs font-bold uppercase tracking-wider text-text-secondary dark:text-text-dark-secondary mb-1">
                    Plantilla base
                  </p>
                  <div className="flex items-center justify-between gap-3">
                    <span className="text-sm font-semibold text-text-primary dark:text-text-dark-primary">
                      {template.name}
                    </span>
                    <button
                      type="button"
                      onClick={() => navigate('/documentos/nuevo')}
                      className="text-xs text-odoo-purple dark:text-odoo-dark-purple hover:underline cursor-pointer shrink-0"
                    >
                      Cambiar plantilla
                    </button>
                  </div>
                  <div className="mt-2 flex flex-wrap gap-2 text-xs text-text-secondary dark:text-text-dark-secondary">
                    <span className="rounded border border-ui-border dark:border-ui-dark-border px-2 py-0.5">
                      Visibilidad: {visibilityLabel(template.visibility_level)}
                    </span>
                    {templateScopeLabel && template.visibility_level !== 'team' && (
                      <span className="rounded border border-ui-border dark:border-ui-dark-border px-2 py-0.5">
                        Ámbito: {templateScopeLabel}
                      </span>
                    )}
                    {(visibilityRule === 'team' || visibilityRule === 'global') && (template.team?.name || fixedTeamId || teamId) && (
                      <span className="rounded border border-ui-border dark:border-ui-dark-border px-2 py-0.5">
                        Equipo: {template.team?.name ?? availableTeams.find((t) => t.id === (fixedTeamId || teamId))?.name ?? 'Asignado'}
                      </span>
                    )}
                  </div>
                </div>
              )}

              <div className="border-t border-ui-border dark:border-ui-dark-border pt-5 space-y-4">
                <p className="text-xs text-text-muted dark:text-text-dark-muted">
                  Selecciona el contexto académico donde se archivará esta programación.
                </p>

                {teamEditable && (
                  <div className="space-y-1">
                    <FieldLabel>Equipo (opcional, exclusivo con contexto académico)</FieldLabel>
                    <Select
                      fieldSize="comfortable"
                      value={teamId}
                      disabled={!isDraft || !teamEditable}
                      onChange={(e: ChangeEvent<HTMLSelectElement>) => {
                        setTeamId(e.target.value);
                        if (e.target.value) {
                          setStudyTypeId('');
                          setStudyId('');
                          setModuleId('');
                        }
                        setErrors((prev: Record<string, string>) => ({ ...prev, studyTypeId: '', studyId: '', moduleId: '' }));
                      }}
                    >
                      <option value="">— Sin equipo (global/académico) —</option>
                      {availableTeams.map((t) => (
                        <option key={t.id} value={t.id}>{t.name}</option>
                      ))}
                    </Select>
                  </div>
                )}

                <div className="space-y-1">
                  <FieldLabel required={requireStudyType}>Tipo de Estudio</FieldLabel>
                  <Select
                    fieldSize="comfortable"
                    value={studyTypeId}
                    disabled={hierarchyLoading || !isDraft || !studyTypeEditable || (visibilityRule === 'global' && !isGlobalAcademicMode)}
                    onChange={(e) => {
                      setStudyTypeId(e.target.value);
                      setStudyId('');
                      setModuleId('');
                      if (visibilityRule === 'global') setTeamId('');
                      setErrors((prev: Record<string, string>) => ({ ...prev, studyTypeId: '', studyId: '', moduleId: '' }));
                    }}
                    error={!!errors.studyTypeId}
                  >
                    {hierarchy.length === 0 && !hierarchyLoading ? (
                      <option value="" disabled>No tienes tipos de estudio asignados, contacta con un administrador</option>
                    ) : (
                      <option value="">
                        {hierarchyLoading ? 'Cargando…' : '— Seleccionar —'}
                      </option>
                    )}
                    {hierarchy.map((t: any) => (
                      <option key={t.id} value={t.id}>{t.name}</option>
                    ))}
                  </Select>
                  {errors.studyTypeId && (
                    <p className="text-xs text-danger-dark dark:text-danger">{errors.studyTypeId}</p>
                  )}
                </div>

                <div className="space-y-1">
                  <FieldLabel required={requireStudy}>Estudio</FieldLabel>
                  <Select
                    fieldSize="comfortable"
                    value={studyId}
                    disabled={hierarchyLoading || !studyTypeId || !isDraft || !studyEditable || (visibilityRule === 'global' && !isGlobalAcademicMode)}
                    onChange={(e: ChangeEvent<HTMLSelectElement>) => {
                      setStudyId(e.target.value);
                      setModuleId('');
                      if (visibilityRule === 'global') setTeamId('');
                      setErrors((prev: Record<string, string>) => ({ ...prev, studyId: '', moduleId: '' }));
                    }}
                    error={!!errors.studyId}
                  >
                    <option value="">— Seleccionar —</option>
                    {filteredStudies.map((s: any) => (
                      <option key={s.id} value={s.id}>{s.name}</option>
                    ))}
                  </Select>
                  {errors.studyId && (
                    <p className="text-xs text-danger-dark dark:text-danger">{errors.studyId}</p>
                  )}
                </div>

                <div className="space-y-1">
                  <FieldLabel required={requireModule}>Módulo</FieldLabel>
                  <Select
                    fieldSize="comfortable"
                    value={moduleId}
                    disabled={hierarchyLoading || !studyId || !isDraft || !moduleEditable || (visibilityRule === 'global' && !isGlobalAcademicMode)}
                    onChange={(e: ChangeEvent<HTMLSelectElement>) => {
                      setModuleId(e.target.value);
                      if (visibilityRule === 'global') setTeamId('');
                      setErrors((prev: Record<string, string>) => ({ ...prev, moduleId: '' }));
                    }}
                    error={!!errors.moduleId}
                  >
                    <option value="">— Seleccionar —</option>
                    {filteredModules.map((m: any) => (
                      <option key={m.id} value={m.id}>{m.name}</option>
                    ))}
                  </Select>
                  {errors.moduleId && (
                    <p className="text-xs text-danger-dark dark:text-danger">{errors.moduleId}</p>
                  )}
                </div>
              </div>

              <div className="border-t border-ui-border dark:border-ui-dark-border pt-5 space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="space-y-1">
                    <FieldLabel required htmlFor="doc-title-input">Nombre</FieldLabel>
                    <TextInput
                      id="doc-title-input"
                      type="text"
                      fieldSize="comfortable"
                      value={title}
                      onChange={(e: ChangeEvent<HTMLInputElement>) => {
                        setTitle(e.target.value);
                        setErrors((prev: Record<string, string>) => ({ ...prev, title: '' }));
                      }}
                      disabled={!isDraft}
                      placeholder="Nombre de la programación"
                      error={!!errors.title}
                    />
                    {errors.title && (
                      <p className="text-xs text-danger-dark dark:text-danger">{errors.title}</p>
                    )}
                  </div>

                  <div className="space-y-1">
                    <FieldLabel htmlFor="doc-delivery-deadline-input" required>Fecha límite</FieldLabel>
                    <DatePicker
                      value={deliveryDeadline || null}
                      onChange={(d: string | null) => setDeliveryDeadline(d ?? '')}
                      disabled={!isDraft}
                      placeholder="Seleccionar fecha…"
                      ariaLabel="Fecha límite del documento"
                    />
                    {errors.deliveryDeadline && (
                      <p className="text-xs text-danger-dark dark:text-danger">{errors.deliveryDeadline}</p>
                    )}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {!isValidateMode && step === 'blocks' && (
        <div className={isEditorFullscreen
          ? 'fixed inset-0 z-[100] bg-white dark:bg-ui-dark-card flex flex-col'
          : 'flex-1 overflow-hidden flex flex-col md:flex-row'
        }>
          {/* Compact fullscreen header */}
          {isEditorFullscreen && activeBlock && (
            <div className="shrink-0 h-11 px-4 flex items-center gap-3 border-b border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card">
              <button
                type="button"
                aria-label="Salir de pantalla completa"
                title="Salir de pantalla completa (Esc)"
                onClick={() => document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))}
                className="shrink-0 p-1.5 rounded text-text-muted hover:text-text-primary hover:bg-ui-body dark:hover:bg-ui-dark-border transition-colors focus-visible:ring-2 focus-visible:ring-odoo-purple/50"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  <path d="M8 3v3a2 2 0 0 1-2 2H3" /><path d="M21 8h-3a2 2 0 0 1-2-2V3" />
                  <path d="M3 16h3a2 2 0 0 1 2 2v3" /><path d="M16 21v-3a2 2 0 0 1 2-2h3" />
                </svg>
              </button>
              <h3 className="flex-1 text-sm font-bold truncate uppercase tracking-widest">{activeBlock.title || 'Bloque'}</h3>
              {saveStatus === 'saving' && <span className="text-xs text-text-muted italic animate-pulse">Guardando…</span>}
              {saveStatus === 'saved' && <span className="text-xs text-success-dark font-bold">✓ Guardado</span>}
              {saveStatus === 'error' && <span className="text-xs text-danger-dark font-bold">Error al guardar</span>}
              <Button type="button" variant="primary" size="xs" onClick={() => void handleContinue()} className="shrink-0">
                Continuar →
              </Button>
            </div>
          )}
          {/* Block tree — hidden when editor is in fullscreen */}
          {!isEditorFullscreen && <div className="md:w-1/4 shrink-0 flex flex-col border-r border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card overflow-hidden">
            <div className="px-4 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between">
              <span className="text-xs font-bold uppercase text-text-secondary tracking-widest">
                Bloques ({sortedBlocks.length})
              </span>
            </div>
            <div className="flex-1 overflow-y-auto p-4 space-y-2">
              {sortedBlocks.length === 0 ? (
                <p className="text-xs text-text-muted">No hay bloques.</p>
              ) : (
                sortedBlocks.map((b) => {
                  const key = b.document_block_id ?? b.template_block_id;
                  const selected = key === activeBlockKey;
                  const ui = blockToUiState(b);
                  return (
                    <BlockListItem
                      key={key}
                      title={b.title || ''}
                      variant={selected ? 'selected' : 'default'}
                      locked={ui === 'locked'}
                      stateLabel={BLOCK_UI_STATE_CONFIG[ui].label}
                      hasReviewComments={reviewComments.some(c => c.blockable_id === b.document_block_id)}
                      onClick={() => setActiveBlockKey(key)}
                    />
                  );
                })
              )}
            </div>
          </div>}
          <div className="flex-1 min-w-0 flex flex-col bg-ui-body/30 dark:bg-ui-dark-bg overflow-hidden">
            {activeBlock && (
              <div className="flex-1 flex flex-col overflow-hidden animate-in fade-in">
                {!isEditorFullscreen && (
                <div className="px-5 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between shrink-0 bg-white dark:bg-ui-dark-card">
                  <div className="flex items-center gap-3 min-w-0">
                    <h3 className="text-sm font-bold truncate uppercase tracking-widest">
                      {activeBlock.title || 'Bloque'}
                    </h3>
                    {saveStatus === 'saving' && <span className="text-xs text-text-muted italic animate-pulse">Guardando…</span>}
                    {saveStatus === 'saved' && <span className="text-xs text-success-dark font-bold">✓ Guardado</span>}
                    {saveStatus === 'error' && <span className="text-xs text-danger-dark font-bold">Error al guardar</span>}
                  </div>
                  <div className="flex items-center gap-2 shrink-0">
                    {!showDocumentCommentPanel && activeBlock.document_block_id &&
                      reviewComments.some(c => c.blockable_id === activeBlock.document_block_id) && (
                      <Button
                        type="button"
                        size="xs"
                        variant="outline"
                        className="text-odoo-purple border-odoo-purple/40 hover:bg-odoo-purple/5"
                        onClick={() => setShowDocumentCommentPanel(true)}
                      >
                        Comentarios
                      </Button>
                    )}
                    {canDeleteOptionalBlock && (
                      <Button
                        type="button"
                        size="xs"
                        variant="outline"
                        className="text-danger border-danger/40 hover:border-danger hover:bg-danger/5"
                        onClick={() => setShowDeleteBlockConfirm(true)}
                      >
                        Eliminar
                      </Button>
                    )}
                  </div>
                </div>
              )}

                {!isEditorFullscreen && <div className="flex border-b border-ui-border dark:border-ui-dark-border shrink-0 bg-white dark:bg-ui-dark-card">
                  {([
                    { id: 'content', label: 'Contenido' },
                    { id: 'description', label: 'Descripción' },
                  ] as const).map((tab) => {
                    const isActive = blockViewTab === tab.id;
                    return (
                      <button
                        key={tab.id}
                        type="button"
                        onClick={() => setBlockViewTab(tab.id)}
                        className={`px-4 py-2 text-xs font-bold uppercase tracking-widest border-b-2 transition-colors ${
                          isActive
                            ? 'border-odoo-purple text-odoo-purple'
                            : 'border-transparent text-text-muted hover:text-text-primary'
                        }`}
                      >
                        {tab.label}
                      </button>
                    );
                  })}
                </div>}

                {blockSaveError && (
                  <p className="text-xs text-danger-dark dark:text-danger px-5 py-2 shrink-0 bg-white dark:bg-ui-dark-card">
                    {blockSaveError}
                  </p>
                )}
                <div className="flex-1 min-h-0 flex flex-col overflow-hidden">
                  {blockViewTab === 'content' ? (
                    canEditBlocks ? (
                      <ErrorBoundary fallback={<div className="p-4 text-danger">Error al cargar el editor de contenido.</div>}>
                        <div className="flex-1 min-h-0 p-6 flex flex-col">
                          <div className="flex-1 min-h-0 flex flex-col bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm overflow-hidden">
                            <Suspense
                              fallback={<p className="p-4 text-xs text-text-muted">Cargando editor…</p>}
                              key={activeBlockKey ?? 'none'}
                            >
                              <BlockNoteEditorPanel
                                initialContent={blockEditorContent(activeBlock)}
                                editable
                                isDark={isDark}
                                onChange={(content) => { setLocalContent(content); triggerSave(); }}
                                onFullscreenChange={handleEditorFullscreenChange}
                              />
                            </Suspense>
                          </div>
                        </div>
                      </ErrorBoundary>
                    ) : (
                      <div className="flex-1 overflow-y-auto p-6">
                        <div className="bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm p-6">
                          {(() => {
                            const nodes = blockEditorContent(activeBlock);
                            const hasNodes = nodes.length > 0;
                            return hasNodes ? (
                              <BlockContentHtml content={nodes} />
                            ) : (
                              <p className="text-sm text-text-muted italic">Sin contenido en este bloque.</p>
                            );
                          })()}
                        </div>
                      </div>
                    )
                  ) : (
                    <div className="flex-1 overflow-y-auto p-6">
                      <div className="bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm p-6">
                        {activeBlock.description != null && activeBlock.description !== '' ? (
                          <DocumentBlockDescriptionView description={activeBlock.description} />
                        ) : (
                          <p className="text-sm text-text-muted italic">
                            Este bloque no tiene descripción/instrucciones.
                          </p>
                        )}
                      </div>
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>

          {/* Right: creator-edit comment panel for active block */}
          {showDocumentCommentPanel && activeBlock && activeBlock.document_block_id && !isEditorFullscreen && (() => {
            const blockComments = reviewComments.filter(c => c.blockable_id === activeBlock.document_block_id && !c.parent_id);
            const allBlockComments = reviewComments.filter(c => {
              if (c.blockable_id === activeBlock.document_block_id && !c.parent_id) return true;
              const rootIds = blockComments.map(r => r.id);
              return c.parent_id !== null && rootIds.includes(c.parent_id);
            });
            if (blockComments.length === 0) return null;
            return (
              <div className="hidden md:block md:w-[35%] shrink-0 border-l border-ui-border dark:border-ui-dark-border overflow-y-auto custom-scrollbar p-4">
                <BlockCommentsCard
                  mode="creator-edit"
                  blockSortOrder={activeBlock.sort_order ?? '?'}
                  blockComments={allBlockComments}
                  allComments={reviewComments}
                  onReply={handleReviewReply}
                  onClose={() => setShowDocumentCommentPanel(false)}
                />
              </div>
            );
          })()}
        </div>
      )}

      {step === 'summary' && detail && (
        <div className="flex-1 min-h-0 flex flex-col px-6 py-5 space-y-4 overflow-hidden">
          {isValidateMode && (
            <p className="text-xs text-text-muted text-center shrink-0">
              Revisa el resumen del documento y confirma si lo apruebas o lo rechazas.
            </p>
          )}

          <div className="shrink-0 bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm overflow-hidden grid grid-cols-2 animate-in fade-in slide-in-from-top-1 w-full">
            {/* Columna izquierda — Propiedades */}
            <div className="px-5 py-4 border-r border-ui-border dark:border-ui-dark-border">
              <p className="text-xs font-bold uppercase tracking-widest text-text-secondary mb-3">Propiedades</p>
              <dl className="grid grid-cols-2 gap-x-4 gap-y-0">
                <DocSummaryRow label="Título" value={detail?.title} />
                <DocSummaryRow
                  label="Estado"
                  value={detail ? (DOCUMENT_STATUS_LABELS[detail.status] ?? detail.status) : ''}
                />
                <DocSummaryRow label="Versión" value={detail ? `v${detail.current_version}` : ''} />
                <DocSummaryRow label="Tipo de estudio" value={detail?.study_type_id} />
                <DocSummaryRow label="Estudio" value={detail?.study_id} />
                <DocSummaryRow label="Módulo" value={detail?.module_id} />
                <DocSummaryRow
                  label="Plazo de entrega"
                  value={detail?.delivery_deadline ? new Date(detail.delivery_deadline).toLocaleDateString() : null}
                />
              </dl>
            </div>

            {/* Columna derecha — Revisores / validadores */}
            <div className="px-5 py-4 space-y-3">
              <p className="text-xs font-bold uppercase tracking-widest text-text-secondary">
                {reviewerListKind === 'document'
                  ? 'Validadores del documento'
                  : reviewerListKind === 'template_fallback'
                    ? 'Quién validará (revisores de plantilla)'
                    : 'Revisores / validadores'}
              </p>
              {documentReviewers.length > 0 ? (
                <div className="space-y-1">
                  {reviewerListKind === 'template_fallback' && (
                    <p className="text-xs text-text-muted dark:text-text-dark-muted leading-snug">
                      La plantilla no define validadores de documento; al enviar se usarán los revisores
                      normativos de la plantilla (misma prioridad que en el servidor).
                    </p>
                  )}
                  <ul className="mt-1 space-y-1 text-xs">
                    {documentReviewers.map((reviewer) => (
                      <li key={reviewer.id}>• {reviewer.name}</li>
                    ))}
                  </ul>
                </div>
              ) : (
                <p className="text-xs text-text-muted italic">
                  {reviewerListKind === 'none'
                    ? 'La plantilla no tiene revisores ni validadores de documento configurados.'
                    : '—'}
                </p>
              )}
              {summaryError && (
                <p className="mt-2 text-xs text-danger-dark dark:text-danger">{summaryError}</p>
              )}
            </div>
          </div>

          <div className="flex-1 min-h-0 flex flex-col bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm overflow-hidden animate-in fade-in slide-in-from-top-1 w-full">
            <div className="shrink-0 px-5 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between">
              <span className="text-xs font-bold uppercase tracking-widest text-text-secondary">
                Contenido — {sortedBlocks.length} bloque{sortedBlocks.length !== 1 ? 's' : ''}
              </span>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() =>
                  navigate(`/documents/${documentId}`, {
                    state: {
                      returnToStep: isValidateMode || !!documentId ? 'summary' : undefined,
                      returnToValidate: isValidateMode,
                      backTo: processBackTo,
                      forceBackTo: !documentId && !isValidateMode,
                    },
                  })
                }
              >
                PREVISUALIZAR
              </Button>
            </div>
            {sortedBlocks.length === 0 ? (
              <div className="p-5">
                <p className="text-xs text-warning-dark italic">Este documento no tiene bloques.</p>
              </div>
            ) : (
              <div className="flex-1 min-h-0 grid" style={{ gridTemplateColumns: '200px 1fr' }}>
                <div className="border-r border-ui-border dark:border-ui-dark-border p-3 overflow-y-auto">
                  <div className="space-y-1">
                    {sortedBlocks.map((block, i) => {
                      const key = block.document_block_id ?? block.template_block_id;
                      const cfg = BLOCK_UI_STATE_CONFIG[blockToUiState(block)];
                      const fallbackKey = sortedBlocks[0]
                        ? (sortedBlocks[0].document_block_id ?? sortedBlocks[0].template_block_id)
                        : null;
                      const isSelected = key === (summaryBlockKey ?? fallbackKey);
                      return (
                        <button
                          key={key}
                          type="button"
                          onClick={() => setSummaryBlockKey(key)}
                          className={[
                            'w-full text-left flex items-center gap-2 px-2.5 py-2 rounded-lg border transition-all',
                            isSelected
                              ? 'bg-odoo-purple/10 border-odoo-purple/30 dark:bg-odoo-dark-purple/15'
                              : 'bg-transparent border-ui-border dark:border-ui-dark-border/50 hover:bg-ui-body dark:hover:bg-ui-dark-bg hover:border-ui-border',
                          ].join(' ')}
                        >
                          <span className="shrink-0 text-xs font-bold text-text-muted w-4 text-right">
                            {i + 1}
                          </span>
                          <span className="flex-1 min-w-0 text-xs font-medium text-text-primary dark:text-text-dark-primary truncate">
                            {block.title || 'Sin nombre'}
                          </span>
                          <span
                            className={`shrink-0 px-1.5 py-0.5 rounded text-xs font-bold uppercase ${cfg.badgeCls}`}
                          >
                            {cfg.label}
                          </span>
                        </button>
                      );
                    })}
                  </div>
                  <div className="absolute right-4 top-1/2 -translate-y-1/2 flex items-center gap-2">
                    {saveStatus === 'saving' && <span className="text-xs text-text-muted italic animate-pulse">Guardando…</span>}
                    {saveStatus === 'saved' && <span className="text-xs text-success-dark font-bold">✓ Guardado</span>}
                    {saveStatus === 'error' && <span className="text-xs text-danger-dark font-bold">Error al guardar</span>}
                  </div>
                </div>
                <div className="flex flex-col min-w-0 min-h-0 preview-content">
                  <div className="shrink-0 px-4 pt-3 border-b border-ui-border dark:border-ui-dark-border">
                    <div className="flex gap-0 -mb-px">
                      {([
                        { id: 'content', label: 'Contenido' },
                        { id: 'description', label: 'Descripción' },
                      ] as const).map((tab) => {
                        const isActive = summaryBlockTab === tab.id;
                        return (
                          <button
                            key={tab.id}
                            type="button"
                            onClick={() => setSummaryBlockTab(tab.id)}
                            className={[
                              'px-3 py-1.5 text-xs border-b-2 transition-all',
                              isActive
                                ? 'border-odoo-purple text-odoo-purple font-medium cursor-default'
                                : 'border-transparent text-text-muted hover:text-text-primary cursor-pointer',
                            ].join(' ')}
                            disabled={isActive}
                          >
                            {tab.label}
                          </button>
                        );
                      })}
                    </div>
                  </div>
                  <div className="flex-1 min-h-0 overflow-y-auto p-4">
                    {selectedSummaryBlock ? (
                      summaryBlockTab === 'content' ? (
                        (() => {
                          const nodes = blockEditorContent(selectedSummaryBlock);
                          const hasNodes = nodes.length > 0;
                          return hasNodes ? (
                            <BlockContentHtml content={nodes} />
                          ) : (
                            <span className="text-xs text-text-muted italic">Este bloque no tiene contenido.</span>
                          );
                        })()
                      ) : selectedSummaryBlock.description != null && selectedSummaryBlock.description !== '' ? (
                        <DocumentBlockDescriptionView description={selectedSummaryBlock.description} />
                      ) : (
                        <span className="text-xs text-text-muted italic">
                          Este bloque no tiene descripción.
                        </span>
                      )
                    ) : null}
                  </div>
                </div>
              </div>
            )}
          </div>

        </div>
      )}
      </>
    </WizardShell>
    <ConfirmDialog
        open={showDeleteBlockConfirm}
        variant="danger"
        title="¿Eliminar este bloque?"
        description="Este bloque opcional se eliminará del documento. Esta acción no se puede deshacer."
        confirmLabel="Eliminar"
        cancelLabel="Cancelar"
        onCancel={() => setShowDeleteBlockConfirm(false)}
        onConfirm={async () => {
          setShowDeleteBlockConfirm(false);
          const blockId = activeBlock?.document_block_id;
          if (!documentId || !blockId) return;
          try {
            await deleteDocumentBlock(documentId, blockId);
            await refreshDetail();
          } catch (e) {
            setBlockSaveError(e instanceof Error ? e.message : 'No se pudo eliminar el bloque.');
          }
        }}
      />
      <ConfirmDialog
        open={validateConfirm === 'approve'}
        title="Confirmar aprobación"
        description="Se registrará tu aprobación. Si eres el último validador pendiente, el documento pasará a publicado."
        confirmLabel="Aprobar"
        error={validationModalError}
        loading={validationActionLoading}
        onCancel={() => {
          setValidateConfirm(null);
          setValidationModalError(null);
        }}
        onConfirm={() => void handleApproveValidation()}
      />
      <ConfirmDialog
        open={validateConfirm === 'reject'}
        title="Confirmar rechazo"
        description={
          <div className="space-y-2 text-left">
            <p className="text-sm text-text-secondary dark:text-text-dark-secondary">
              El documento volverá a borrador para que el titular pueda corregirlo. El resto de validadores dejarán
              de tener esta revisión asignada.
            </p>
            <TextArea
              fieldSize="comfortable"
              rows={3}
              value={rejectReason}
              onChange={(e: ChangeEvent<HTMLTextAreaElement>) => setRejectReason(e.target.value)}
              placeholder={`Motivo del rechazo (obligatorio, mín. ${DOCUMENT_REJECT_REASON_MIN_LEN} caracteres)`}
            />
          </div>
        }
        confirmLabel="Rechazar"
        variant="danger"
        error={validationModalError}
        loading={validationActionLoading}
        onCancel={() => {
          setValidateConfirm(null);
          setValidationModalError(null);
          setRejectReason('');
        }}
        onConfirm={() => void handleRejectValidation()}
      />
      <ConfirmDialog
        open={summaryConfirmAction !== null}
        title={summaryConfirmAction === 'submit' ? 'Confirmar envío a validar' : 'Confirmar guardado'}
        description={
          summaryConfirmAction === 'submit'
            ? (
                <div className="space-y-2">
                  <p>
                    {reviewerListKind === 'document'
                      ? 'Se enviará una notificación a los validadores del documento configurados en la plantilla.'
                      : reviewerListKind === 'template_fallback'
                        ? 'La plantilla no tiene validadores de documento; se notificará según los revisores normativos de la plantilla listados abajo.'
                        : 'No hay revisores configurados en la plantilla para este envío.'}
                  </p>
                  {documentReviewers.length > 0 ? (
                    <>
                      <p>
                        Tipo de revisión:{' '}
                        <strong>{documentReviewMode === 'sequential' ? 'Ordenada' : 'Libre'}</strong>
                      </p>
                      {documentReviewMode === 'sequential' ? (
                        <ol className="list-decimal pl-4 space-y-1">
                          {documentReviewers.map((reviewer) => (
                            <li key={reviewer.id}>{reviewer.name}</li>
                          ))}
                        </ol>
                      ) : (
                        <ul className="space-y-1">
                          {documentReviewers.map((reviewer) => (
                            <li key={reviewer.id}>• {reviewer.name}</li>
                          ))}
                        </ul>
                      )}
                    </>
                  ) : (
                    <p>No hay personas en la lista de revisión para mostrar.</p>
                  )}
                  <p>Después no se podrá seguir editando como borrador.</p>
                </div>
              )
            : '¿Quieres guardar y salir sin enviar? El documento permanecerá en estado borrador.'
        }
        confirmLabel={summaryConfirmAction === 'submit' ? 'Sí, enviar a validar' : 'Sí, guardar y salir'}
        cancelLabel="Cancelar"
        variant={summaryConfirmAction === 'submit' ? 'primary' : 'teal'}
        loading={summaryConfirmAction === 'submit' && submittingForReview}
        onCancel={() => setSummaryConfirmAction(null)}
        onConfirm={() => void handleConfirmSummaryAction()}
      />
    </>
  );
}
