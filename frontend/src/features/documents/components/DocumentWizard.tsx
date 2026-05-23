import {
  lazy,
  Suspense,
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ChangeEvent,
} from 'react';
import { useTranslation } from 'react-i18next';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useLocation, useNavigate } from 'react-router-dom';
import {
  documentStep1Schema,
  type DocumentStep1Input,
} from '../schemas/documentStep1';
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
  delegateDocument,
  type DocumentReview,
} from '../../../api/documents';
import { useQueryClient } from '@tanstack/react-query';
import { ApiHttpError, apiFetchJson } from '../../../api/http';
import { useUserProfile } from '../../user-profile';
import { canDeleteBlockComment } from '../../../permissions';
import { fetchProcesses } from '../../../api/processes';
import { fetchTemplate } from '../../../api/templates';
import { useDocumentCommentsQuery } from '../hooks/useDocumentComments';
import { BlockCommentsCard } from '../../templates/components/BlockCommentsCard';
import type { BlockComment } from '../../templates/components/BlockCommentsCard';
import { fetchMe, searchDocumentReviewerCandidates, searchOwnerCandidates, type UserTeam } from '../../../api/users';
import { useAutoSave } from '../../../hooks/useAutoSave';
import { useDarkMode } from '@maya/shared-layout-react';
import type { DocumentDetail, DocumentDisplayBlock, DocumentStatus } from '../../../types/documents';
import { useHierarchy } from '../../hierarchy';
import type { Study, CourseModule } from '../../../types/hierarchy';
import type { Template } from '../../../types/templates';
import { BLOCK_UI_STATE_CONFIG, blockToUiState } from '../../templates/blockUiState';
import { normalizeBlockContentForEditor } from '../lib/normalizeBlockContent';
import { BlockContentHtml } from '../../templates/components/BlockContentHtml';
import { visibilityLabel } from '../../templates/constants';
import {
  Button,
  Card,
  ConfirmDialog,
  DatePicker,
  ErrorBoundary,
  FieldLabel,
  Select,
  TextInput,
} from '@maya/shared-ui-react';
import { WizardShell, type WizardStepDef } from '../../../components/wizard/WizardShell';
import { BlockListItem } from '../../blocks-ui/BlockListItem';
import { getCommentsForBlock } from '../../../utils/blockComments';
import { uploadMedia } from '../../../api/media';

const BlockNoteEditorPanel = lazy(() =>
  import('../../templates/components/BlockNoteEditorPanel').then(
    (m) => ({ default: m.BlockNoteEditorPanel }),
  )
);

import {
  type Step,
  type SummaryConfirmAction,
  type BlockViewTab,
  type ReviewModeView,
  type VisibilityRuleMode,
  type ReviewerView,
  DOCUMENT_STATUS_LABELS,
  dateIsoToInput,
  blockEditorContent,
  validationSuccessBannerMessage,
  pickActionableDocumentReview,
  isUuidLike,
} from './documentWizardUtils';
import { DocumentBlockDescriptionView, DocSummaryRow } from './DocumentWizardSubviews';

type Props = {
  documentId?: string | null;
  templateId?: string | null;
  mode?: 'edit' | 'validate';
};

/**
 * Asistente de edición de documento (3 pasos, sin usuarios/validadores).
 * Reutiliza estética y piezas de plantillas (BlockNote, preview HTML) sin acoplar al flujo de TemplateWizard.
 */
export function DocumentWizard({ documentId, templateId, mode = 'edit' }: Props) {
  const navigate = useNavigate();
  const { t } = useTranslation(['documents', 'common']);
  const queryClient = useQueryClient();
  const { hasPermission } = useUserProfile();
  const location = useLocation();
  const { isDark } = useDarkMode();

  const [step, setStep] = useState<Step>('properties');
  const [completedSteps, setCompletedSteps] = useState<Step[]>([]);

  const [detail, setDetail] = useState<DocumentDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  const step1Methods = useForm<DocumentStep1Input>({
    defaultValues: {
      title: '',
      deliveryDeadline: '',
      studyTypeId: '',
      studyId: '',
      moduleId: '',
      teamId: '',
    },
    resolver: zodResolver(documentStep1Schema),
    mode: 'onChange',
  });
  const {
    setValue: setStep1Value,
    watch: watchStep1,
    setError: setStep1Error,
    clearErrors: clearStep1Errors,
    handleSubmit: handleStep1Submit,
    formState: { errors: step1Errors },
  } = step1Methods;
  const title = watchStep1('title');
  const deliveryDeadline = watchStep1('deliveryDeadline');
  const studyTypeId = watchStep1('studyTypeId');
  const studyId = watchStep1('studyId');
  const moduleId = watchStep1('moduleId');
  const teamId = watchStep1('teamId');
  const setTitle = useCallback((v: string) => setStep1Value('title', v, { shouldDirty: true, shouldValidate: false }), [setStep1Value]);
  const setDeliveryDeadline = useCallback((v: string) => setStep1Value('deliveryDeadline', v, { shouldDirty: true, shouldValidate: false }), [setStep1Value]);
  const setStudyTypeId = useCallback((v: string) => setStep1Value('studyTypeId', v, { shouldDirty: true, shouldValidate: false }), [setStep1Value]);
  const setStudyId = useCallback((v: string) => setStep1Value('studyId', v, { shouldDirty: true, shouldValidate: false }), [setStep1Value]);
  const setModuleId = useCallback((v: string) => setStep1Value('moduleId', v, { shouldDirty: true, shouldValidate: false }), [setStep1Value]);
  const setTeamId = useCallback((v: string) => setStep1Value('teamId', v, { shouldDirty: true, shouldValidate: false }), [setStep1Value]);
  const [availableTeams, setAvailableTeams] = useState<UserTeam[]>([]);
  const [currentUserId, setCurrentUserId] = useState<string | null>(null);
  const [newOwnerForDoc, setNewOwnerForDoc] = useState<{ id: string; name: string } | null>(null);
  const [ownerQuery, setOwnerQuery] = useState('');
  const [ownerResults, setOwnerResults] = useState<import('../../../types/users').User[]>([]);
  const [ownerSearching, setOwnerSearching] = useState(false);
  // Cross-field errors that depend on derived flags (require*) live alongside RHF formState.errors.
  const errors: Record<string, string> = useMemo(() => {
    const map: Record<string, string> = {};
    if (step1Errors.title?.message) map.title = step1Errors.title.message;
    if (step1Errors.deliveryDeadline?.message) map.deliveryDeadline = step1Errors.deliveryDeadline.message;
    if (step1Errors.studyTypeId?.message) map.studyTypeId = step1Errors.studyTypeId.message;
    if (step1Errors.studyId?.message) map.studyId = step1Errors.studyId.message;
    if (step1Errors.moduleId?.message) map.moduleId = step1Errors.moduleId.message;
    if (step1Errors.teamId?.message) map.teamId = step1Errors.teamId.message;
    return map;
  }, [step1Errors]);
  const setErrors = useCallback((updater: Record<string, string> | ((prev: Record<string, string>) => Record<string, string>)) => {
    const next = typeof updater === 'function' ? updater(errors) : updater;
    const keys: (keyof DocumentStep1Input)[] = ['title', 'deliveryDeadline', 'studyTypeId', 'studyId', 'moduleId', 'teamId'];
    for (const k of keys) {
      if (next[k]) setStep1Error(k, { type: 'manual', message: next[k] });
      else clearStep1Errors(k);
    }
  }, [errors, setStep1Error, clearStep1Errors]);
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
  const [showNoValidatorsDocModal, setShowNoValidatorsDocModal] = useState(false);

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
  const [localContent, setLocalContent] = useState<unknown>(null);
  const [showDeleteBlockConfirm, setShowDeleteBlockConfirm] = useState(false);
  const [emptyEditableBlocksModal, setEmptyEditableBlocksModal] = useState<string[] | null>(null);
  const [processSubtitle, setProcessSubtitle] = useState<string | null>(null);
  const activeBlockRef = useRef<DocumentDisplayBlock | null>(null);
  const [isEditorFullscreen, setIsEditorFullscreen] = useState(false);

  // Review comments for creator-edit mode (mirrors TemplateWizard + WizardStep2Blocks).
  // Sourced from the shared TanStack Query cache (useDocumentCommentsQuery) so the
  // DocumentPreviewPage and the wizard reuse the same in-memory comments.
  const [showDocumentCommentPanel, setShowDocumentCommentPanel] = useState(true);
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(false);

  const handleEditorFullscreenChange = useCallback((v: boolean) => {
    setIsEditorFullscreen(v);
    document.documentElement.classList.toggle('editor-fullscreen', v);
  }, []);

  useEffect(() => {
    return () => document.documentElement.classList.remove('editor-fullscreen');
  }, []);

  const isValidateMode = mode === 'validate';
  const isDraft = !detail || detail.status === 'draft' || detail.status === 'rejected';
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

  const documentCommentsQuery = useDocumentCommentsQuery(documentId ?? '', {
    enabled: !!documentId && !!detail,
  });
  const reviewComments: BlockComment[] = documentCommentsQuery.data?.data ?? [];

  const handleDocumentCommentSend = useCallback(async (parentId: string | null, body: string) => {
    if (!documentId) return;
    const blockableId = parentId
      ? (reviewComments.find(c => c.id === parentId)?.blockable_id ?? null)
      : (activeBlockRef.current?.document_block_id ?? null);
    const res = await apiFetchJson<{ data: BlockComment }>(`documents/${documentId}/comments`, {
      method: 'POST',
      body: { body, parent_id: parentId, blockable_id: blockableId },
    });
    queryClient.setQueryData<{ data: BlockComment[] }>(
      ['documents', documentId, 'comments'],
      (current) => ({ data: [...(current?.data ?? []), res.data] }),
    );
  }, [documentId, reviewComments, queryClient]);

  const handleDocumentCommentEdit = useCallback(async (commentId: string, newBody: string) => {
    if (!documentId) return;
    const res = await apiFetchJson<{ data: BlockComment }>(`comments/${commentId}`, {
      method: 'PATCH',
      body: { body: newBody },
    });
    queryClient.setQueryData<{ data: BlockComment[] }>(
      ['documents', documentId, 'comments'],
      (current) => ({ data: (current?.data ?? []).map(c => c.id === commentId ? res.data : c) }),
    );
  }, [documentId, queryClient]);

  const handleDocumentCommentDelete = useCallback(async (commentId: string) => {
    if (!documentId) return;
    await apiFetchJson(`comments/${commentId}`, { method: 'DELETE' });
    queryClient.setQueryData<{ data: BlockComment[] }>(
      ['documents', documentId, 'comments'],
      (current) => ({ data: (current?.data ?? []).filter(c => c.id !== commentId) }),
    );
  }, [documentId, queryClient]);

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
        if (!cancelled) {
          setAvailableTeams(me.data.teams ?? []);
          setCurrentUserId(me.data.id);
        }
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
        setCurrentUserId(userId);
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
    const typeNode = hierarchy.find((t) => String(t.id) === studyTypeId);
    if (!typeNode) return;
    if ((typeNode.studies ?? []).length === 1) setStudyId(String(typeNode.studies[0].id));
  }, [documentId, hierarchy, studyTypeId, studyId]);

  useEffect(() => {
    if (documentId || !studyId || moduleId) return;
    const allStudiesFlat = hierarchy.flatMap((t) => t.studies ?? []);
    const studyNode = allStudiesFlat.find((s) => String(s.id) === studyId);
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

  const selectedStudyNode = allStudies.find((s) => String(s.id) === studyId) ?? null;

  const filteredStudies = useMemo<Study[]>(() => {
    if (!studyTypeId) return [];
    const byType = hierarchy.find((t) => String(t.id) === studyTypeId)?.studies ?? [];
    if (visibilityRule === 'study' || visibilityRule === 'module') {
      return byType.filter((s) => String(s.id) === String(templateStudyId ?? ''));
    }
    return byType;
  }, [studyTypeId, hierarchy, visibilityRule, templateStudyId]);

  const filteredModules = useMemo<CourseModule[]>(() => {
    if (!studyId) return [];
    const byStudy = allStudies.find((s) => String(s.id) === studyId)?.course_modules ?? [];
    if (visibilityRule === 'module') {
      return byStudy.filter((m) => String(m.id) === String(templateModuleId ?? ''));
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
    const studyType = hierarchy.find((t) => String(t.id) === String(template.study_type_id ?? ''));
    const studies: Study[] = studyType?.studies ?? hierarchy.flatMap((t) => t.studies ?? []);
    const study = studies.find((s) => String(s.id) === String(template.study_id ?? ''));
    const modules: CourseModule[] = study?.course_modules ?? [];
    const module = modules.find((m) => String(m.id) === String(template.module_id ?? ''));
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
      const stFromStudy = allStudies.find((s) => String(s.id) === String(templateStudyId))?.study_type_id;
      if (stFromStudy) setStudyTypeId(String(stFromStudy));
      setStudyId(String(templateStudyId));
      if (moduleId) {
        const moduleInStudy = (allStudies.find((s) => String(s.id) === String(templateStudyId))?.course_modules ?? [])
          .some((m) => String(m.id) === String(moduleId));
        if (!moduleInStudy) setModuleId('');
      }
      return;
    }
    if (visibilityRule === 'module' && templateModuleId) {
      const owningStudy = allStudies.find((s) => (s.course_modules ?? []).some((m) => String(m.id) === String(templateModuleId))) ?? null;
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
        const tplUserIds = tplRows.map((r: { user_id: string }) => r.user_id);
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

        const byId = new Map(usersResp.data.map((u: { id: string; name: string }) => [u.id, u.name] as const));
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
    if (!template) return;
    const docIds = template.document_reviewers ?? [];
    const tplUserIds = (template.reviewers ?? []).map((r: { user_id: string }) => r.user_id);
    if (docIds.length > 0) {
      setReviewerListKind('document');
    } else if (tplUserIds.length > 0) {
      setReviewerListKind('template_fallback');
    } else {
      setReviewerListKind('none');
    }
  }, [template]);

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

  useEffect(() => {
    const q = ownerQuery.trim();
    if (q.length < 2) {
      setOwnerResults([]);
      return;
    }
    const timer = setTimeout(() => {
      setOwnerSearching(true);
      searchOwnerCandidates(q)
        .then((res) => setOwnerResults(res.data))
        .catch(() => setOwnerResults([]))
        .finally(() => setOwnerSearching(false));
    }, 300);
    return () => clearTimeout(timer);
  }, [ownerQuery]);

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
    if (step === 'properties') {
      clearStep1Errors();
      // First run zod schema validation; if it fails, errors are set automatically.
      const valid = await new Promise<DocumentStep1Input | false>((resolve) => {
        void handleStep1Submit(
          (values) => resolve(values),
          () => resolve(false),
        )();
      });
      if (!valid) return;

      // Context-dependent (derived) requirements not expressible in the zod schema.
      const contextErrors: Partial<Record<keyof DocumentStep1Input, string>> = {};
      if ((requireStudyType || (visibilityRule === 'global' && isGlobalAcademicMode && (valid.studyId || valid.moduleId || valid.studyTypeId))) && !valid.studyTypeId) {
        contextErrors.studyTypeId = 'Selecciona un tipo de estudio.';
      }
      if ((requireStudy || (visibilityRule === 'global' && isGlobalAcademicMode && (valid.moduleId || valid.studyId))) && !valid.studyId) {
        contextErrors.studyId = 'Selecciona un estudio.';
      }
      if (requireModule && !valid.moduleId) contextErrors.moduleId = 'Selecciona un módulo.';

      if (Object.keys(contextErrors).length > 0) {
        for (const [field, message] of Object.entries(contextErrors)) {
          setStep1Error(field as keyof DocumentStep1Input, { type: 'manual', message });
        }
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
            delivery_deadline: deliveryDeadline || null,
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
            delivery_deadline: deliveryDeadline || null,
            study_type_id: studyTypeId || undefined,
            study_id: studyId || undefined,
            module_id: moduleId || undefined,
          });

          if (newOwnerForDoc) {
            await delegateDocument(documentId, newOwnerForDoc.id);
            setNewOwnerForDoc(null);
          }

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
      if (detail) {
        const emptyEditable = detail.blocks.filter(
          (b: DocumentDisplayBlock) => b.block_state === 'editable' && !b.is_filled && !b.is_deleted,
        );
        if (emptyEditable.length > 0) {
          setEmptyEditableBlocksModal(emptyEditable.map((b: DocumentDisplayBlock) => b.title ?? 'Sin título'));
          return;
        }
      }
      if (reviewerListKind === 'none' && selectedTemplateVisibility != null && selectedTemplateVisibility !== 'personal') {
        setShowNoValidatorsDocModal(true);
        return;
      }
      setCompletedSteps((prev: Step[]) => Array.from(new Set([...prev, 'blocks'] as Step[])));
      setStep('summary');
      return;
    }
    if (step === 'summary') {
      if (window.history.length <= 1) {
        navigate("/dashboard");
      } else {
        navigate(-1);
      }
    }
  };

  const handleSubmitForReview = async () => {
    if (!detail || !['draft', 'rejected'].includes(detail.status)) {
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
      navigate(processBackTo, {
        state: { documentValidationBanner: validationSuccessBannerMessage(updated, 'approve'), tab: 'documents' },
      });
    } catch (e) {
      setValidationModalError(e instanceof ApiHttpError ? e.message : 'No se pudo aprobar la revisión.');
    } finally {
      setValidationActionLoading(false);
    }
  };

  const validatorHasCommented = currentUserId
    ? reviewComments.some(c => c.author_id === currentUserId)
    : false;

  const handleRejectValidation = async () => {
    if (!documentId || !actionableReviewId) {
      setValidationModalError('Faltan datos críticos para procesar la revisión.');
      return;
    }
    setValidationModalError(null);
    setSummaryError(null);
    setValidationActionLoading(true);
    try {
      const updated = await rejectDocumentReview(documentId, actionableReviewId, null);
      setValidateConfirm(null);
      navigate(processBackTo, {
        state: { documentValidationBanner: validationSuccessBannerMessage(updated, 'reject'), tab: 'documents' },
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
        <p className="text-sm text-text-muted dark:text-text-dark-muted">{t('wizard.loadingValidation')}</p>
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
    const order: Step[] = ['properties', 'blocks', 'summary'];
    const idx = order.indexOf(step);
    if (isValidateMode) {
      navigate('/dashboard');
      return;
    }
    if (idx > 0) {
      setStep(order[idx - 1]!);
    } else {
      if (window.history.length <= 1) {
        navigate("/dashboard");
      } else {
        navigate(-1);
      }
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

            <Card padding="lg" radius="xl" className="space-y-6 animate-in fade-in slide-in-from-top-1">
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
                    {hierarchy.map((t) => (
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
                    {filteredStudies.map((s) => (
                      <option key={s.id} value={s.id}>{s.name}</option>
                    ))}
                  </Select>
                  {errors.studyId && (
                    <p className="text-xs text-danger-dark dark:text-danger">{errors.studyId}</p>
                  )}
                </div>

                <div className="space-y-1">
                  <FieldLabel required={requireModule}>{t('documents:fields.module')}</FieldLabel>
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
                    {filteredModules.map((m) => (
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
                      placeholder={t('documents:wizard.namePlaceholder')}
                      error={!!errors.title}
                    />
                    {errors.title && (
                      <p className="text-xs text-danger-dark dark:text-danger">{errors.title}</p>
                    )}
                  </div>

                  <div className="space-y-1">
                    <FieldLabel htmlFor="doc-delivery-deadline-input" required>{t('documents:fields.deadline')}</FieldLabel>
                    <DatePicker
                      value={deliveryDeadline || null}
                      onChange={(d: string | null) => setDeliveryDeadline(d ?? '')}
                      disabled={!isDraft}
                      placeholder={t('documents:wizard.selectDate')}
                      ariaLabel={t('documents:fields.deadline')}
                    />
                    {errors.deliveryDeadline && (
                      <p className="text-xs text-danger-dark dark:text-danger">{errors.deliveryDeadline}</p>
                    )}
                  </div>
                </div>
              </div>

              {isDraft && !!detail && !!currentUserId && detail.owner_id === currentUserId && (
                <div className="border-t border-ui-border dark:border-ui-dark-border pt-5 space-y-3">
                  <p className="text-xs font-bold uppercase tracking-wider text-text-secondary dark:text-text-dark-secondary">
                    Propietario
                  </p>
                  <div className="flex items-center gap-2">
                    <span className="text-xs text-text-secondary dark:text-text-dark-secondary">Actual:</span>
                    <span className="text-xs font-semibold text-text-primary dark:text-text-dark-primary">
                      {newOwnerForDoc ? newOwnerForDoc.name : (detail.owner_name ?? '—')}
                    </span>
                    {newOwnerForDoc && (
                      <button
                        type="button"
                        onClick={() => { setNewOwnerForDoc(null); setOwnerQuery(''); }}
                        className="text-xs text-danger-dark hover:underline"
                      >
                        Deshacer
                      </button>
                    )}
                  </div>
                  <div className="relative">
                    <TextInput
                      type="search"
                      fieldSize="comfortable"
                      placeholder="Buscar nuevo propietario…"
                      value={ownerQuery}
                      onChange={(e: ChangeEvent<HTMLInputElement>) => setOwnerQuery(e.target.value)}
                    />
                  </div>
                  {ownerQuery.trim().length > 0 && ownerQuery.trim().length < 2 && (
                    <p className="text-xs text-text-muted italic">Escribe al menos 2 caracteres para buscar.</p>
                  )}
                  {ownerSearching && <p className="text-xs text-text-muted italic">Buscando…</p>}
                  {!ownerSearching && ownerResults.length > 0 && (
                    <ul className="border border-ui-border dark:border-ui-dark-border rounded-lg overflow-hidden divide-y divide-ui-border dark:divide-ui-dark-border">
                      {ownerResults.map((u) => (
                        <li key={u.id}>
                          <button
                            type="button"
                            onClick={() => { setNewOwnerForDoc({ id: u.id, name: u.name }); setOwnerQuery(''); setOwnerResults([]); }}
                            className="w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-odoo-purple/5 transition-colors"
                          >
                            <span className="shrink-0 flex items-center justify-center w-7 h-7 rounded-full bg-odoo-purple/10 text-odoo-purple text-xs font-black border border-odoo-purple/20">
                              {u.name.split(' ').filter(Boolean).slice(0, 2).map((w: string) => w[0]?.toUpperCase() ?? '').join('')}
                            </span>
                            <div className="flex-1 min-w-0">
                              <p className="text-sm font-semibold text-text-primary dark:text-text-dark-primary truncate">{u.name}</p>
                              {u.role && <p className="text-xs text-text-secondary dark:text-text-dark-secondary">{u.role}</p>}
                            </div>
                          </button>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              )}
            </Card>
          </div>
        </div>
      )}

      {!isValidateMode && step === 'blocks' && (
        <div className={isEditorFullscreen
          ? 'fixed inset-0 z-[100] bg-white dark:bg-ui-dark-card flex flex-col'
          : 'flex-1 overflow-visible flex flex-col md:flex-row min-h-0'
        }>
          {/* Compact fullscreen header */}
          {isEditorFullscreen && activeBlock && (
            <div className="shrink-0 h-11 px-4 flex items-center gap-3 border-b border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card">
              <button
                type="button"
                aria-label={t('documents:wizard.exitFullscreenAria')}
                title={t('documents:wizard.exitFullscreenTitle')}
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
          {!isEditorFullscreen && (
            <div className="relative shrink-0 z-30 flex flex-col overflow-visible">
              <div className={[
                'h-full flex flex-col border-r border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card transition-all duration-300 overflow-hidden',
                isSidebarCollapsed ? 'w-0' : 'md:w-64 lg:w-72'
              ].join(' ')}>
                <div className="px-4 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between shrink-0">
                  <span className="text-xs font-bold uppercase text-text-secondary tracking-widest truncate">
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
                      const isEmptyEditable = b.block_state === 'editable' && !b.is_filled && !b.is_deleted;
                      return (
                        <BlockListItem
                          key={key}
                          title={b.title || ''}
                          variant={selected ? 'selected' : 'default'}
                          locked={ui === 'locked'}
                          stateLabel={BLOCK_UI_STATE_CONFIG[ui].label}
                          hasReviewComments={reviewComments.some(c => c.blockable_id === b.document_block_id)}
                          isEmpty={isEmptyEditable}
                          onClick={() => setActiveBlockKey(key)}
                        />
                      );
                    })
                  )}
                </div>
              </div>

              <button
                type="button"
                onClick={() => setIsSidebarCollapsed(!isSidebarCollapsed)}
                className={[
                  'absolute top-4 -right-3 z-50 w-6 h-6 rounded-full border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card flex items-center justify-center text-text-muted hover:text-odoo-purple transition-all shadow-sm',
                  isSidebarCollapsed ? 'rotate-180' : ''
                ].join(' ')}
                title={isSidebarCollapsed ? 'Expandir' : 'Colapsar'}
              >
                <svg className="w-3.5 h-3.5 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
              </button>
            </div>
          )}
          <div className="flex-1 min-w-0 min-h-0 flex flex-col bg-ui-body/30 dark:bg-ui-dark-bg overflow-visible">
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
                    {!showDocumentCommentPanel && activeBlock.document_block_id && (
                      <Button
                        type="button"
                        size="xs"
                        variant="outline"
                        className="text-odoo-purple border-odoo-purple/40 hover:bg-odoo-purple/5"
                        onClick={() => setShowDocumentCommentPanel(true)}
                      >
                        Comentarios ({getCommentsForBlock(activeBlock.document_block_id, reviewComments).length})
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
                                uploadFile={uploadMedia}
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
          {showDocumentCommentPanel && activeBlock && activeBlock.document_block_id && !isEditorFullscreen && (
            <div className="hidden md:flex md:w-[35%] shrink-0 border-l border-ui-border dark:border-ui-dark-border flex-col p-4 h-full min-h-0">
              <BlockCommentsCard
                mode="creator-edit"
                blockSortOrder={activeBlock.sort_order ?? '?'}
                blockComments={getCommentsForBlock(activeBlock.document_block_id, reviewComments)}
                allComments={reviewComments}
                onSendMessage={handleDocumentCommentSend}
                onClose={() => setShowDocumentCommentPanel(false)}
                canAddComments={detail?.status !== 'published'}
                currentUserId={currentUserId ?? undefined}
                canDeleteAnyComment={canDeleteBlockComment(hasPermission)}
                onEditComment={handleDocumentCommentEdit}
                onDeleteComment={handleDocumentCommentDelete}
              />
            </div>
          )}
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
        open={emptyEditableBlocksModal !== null}
        title={t('documents:wizard.unfilledBlocksTitle')}
        description={
          <div className="space-y-2">
            <p>Debes rellenar todos los bloques editables antes de continuar. Bloques pendientes:</p>
            <ul className="space-y-1">
              {(emptyEditableBlocksModal ?? []).map((name, i) => (
                <li key={i} className="font-medium">• {name}</li>
              ))}
            </ul>
          </div>
        }
        confirmLabel="Entendido"
        onConfirm={() => setEmptyEditableBlocksModal(null)}
        onCancel={() => setEmptyEditableBlocksModal(null)}
      />
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
        title={t('documents:approveTitle')}
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
        title={validatorHasCommented ? 'Confirmar rechazo' : 'Comentario requerido'}
        description={
          validatorHasCommented ? (
            <p className="text-sm text-text-secondary dark:text-text-dark-secondary">
              El documento volverá a borrador para que el titular pueda corregirlo. El resto de validadores dejarán
              de tener esta revisión asignada. Tus comentarios en los bloques quedarán registrados como motivo.
            </p>
          ) : (
            <p className="text-sm text-text-secondary dark:text-text-dark-secondary">
              Para rechazar la validación debes dejar al menos un comentario en un bloque del documento explicando
              el motivo del rechazo. El comentario queda registrado para el titular.
            </p>
          )
        }
        confirmLabel={validatorHasCommented ? 'Rechazar' : 'Entendido'}
        variant={validatorHasCommented ? 'danger' : 'primary'}
        error={validationModalError}
        loading={validationActionLoading}
        onCancel={() => {
          setValidateConfirm(null);
          setValidationModalError(null);
        }}
        onConfirm={validatorHasCommented
          ? () => void handleRejectValidation()
          : () => { setValidateConfirm(null); setValidationModalError(null); }}
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
      <ConfirmDialog
        open={showNoValidatorsDocModal}
        title="Sin validadores configurados"
        description="La plantilla no tiene validadores asignados. Al enviar este documento, se publicará automáticamente sin revisión. Para añadir validadores, edita la plantilla."
        confirmLabel="Continuar de todas formas"
        cancelLabel="Cancelar"
        onConfirm={() => {
          setShowNoValidatorsDocModal(false);
          setCompletedSteps((prev: Step[]) => Array.from(new Set([...prev, 'blocks'] as Step[])));
          setStep('summary');
        }}
        onCancel={() => setShowNoValidatorsDocModal(false)}
      />
    </>
  );
}
