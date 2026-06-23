import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocation, useNavigate } from 'react-router-dom';
import type { DocumentStep1Input } from '../schemas/documentStep1';
import {
  applyTemplateMigration,
  approveDocumentReview,
  createDocument,
  deleteDocumentBlock,
  fetchDocument,
  fetchDocumentReviewers,
  fetchDocumentReviews,
  rejectDocumentReview,
  submitDocumentForReview,
  updateDocument,
  updateDocumentBlock,
  delegateDocument,
} from '../../../api/documents';
import { DocumentMigrationStep } from './DocumentMigrationStep';
import { useQueryClient } from '@tanstack/react-query';
import { refreshDmsDashboardQuery } from '../../dashboard/hooks/useDmsDashboard';
import { ApiHttpError } from '../../../api/http';
import { useUserProfile } from '../../user-profile';
import { canCommentOnDocument, canDeleteBlockComment } from '../../../permissions';
import { useProcessesQuery } from '../../../hooks/useProcesses';
import { fetchTemplate } from '../../../api/templates';
import { useCompletedBlocks } from '../hooks/useCompletedBlocks';
import { searchOwnerCandidates } from '../../../api/users';
import { useAutoSave, useBackNavigation, useFlushOnPageLeave } from '@ceedcv-maya/shared-hooks-react';
import {
  normalizeTiptapContentForPersistence,
  type TiptapDoc,
} from '@ceedcv-maya/shared-editor-react';
import { useDarkMode } from '@ceedcv-maya/shared-layout-react';
import type { DocumentDetail, DocumentDisplayBlock } from '../../../types/documents';
import { useHierarchy } from '../../hierarchy';
import type { Study, CourseModule } from '../../../types/hierarchy';
import type { Template } from '../../../types/templates';
import { blockToUiState } from '../../templates/blockUiState';
import { applyBlockSaveToDetail } from '../lib/applyBlockSaveToDetail';
import {
  documentBlockContentUnchanged,
  listUnresolvedEditableBlockTitles,
  planDocumentBlockSave,
} from '../lib/blockContentEquals';
import { Button, ConfirmDialog } from '@ceedcv-maya/shared-ui-react';
import { VersionChangelogModal } from '../../../components/VersionChangelogModal';
import { WizardShell, type WizardStepDef } from '../../../components/wizard/WizardShell';
import { normalizeBlockContentForEditor } from '../lib/normalizeBlockContent';

type BlocksViewMode = 'per-block' | 'continuous';

/** Devuelve la clave de localStorage para el modo de vista de un documento concreto.
 *  Escopar por documentId evita que la preferencia de un documento contamine a otro. */
function viewModeStorageKey(id: string | null | undefined): string {
  return id ? `dms.document-edit-view-mode.${id}` : 'dms.document-edit-view-mode';
}

function readStoredViewMode(id: string | null | undefined): BlocksViewMode {
  if (typeof window === 'undefined') return 'per-block';
  const raw = window.localStorage.getItem(viewModeStorageKey(id));
  return raw === 'continuous' ? 'continuous' : 'per-block';
}

import {
  type Step,
  type SummaryConfirmAction,
  type BlockViewTab,
  type ReviewModeView,
  type VisibilityRuleMode,
  type ReviewerView,
  dateIsoToInput,
  validationSuccessBannerMessage,
  effectiveDocumentReviewMode,
  pickActionableDocumentReview,
  isUuidLike,
} from './documentWizardUtils';
import { useDocumentMigration } from './useDocumentMigration';
import { useDocumentCommentHandlers } from './useDocumentCommentHandlers';
import { useDocumentStep1Form } from './useDocumentStep1Form';
import { DocumentPropertiesStep } from './DocumentPropertiesStep';
import { DocumentBlocksStep } from './DocumentBlocksStep';
import { DocumentSummaryStep } from './DocumentSummaryStep';

type Props = {
  documentId?: string | null;
  templateId?: string | null;
  mode?: 'edit' | 'validate';
  /** Documento origen al continuar/clonar; activa el paso de migración si su plantilla tiene versión nueva. */
  sourceDocumentId?: string | null;
  /**
   * 'clone' (por defecto): se crea un documento nuevo en la versión nueva.
   * 'upgrade': se actualiza ESTE documento (nueva versión) in-situ a la versión nueva.
   */
  migrationMode?: 'clone' | 'upgrade';
};

/**
 * Asistente de edición de documento (3 pasos, sin usuarios/validadores).
 * Reutiliza estética y piezas de plantillas (BlockNote, preview HTML) sin acoplar al flujo de TemplateWizard.
 */
export function DocumentWizard({ documentId, templateId, mode = 'edit', sourceDocumentId, migrationMode = 'clone' }: Props) {
  const navigate = useNavigate();
  const { t } = useTranslation(['documents', 'common']);
  const queryClient = useQueryClient();
  const { profile, hasPermission } = useUserProfile();
  const location = useLocation();
  const { isDark } = useDarkMode();

  const [step, setStep] = useState<Step>('properties');
  const [completedSteps, setCompletedSteps] = useState<Step[]>([]);

  // Paso de migración:
  // - clone (creación): al continuar un documento origen cuya plantilla tiene versión nueva.
  // - upgrade (versionado): al iniciar nueva versión de ESTE documento con plantilla nueva.
  const isUpgradeMigration = migrationMode === 'upgrade' && !!documentId;
  const {
    migrationPayload,
    migrationChoices,
    removedBlockChoices,
    pendingMigrationBlocks,
    setPendingMigrationBlocks,
    upgradePending,
    showMigrationStep,
    setMigrationChoice,
    setRemovedBlockChoice,
    buildMigratedBlocks,
    buildRemovedBlockActions,
    computePendingMigrationBlocks,
  } = useDocumentMigration({ documentId, sourceDocumentId, isUpgradeMigration });

  const [detail, setDetail] = useState<DocumentDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  const step1Form = useDocumentStep1Form();
  const {
    title, setTitle,
    deliveryDeadline, setDeliveryDeadline,
    studyTypeId, setStudyTypeId,
    studyId, setStudyId,
    moduleId, setModuleId,
    teamId, setTeamId,
    handleStep1Submit,
    clearStep1Errors,
    setStep1Error,
  } = step1Form;
  // currentUserId sale del perfil compartido (useUserProfile) — sin fetchMe() redundante.
  const currentUserId = profile?.id ?? null;
  const [newOwnerForDoc, setNewOwnerForDoc] = useState<{ id: string; name: string } | null>(null);
  const [ownerQuery, setOwnerQuery] = useState('');
  const [ownerResults, setOwnerResults] = useState<import('../../../types/users').User[]>([]);
  const [ownerSearching, setOwnerSearching] = useState(false);
  const [saving, setSaving] = useState(false);
  const [submittingForReview, setSubmittingForReview] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [transferError, setTransferError] = useState<string | null>(null);

  const [activeBlockKey, setActiveBlockKey] = useState<string | null>(null);
  const [summaryBlockKey, setSummaryBlockKey] = useState<string | null>(null);
  const [summaryBlockTab, setSummaryBlockTab] = useState<BlockViewTab>('content');
  const [blockSaveError, setBlockSaveError] = useState<string | null>(null);
  const [summaryError, setSummaryError] = useState<string | null>(null);
  const [documentReviewers, setDocumentReviewers] = useState<ReviewerView[]>([]);
  const [reviewerListKind, setReviewerListKind] = useState<'document' | 'template_fallback' | 'none'>('none');
  const [documentReviewMode, setDocumentReviewMode] = useState<ReviewModeView>('parallel');
  const [summaryConfirmAction, setSummaryConfirmAction] = useState<SummaryConfirmAction>(null);
  const [showChangelogModal, setShowChangelogModal] = useState(false);
  const [changelogModalError, setChangelogModalError] = useState<string | null>(null);
  const [showNoValidatorsDocModal, setShowNoValidatorsDocModal] = useState(false);

  const [template, setTemplate] = useState<Template | null>(null);
  const [loadingTemplate, setLoadingTemplate] = useState(false);

  const { hierarchy, teams: availableTeams, loading: hierarchyLoading } = useHierarchy();
  const [blockViewTab, setBlockViewTab] = useState<BlockViewTab>('content');
  const [validationReviewLoading, setValidationReviewLoading] = useState(false);
  const [validationSetupError, setValidationSetupError] = useState<string | null>(null);
  const [actionableReviewId, setActionableReviewId] = useState<string | null>(null);
  const [validateConfirm, setValidateConfirm] = useState<null | 'approve' | 'reject'>(null);
  const [validationActionLoading, setValidationActionLoading] = useState(false);
  const [validationModalError, setValidationModalError] = useState<string | null>(null);
  const [, setLocalContent] = useState<unknown>(null);
  const [showDeleteBlockConfirm, setShowDeleteBlockConfirm] = useState(false);
  const [emptyEditableBlocksModal, setEmptyEditableBlocksModal] = useState<string[] | null>(null);
  const activeBlockRef = useRef<DocumentDisplayBlock | null>(null);
  const detailRef = useRef<DocumentDetail | null>(null);
  detailRef.current = detail;
  const [isEditorFullscreen, setIsEditorFullscreen] = useState(false);

  // Review comments for creator-edit mode (mirrors TemplateWizard + WizardStep2Blocks).
  // Sourced from the shared TanStack Query cache (useDocumentCommentsQuery) so the
  // DocumentPreviewPage and the wizard reuse the same in-memory comments.
  const [showDocumentCommentPanel, setShowDocumentCommentPanel] = useState(false);
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const lastSavedContentRef = useRef<unknown>(null);
  const localContentRef = useRef<unknown>(null);
  const editorFlushRef = useRef<(() => void | Promise<void>) | null>(null);

  const applyLocalContent = useCallback((content: unknown) => {
    localContentRef.current = content;
    setLocalContent(content);
  }, []);

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
  const selectedTemplateVersionId = locationState?.templateVersionId ?? null;
  const selectedTemplateVersionUuid = isUuidLike(selectedTemplateVersionId)
    ? selectedTemplateVersionId
    : null;
  const processBackTo = useMemo(() => {
    const effectiveProcessId = locationProcessId ?? template?.process_id ?? null;
    return effectiveProcessId ? `/processes/${effectiveProcessId}` : '/processes';
  }, [locationProcessId, template?.process_id]);
  // Salida del asistente: pila backTo del listado de origen, o proceso/listado de procesos.
  const { goBack } = useBackNavigation({ fallback: processBackTo });

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
  }, [documentId, setTitle, setDeliveryDeadline, setStudyTypeId, setStudyId, setModuleId, setTeamId]);

  const {
    reviewComments,
    documentCommentLoading,
    documentCommentSubmitError,
    handleDocumentCommentSend,
    handleDocumentCommentEdit,
    handleDocumentCommentDelete,
    handleDocumentCommentMarkAsRead,
    handleDocumentCommentMarkAllBlockAsRead,
  } = useDocumentCommentHandlers({
    documentId,
    hasDetail: !!detail,
    activeBlockRef,
    profileName: profile?.name,
  });

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
  }, [documentId, setTitle, setDeliveryDeadline, setStudyTypeId, setStudyId, setModuleId, setTeamId]);

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
          setTemplate(res);
        }
      } catch (e) {
        // No tragar el fallo: sin plantilla el wizard nuevo queda en blanco.
        console.error('No se pudo cargar la plantilla del documento', e);
        if (!cancelled) {
          setTemplate(null);
          setFormError(e instanceof Error ? e.message : 'No se pudo cargar la plantilla.');
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

  // biome-ignore lint/correctness/useExhaustiveDependencies: documentId intentionally re-runs the reset on document switch; body reads `mode`.
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

  // biome-ignore lint/correctness/useExhaustiveDependencies: keyed on specific detail fields (id/status/deadline) on purpose — re-running on every detail mutation would re-route steps mid-edit; setModuleId is stable and location.state is read on create only.
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
    // En upgrade de versión nunca saltamos a bloques: hay que pasar por migración.
    if (isUpgradeMigration) {
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
  }, [detail?.id, detail?.status, detail?.delivery_deadline, forcePropertiesStep, mode, isUpgradeMigration]);

  useEffect(() => {
    if (mode === 'validate') return;
    if (!detail || !returnToSummary) return;
    setCompletedSteps(['properties', 'blocks']);
    setStep('summary');
  }, [detail, returnToSummary, mode]);

  // biome-ignore lint/correctness/useExhaustiveDependencies: keyed on detail.id/status on purpose — loads validation data once per document/status; broader detail deps would re-fetch on every detail mutation.
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
    // El usuario actual sale del perfil compartido; si aún no se ha resuelto,
    // el efecto se relanza cuando llegue (dep `currentUserId`).
    if (!currentUserId) {
      return;
    }
    let cancelled = false;
    setValidationReviewLoading(true);
    setValidationSetupError(null);
    setActionableReviewId(null);
    void (async () => {
      try {
        const [reviews, templateResp] = await Promise.all([
          fetchDocumentReviews(detail.id),
          fetchTemplate(detail.template_id),
        ]);
        if (cancelled) return;
        const reviewMode = effectiveDocumentReviewMode(templateResp);
        const actionable = pickActionableDocumentReview(reviews, currentUserId, reviewMode);
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
  }, [isValidateMode, detail?.id, detail?.status, currentUserId]);

  // Auto-selección si solo hay una opción disponible
  useEffect(() => {
    if (documentId || hierarchyLoading || hierarchy.length === 0 || studyTypeId) return;
    if (hierarchy.length === 1) setStudyTypeId(String(hierarchy[0].id));
  }, [documentId, hierarchy, hierarchyLoading, studyTypeId, setStudyTypeId]);

  useEffect(() => {
    if (documentId || !studyTypeId || studyId) return;
    const typeNode = hierarchy.find((t) => String(t.id) === studyTypeId);
    if (!typeNode) return;
    if ((typeNode.studies ?? []).length === 1) setStudyId(String(typeNode.studies[0].id));
  }, [documentId, hierarchy, studyTypeId, studyId, setStudyId]);

  useEffect(() => {
    if (documentId || !studyId || moduleId) return;
    const allStudiesFlat = hierarchy.flatMap((t) => t.studies ?? []);
    const studyNode = allStudiesFlat.find((s) => String(s.id) === studyId);
    if (!studyNode) return;
    if ((studyNode.course_modules ?? []).length === 1) setModuleId(String(studyNode.course_modules[0].id));
  }, [documentId, hierarchy, studyId, moduleId, setModuleId]);

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
  /** Solo hay revisión de documento si la plantilla define validadores de documento (no revisores de plantilla). */
  const willSubmitDocumentToReview = reviewerListKind === 'document';
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
    setStudyTypeId,
    setStudyId,
    setModuleId,
    setTeamId,
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

  // biome-ignore lint/correctness/useExhaustiveDependencies: summaryBlockKey is the intentional trigger to reset the tab when the selected block changes.
  useEffect(() => {
    setSummaryBlockTab('content');
  }, [summaryBlockKey]);

  // biome-ignore lint/correctness/useExhaustiveDependencies: keyed on detail id/status/template_version_id intentionally — reloads reviewers when any of those change.
  useEffect(() => {
    const docId = detail?.id;
    if (!docId) {
      return;
    }
    let cancelled = false;
    const loadDocumentReviewers = async () => {
      setSummaryError(null);
      try {
        // Pool resuelto en backend desde la versión de plantilla anclada (misma fuente
        // que el envío a validar). No depende del acceso de lectura a la plantilla, por
        // lo que el titular del documento ve siempre sus validadores.
        const pool = await fetchDocumentReviewers(docId);
        if (cancelled) return;
        setReviewerListKind(pool.kind);
        setDocumentReviewMode(pool.review_mode);
        setDocumentReviewers(
          pool.reviewers.map((r) => ({
            id: r.id,
            name: r.name ?? `Usuario no encontrado (${r.id.slice(0, 8)}...)`,
            resolved: r.name != null,
          })),
        );
      } catch (e) {
        if (!cancelled) {
          setSummaryError(e instanceof Error ? e.message : 'No se pudieron cargar los validadores de documento.');
          setDocumentReviewers([]);
          setReviewerListKind('none');
        }
      }
    };
    void loadDocumentReviewers();
    return () => {
      cancelled = true;
    };
  }, [detail?.id, detail?.status, detail?.template_version_id]);

  useEffect(() => {
    // DMS-F12: el flujo de documento NUEVO (templateId sin documentId) lo gestiona
    // el efecto de arriba. Este efecto solo resuelve la plantilla de un documento
    // YA cargado; así ambos efectos son mutuamente excluyentes y no compiten por
    // escribir `template` (evita la carrera/doble-fetch bajo render concurrente).
    if (!documentId) return;
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
          setTemplate(templateResp);
        }
      } catch (e) {
        // La plantilla es complementaria aquí (el detalle ya cargó); no silenciar el error.
        console.error('No se pudo cargar la plantilla del documento', e);
        if (!cancelled) {
          setTemplate(null);
        }
      }
    };
    void loadTemplate();
    return () => {
      cancelled = true;
    };
  }, [documentId, detail?.template_id, templateId]);

  // Catálogo cacheado de procesos (TanStack Query, staleTime 60s) en lugar de fetch manual.
  const wizardProcessId = template?.process_id ?? locationProcessId ?? null;
  const processesQuery = useProcessesQuery(undefined, { enabled: !!wizardProcessId });
  const processSubtitle = useMemo<string | null>(() => {
    if (!wizardProcessId) return null;
    // 1er .data: TanStack Query; 2º .data: envelope paginado de fetchProcesses (no migrado a contrato pelado).
    const selectedProcess = processesQuery.data?.data.find((p) => p.id === wizardProcessId) ?? null;
    if (!selectedProcess) return null;
    return `Proceso: ${selectedProcess.code} — ${selectedProcess.name}`;
  }, [wizardProcessId, processesQuery.data]);

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

  // biome-ignore lint/correctness/useExhaustiveDependencies: activeBlockKey is the intentional trigger to reset the tab when the active block changes.
  useEffect(() => {
    setBlockViewTab('content');
  }, [activeBlockKey]);

  const doSave = useCallback(async (): Promise<boolean> => {
    const block = activeBlockRef.current;
    if (!block || !isDraft || blockToUiState(block) === 'locked') return false;
    const blockId = block.document_block_id;
    if (!blockId) return false;

    const plan = planDocumentBlockSave(
      localContentRef.current,
      lastSavedContentRef.current,
      block.content ?? null,
      block.default_content ?? null,
      block.block_state,
    );

    if (plan.action === 'skip') {
      lastSavedContentRef.current = localContentRef.current;
      return false;
    }

    setBlockSaveError(null);
    try {
      if (!documentId) return false;
      const saved = await updateDocumentBlock(documentId, blockId, plan.payload);
      setDetail((prev) => (prev ? applyBlockSaveToDetail(prev, blockId, saved) : prev));
      lastSavedContentRef.current = localContentRef.current;
      return true;
    } catch (e) {
      const msg = e instanceof ApiHttpError ? e.message : e instanceof Error ? e.message : 'Error al guardar el bloque.';
      setBlockSaveError(msg);
      throw e;
    }
  }, [documentId, isDraft]);

  const { saveStatus, triggerSave, forceSave } = useAutoSave(doSave, 1500);

  const handleDocumentContentChange = useCallback(
    (content: unknown) => {
      applyLocalContent(content);
      if (documentBlockContentUnchanged(content, lastSavedContentRef.current)) {
        return;
      }
      triggerSave();
    },
    [applyLocalContent, triggerSave],
  );

  const flushBlockSave = useCallback(async () => {
    if (documentBlockContentUnchanged(localContentRef.current, lastSavedContentRef.current)) {
      return;
    }
    await forceSave();
  }, [forceSave]);

  const handleEditorFlush = useCallback(
    async (payload?: unknown) => {
      if (payload != null) {
        if (typeof payload === 'string') {
          applyLocalContent(payload);
        } else {
          applyLocalContent(normalizeTiptapContentForPersistence((payload as TiptapDoc).content));
        }
      }
      await flushBlockSave();
    },
    [applyLocalContent, flushBlockSave],
  );

  const flushActiveEditor = useCallback(async () => {
    await editorFlushRef.current?.();
  }, []);

  const persistDocumentBlockChanges = useCallback(async () => {
    await flushActiveEditor();
    await flushBlockSave();
  }, [flushActiveEditor, flushBlockSave]);

  useFlushOnPageLeave(persistDocumentBlockChanges, isDraft && step === 'blocks');

  // Preferencia de UI para el step `blocks`:
  // - 'per-block': sidebar + un editor a la vez (vista clásica).
  // - 'continuous': documento entero estilo Word con edición inline del bloque clicado.
  const [blocksViewMode, setBlocksViewMode] = useState<BlocksViewMode>(() =>
    readStoredViewMode(documentId),
  );

  // Re-leer la preferencia guardada cuando cambia el documento cargado.
  useEffect(() => {
    setBlocksViewMode(readStoredViewMode(documentId));
  }, [documentId]);

  // Persistir la preferencia scoped al documento actual.
  useEffect(() => {
    if (typeof window === 'undefined') return;
    window.localStorage.setItem(viewModeStorageKey(documentId), blocksViewMode);
  }, [documentId, blocksViewMode]);

  // Ayuda visual de finalización por bloque (sin restricciones funcionales).
  // Persistencia por documento en localStorage; indexada por template_block_id.
  const completedBlocks = useCompletedBlocks(documentId ?? null);

  // Sidebar de DESCRIPCIÓN en modo continuo: si está definido, el sidebar
  // muestra la descripción del bloque indicado en lugar de los comentarios.
  const [descriptionBlockKey, setDescriptionBlockKey] = useState<string | null>(null);
  // Si cambia el documento o el modo, resetear panel de descripción.
  // biome-ignore lint/correctness/useExhaustiveDependencies: documentId/blocksViewMode are intentional triggers to reset the description panel on document or view-mode change.
  useEffect(() => {
    setDescriptionBlockKey(null);
  }, [documentId, blocksViewMode]);

  // Modo "focus" para la vista continua: oculta el wizard shell y deja el
  // documento a pantalla completa. Solo aplica con blocksViewMode === 'continuous'.
  const [isContinuousFullscreen, setIsContinuousFullscreen] = useState(false);
  useEffect(() => {
    if (blocksViewMode !== 'continuous') setIsContinuousFullscreen(false);
  }, [blocksViewMode]);
  // Hide the app sidebar while the continuous page fullscreen is active so the
  // document overlay covers the viewport instead of sitting behind the sidebar.
  useEffect(() => {
    const root = document.documentElement;
    root.classList.toggle('continuous-fullscreen', isContinuousFullscreen);
    return () => root.classList.remove('continuous-fullscreen');
  }, [isContinuousFullscreen]);

  useEffect(() => {
    if (!isContinuousFullscreen) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setIsContinuousFullscreen(false);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [isContinuousFullscreen]);

  useEffect(() => {
    activeBlockRef.current = activeBlock;
  }, [activeBlock]);

  // Solo al cambiar de bloque o al cargar otro documento; no rehidratar tras cada autoguardado.
  // biome-ignore lint/correctness/useExhaustiveDependencies: detail?.id is an intentional trigger to rehydrate when a different document loads; the body reads detailRef.current to avoid re-running on every autosave.
  useEffect(() => {
    if (!activeBlockKey || !documentId) return;
    const currentDetail = detailRef.current;
    if (!currentDetail || currentDetail.id !== documentId) return;

    const block = currentDetail.blocks.find(
      (b) => (b.document_block_id ?? b.template_block_id) === activeBlockKey,
    );
    if (!block) return;

    // Usar `block` (recién resuelto y comprobado no-null) en lugar de
    // `activeBlock` (memo derivado que el compilador no puede narrowar aquí).
    const editorBaseline = normalizeBlockContentForEditor(block.content);
    applyLocalContent(editorBaseline);
    // Misma base que muestra el editor (content persistido o default_content de plantilla).
    lastSavedContentRef.current = editorBaseline;
    setShowDocumentCommentPanel(true);
  }, [activeBlockKey, documentId, detail?.id, applyLocalContent]);

  useEffect(() => {
    if (saveStatus === 'saved') {
      lastSavedContentRef.current = localContentRef.current;
    }
  }, [saveStatus]);

  const handleBlockClick = async (key: string) => {
    if (isSaving) return;

    try {
      setIsSaving(true);
      await persistDocumentBlockChanges();
      setActiveBlockKey(key);

    } catch (e) {
      setFormError(e instanceof Error ? e.message : 'Error al guardar bloque');

    } finally {
      setIsSaving(false);
    }
  };

  const handleGoToStep = async(s: Step) => {
    if (step === 'properties'){
      // First run zod schema validation; if it fails, errors are set automatically.
      const valid = await new Promise<DocumentStep1Input | false>((resolve) => {
        void handleStep1Submit(
          (values) => resolve(values),
          () => resolve(false),
        )();
      });
      if (!valid) return;
    }else if (step === 'blocks'){
      try {
        setIsSaving(true);
        await persistDocumentBlockChanges();
      }finally{
        setIsSaving(false);
      }
    }
    if (s === 'properties'){
      setStep(s);
    }
    else if (s === 'migration' && showMigrationStep && completedSteps.includes('properties')){
      setStep(s);
    }
    else if ((s === 'blocks' || s === 'summary') && showMigrationStep && !completedSteps.includes('migration')){
      // No se puede saltar la migración pendiente: redirige al paso de migración.
      if (completedSteps.includes('properties')) setStep('migration');
    }
    else if (s === 'blocks' && completedSteps.includes('properties')){
      setStep(s);
    }
    else if (s === 'summary' && completedSteps.includes('blocks')){
      if (detail) {
        const unresolvedEditable = listUnresolvedEditableBlockTitles(detail.blocks);
        if (unresolvedEditable.length > 0) {
          setEmptyEditableBlocksModal(unresolvedEditable);
          return;
        }
      }
      setStep(s);
    }
  };

  // Aplica el upgrade in-situ (re-ancla + reconcilia) con las elecciones actuales y pasa a bloques.
  const applyUpgradeAndContinue = async () => {
    if (!documentId) return;
    const updated = await applyTemplateMigration(documentId, {
      target_template_version_id: migrationPayload?.target_template_version_id ?? '',
      migrated_blocks: buildMigratedBlocks(),
      removed_block_actions: buildRemovedBlockActions(),
    });
    setDetail(updated);
    setCompletedSteps((prev: Step[]) =>
      Array.from(new Set([...prev, 'properties', 'migration'] as Step[])),
    );
    setStep('blocks');
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

      // Modo migración: no se crea aún; el usuario elige qué contenido antiguo arrastrar.
      if (!documentId && showMigrationStep) {
        setCompletedSteps((prev: Step[]) => (prev.includes('properties') ? prev : [...prev, 'properties']));
        setStep('migration');
        return;
      }

      setSaving(true);
      try {
        if (!documentId) {
          // Creation Mode: Create everything in one call
          if (!templateId) throw new Error(t('errors.noTemplate'));
          if (!template?.process_id) {
            throw new Error(t('errors.templateNoProcess'));
          }

          const targetVersionId = migrationPayload?.target_template_version_id ?? selectedTemplateVersionUuid;
          const created = await createDocument({
            template_id: templateId,
            process_id: template.process_id,
            ...(targetVersionId ? { template_version_id: targetVersionId } : {}),
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

          setDetail((prev: DocumentDetail | null) => (prev ? { ...prev, ...updated, blocks: prev.blocks } : prev));
          setCompletedSteps((prev: Step[]) => Array.from(new Set([...prev, 'properties'] as Step[])));
          if (showMigrationStep) {
            // Upgrade con decisiones: pasar por el paso de migración.
            setStep('migration');
          } else if (upgradePending) {
            // Upgrade sin nada que decidir: aplicar directo (re-ancla + reconcilia) y saltar el paso.
            await applyUpgradeAndContinue();
          } else {
            setStep('blocks');
          }
        }
      } catch (e) {
        setFormError(e instanceof Error ? e.message : 'No se pudieron guardar los datos del documento.');
      } finally {
        setSaving(false);
      }
      return;
    }
    if (step === 'migration') {
      // Gate: exigir elección en todos los bloques accionables (y eliminados en upgrade).
      const pending = computePendingMigrationBlocks();
      if (pending.length > 0) {
        setPendingMigrationBlocks(pending);
        return;
      }

      setSaving(true);
      try {
        if (isUpgradeMigration && documentId) {
          // Upgrade in-situ: actualiza ESTE documento a la versión nueva.
          await applyUpgradeAndContinue();
        } else {
          // Clone: crea un documento nuevo en la versión destino con el contenido migrado.
          if (!templateId || !template?.process_id) {
            throw new Error(t('errors.templateNoProcess'));
          }
          const targetVersionId = migrationPayload?.target_template_version_id ?? selectedTemplateVersionUuid;
          const created = await createDocument({
            template_id: templateId,
            process_id: template.process_id,
            ...(targetVersionId ? { template_version_id: targetVersionId } : {}),
            title: title.trim(),
            study_type_id: studyTypeId || undefined,
            study_id: studyId || undefined,
            module_id: moduleId || undefined,
            team_id: teamId || undefined,
            delivery_deadline: deliveryDeadline || null,
            migrated_blocks: buildMigratedBlocks(),
          });

          navigate(`/documents/${created.id}/editor`, {
            replace: true,
            state: {
              processId: locationProcessId,
              moduleId: locationModuleId,
              fromTemplateSelection: true,
            },
          });
          setCompletedSteps((prev: Step[]) =>
            Array.from(new Set([...prev, 'properties', 'migration'] as Step[])),
          );
          setStep('blocks');
        }
      } catch (e) {
        setFormError(e instanceof Error ? e.message : 'No se pudo aplicar la migración.');
      } finally {
        setSaving(false);
      }
      return;
    }
    if (step === 'blocks') {
      try {
        setIsSaving(true);
        await persistDocumentBlockChanges();
      }finally{
        setIsSaving(false)
      }
      if (!detail) {
        setFormError(t('errors.stillLoading'));
        return;
      }
      const unresolvedEditable = listUnresolvedEditableBlockTitles(detail.blocks);
      if (unresolvedEditable.length > 0) {
        setEmptyEditableBlocksModal(unresolvedEditable);
        return;
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
      goBack();
    }
  };

  const handleSubmitForReview = async (changelog: string) => {
    if (!detail || !['draft', 'rejected'].includes(detail.status)) {
      return false;
    }
    setSummaryError(null);
    setChangelogModalError(null);
    setSubmittingForReview(true);
    try {
      const updated = await submitDocumentForReview(detail.id, changelog);
      setDetail((prev) => (prev ? { ...prev, ...updated, blocks: prev.blocks } : prev));
      setShowChangelogModal(false);
      navigate(processBackTo, {
        state: { tab: 'documents', documentSubmittedForReview: true },
      });
      return true;
    } catch (e) {
      const message = e instanceof Error ? e.message : 'No se pudo enviar el documento a validar.';
      setChangelogModalError(message);
      return false;
    } finally {
      setSubmittingForReview(false);
    }
  };

  const handleConfirmSummaryAction = async () => {
    if (summaryConfirmAction === 'save') {
      setSummaryConfirmAction(null);
      navigate(processBackTo, { state: { tab: 'documents' } });
    }
  };

  const handleTransferOwnershipDoc = async () => {
    if (!documentId || !newOwnerForDoc) return;
    setSaving(true);
    setTransferError(null);
    try {
      await delegateDocument(documentId, newOwnerForDoc.id);
      setNewOwnerForDoc(null);
      navigate(processBackTo, { state: { tab: 'documents' } });
    } catch (e) {
      setTransferError(e instanceof Error ? e.message : 'No se pudo cambiar el propietario del documento.');
    } finally {
      setSaving(false);
    }
  };

  const documentChangelogIntro = (
    <div className="space-y-2">
      <p>
        {willSubmitDocumentToReview
          ? 'Se enviará una notificación a los validadores del documento configurados en la plantilla.'
          : 'No hay validadores de documento en la plantilla. El documento se publicará directamente sin pasar por revisión.'}
      </p>
      {willSubmitDocumentToReview && documentReviewers.length > 0 ? (
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
      ) : null}
      <p>{t('wizard.submitWarning')}</p>
    </div>
  );

  const handleApproveValidation = async () => {
    if (!documentId || !actionableReviewId) {
      setValidationModalError('Faltan datos críticos para procesar la revisión.');
      return false;
    }
    setValidationModalError(null);
    setSummaryError(null);
    setValidationActionLoading(true);
    try {
      const updated = await approveDocumentReview(documentId, actionableReviewId, null);
      await queryClient.invalidateQueries({ queryKey: ['documents'] });
      await refreshDmsDashboardQuery(queryClient);
      setValidateConfirm(null);
      navigate(processBackTo, {
        state: { documentValidationBanner: validationSuccessBannerMessage(updated, 'approve'), tab: 'documents' },
      });
    } catch (e) {
      setValidationModalError(e instanceof ApiHttpError ? e.message : 'No se pudo aprobar la revisión.');
      return false;
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
      return false;
    }
    setValidationModalError(null);
    setSummaryError(null);
    setValidationActionLoading(true);
    try {
      const updated = await rejectDocumentReview(documentId, actionableReviewId, null);
      await queryClient.invalidateQueries({ queryKey: ['documents'] });
      await refreshDmsDashboardQuery(queryClient);
      setValidateConfirm(null);
      navigate(processBackTo, {
        state: { documentValidationBanner: validationSuccessBannerMessage(updated, 'reject'), tab: 'documents' },
      });
    } catch (e) {
      setValidationModalError(e instanceof ApiHttpError ? e.message : 'No se pudo rechazar la revisión.');
      return false;
    } finally {
      setValidationActionLoading(false);
    }
  };

  const stepsData: WizardStepDef<Step>[] = [
    { id: 'properties', label: 'Propiedades', sub: 'Título y metadatos' },
    ...(showMigrationStep
      ? [{ id: 'migration' as const, label: t('migration.stepLabel'), sub: t('migration.stepSub') }]
      : []),
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
          {isValidateMode
            ? t('common:navigation.backToPanel')
            : t('common:navigation.backToList')}
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
          {t('common:navigation.backToPanel')}
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
          {t('common:navigation.backToPanel')}
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
      {!isValidateMode && step === 'summary' && newOwnerForDoc && (
        <Button
          type="button"
          variant="primary"
          size="sm"
          loading={saving}
          onClick={() => void handleTransferOwnershipDoc()}
        >
          Cambiar propietario
        </Button>
      )}
      {!isValidateMode && step === 'summary' && !newOwnerForDoc && (
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
            onClick={() => {
              setChangelogModalError(null);
              setShowChangelogModal(true);
            }}
          >
            {willSubmitDocumentToReview ? 'Enviar a validar' : 'Publicar'}
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

  const handleWizardBack = async () => {
    const order: Step[] = stepsData.map((s) => s.id);
    const idx = order.indexOf(step);
    if (isValidateMode) {
      navigate('/dashboard');
      return;
    }
    if (idx > 0) {
      if (step === "blocks"){
        try {
          setIsSaving(true);
          await persistDocumentBlockChanges();
        }finally{
          setIsSaving(false)
        }
      }
      setStep(order[idx - 1]!);
    } else {
      goBack();
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
      backLabel={
        isValidateMode ? t('common:navigation.backToMainPanel') : t('common:actions.back')
      }
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
        <DocumentPropertiesStep
          form={step1Form}
          isDraft={isDraft}
          formError={formError}
          template={template}
          templateScopeLabel={templateScopeLabel}
          visibility={{
            rule: visibilityRule,
            studyTypeEditable,
            studyEditable,
            moduleEditable,
            teamEditable,
            requireStudyType,
            requireStudy,
            requireModule,
            isGlobalAcademicMode,
            fixedTeamId,
          }}
          hierarchy={hierarchy}
          hierarchyLoading={hierarchyLoading}
          availableTeams={availableTeams}
          filteredStudies={filteredStudies}
          filteredModules={filteredModules}
          detail={detail}
          currentUserId={currentUserId}
          profileName={profile?.name}
          ownerSearch={{
            query: ownerQuery,
            setQuery: setOwnerQuery,
            results: ownerResults,
            setResults: setOwnerResults,
            searching: ownerSearching,
            newOwner: newOwnerForDoc,
            setNewOwner: setNewOwnerForDoc,
          }}
          onChangeTemplate={() => navigate('/documents/new', { state: location.state })}
        />
      )}

      {!isValidateMode && step === 'migration' && migrationPayload && (
        <DocumentMigrationStep
          payload={migrationPayload}
          choices={migrationChoices}
          onChoose={setMigrationChoice}
          removedChoices={removedBlockChoices}
          onChooseRemoved={setRemovedBlockChoice}
          allowRemovedDecision={isUpgradeMigration}
        />
      )}

      {!isValidateMode && step === 'blocks' && (
        <DocumentBlocksStep
          documentId={documentId}
          detail={detail}
          sortedBlocks={sortedBlocks}
          activeBlock={activeBlock}
          activeBlockKey={activeBlockKey}
          isDraft={isDraft}
          canEditBlocks={canEditBlocks}
          canDeleteOptionalBlock={canDeleteOptionalBlock}
          isSidebarCollapsed={isSidebarCollapsed}
          setIsSidebarCollapsed={setIsSidebarCollapsed}
          blockViewTab={blockViewTab}
          setBlockViewTab={setBlockViewTab}
          completedBlocks={completedBlocks}
          descriptionBlockKey={descriptionBlockKey}
          setDescriptionBlockKey={setDescriptionBlockKey}
          onBlockClick={handleBlockClick}
          onContinue={handleContinue}
          onShowDeleteBlock={() => setShowDeleteBlockConfirm(true)}
          onPersistBlockContent={async (blockId, payload) => {
            if (!documentId || !blockId) return;
            const saved = await updateDocumentBlock(documentId, blockId, payload);
            setDetail((prev) => (prev ? applyBlockSaveToDetail(prev, blockId, saved) : prev));
          }}
          editor={{
            isDark,
            isEditorFullscreen,
            setIsEditorFullscreen,
            onFullscreenChange: handleEditorFullscreenChange,
            onContentChange: handleDocumentContentChange,
            onFlush: handleEditorFlush,
            editorFlushRef,
            saveStatus,
            blockSaveError,
            isSaving,
          }}
          viewMode={{
            mode: blocksViewMode,
            setMode: setBlocksViewMode,
            isContinuousFullscreen,
            setIsContinuousFullscreen,
          }}
          comments={{
            reviewComments,
            showPanel: showDocumentCommentPanel,
            setShowPanel: setShowDocumentCommentPanel,
            loading: documentCommentLoading,
            error: documentCommentSubmitError,
            onSend: handleDocumentCommentSend,
            onEdit: handleDocumentCommentEdit,
            onDelete: handleDocumentCommentDelete,
            onMarkAsRead: handleDocumentCommentMarkAsRead,
            onMarkAllBlockAsRead: handleDocumentCommentMarkAllBlockAsRead,
            canAdd: canCommentOnDocument(detail?.status),
            canDeleteAny: canDeleteBlockComment(hasPermission),
            currentUserId,
          }}
        />
      )}

      {step === 'summary' && detail && (
        <DocumentSummaryStep
          detail={detail}
          isValidateMode={isValidateMode}
          transferError={transferError}
          visibilityRule={visibilityRule}
          reviewerListKind={reviewerListKind}
          documentReviewers={documentReviewers}
          summaryError={summaryError}
          sortedBlocks={sortedBlocks}
          summaryBlockKey={summaryBlockKey}
          onSelectSummaryBlock={setSummaryBlockKey}
          saveStatus={saveStatus}
          summaryBlockTab={summaryBlockTab}
          onSelectSummaryTab={setSummaryBlockTab}
          selectedSummaryBlock={selectedSummaryBlock}
          onPreview={() =>
            navigate(`/documents/${documentId}`, {
              state: {
                returnToStep: isValidateMode || !!documentId ? 'summary' : undefined,
                returnToValidate: isValidateMode,
                backTo: processBackTo,
                forceBackTo: !documentId && !isValidateMode,
              },
            })
          }
        />
      )}
      </>
    </WizardShell>
    <ConfirmDialog
        open={emptyEditableBlocksModal !== null}
        title={t('documents:wizard.unfilledBlocksTitle')}
        description={
          <div className="space-y-2">
            <p>{t('wizard.fillBlocksFirst')}</p>
            <ul className="space-y-1">
              {(emptyEditableBlocksModal ?? []).map((name, i) => (
                <li key={i} className="font-medium">• {name}</li>
              ))}
            </ul>
          </div>
        }
        confirmLabel={t('common:actions.understood')}
        onConfirm={() => setEmptyEditableBlocksModal(null)}
        onCancel={() => setEmptyEditableBlocksModal(null)}
      />
    <ConfirmDialog
        open={pendingMigrationBlocks !== null}
        title={t('documents:migration.pendingTitle')}
        description={
          <div className="space-y-2">
            <p>{t('documents:migration.pendingDescription')}</p>
            <ul className="space-y-1">
              {(pendingMigrationBlocks ?? []).map((name, i) => (
                <li key={i} className="font-medium">• {name}</li>
              ))}
            </ul>
          </div>
        }
        confirmLabel={t('common:actions.understood')}
        onConfirm={() => setPendingMigrationBlocks(null)}
        onCancel={() => setPendingMigrationBlocks(null)}
      />
    <ConfirmDialog
        open={showDeleteBlockConfirm}
        variant="danger"
        title={t('common:confirm.deleteBlock')}
        description={t('wizard.deleteBlockConfirm')}
        confirmLabel={t('common:actions.delete')}
        cancelLabel={t('common:actions.cancel')}
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
        description={t('approve.description')}
        confirmLabel={t('common:actions.approve')}
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
        open={summaryConfirmAction === 'save'}
        title={t('wizard.confirmSave')}
        description={t('wizard.saveExitDescription')}
        confirmLabel={t('wizard.saveExitConfirm')}
        cancelLabel={t('common:actions.cancel')}
        variant="teal"
        onCancel={() => setSummaryConfirmAction(null)}
        onConfirm={() => void handleConfirmSummaryAction()}
      />
      <VersionChangelogModal
        open={showChangelogModal}
        title={willSubmitDocumentToReview ? t('wizard.changelogSubmitTitle') : t('wizard.changelogPublishTitle')}
        intro={documentChangelogIntro}
        initialValue={detail?.submission_changelog}
        confirmLabel={
          submittingForReview
            ? willSubmitDocumentToReview
              ? t('wizard.sending')
              : t('wizard.publishing')
            : willSubmitDocumentToReview
              ? t('wizard.submitConfirm')
              : t('wizard.publishConfirm')
        }
        loading={submittingForReview}
        error={changelogModalError}
        onCancel={() => {
          setShowChangelogModal(false);
          setChangelogModalError(null);
        }}
        onConfirm={handleSubmitForReview}
      />
      <ConfirmDialog
        open={showNoValidatorsDocModal}
        title={t('wizard.noValidators')}
        description={t('wizard.noValidatorsDescription')}
        confirmLabel={t('wizard.continueAnyway')}
        cancelLabel={t('common:actions.cancel')}
        onConfirm={() => {
          setShowNoValidatorsDocModal(false);
          if (!detail) {
            setFormError(t('errors.stillLoading'));
            return;
          }
          const unresolvedEditable = listUnresolvedEditableBlockTitles(detail.blocks);
          if (unresolvedEditable.length > 0) {
            setEmptyEditableBlocksModal(unresolvedEditable);
            return;
          }
          setCompletedSteps((prev: Step[]) => Array.from(new Set([...prev, 'blocks'] as Step[])));
          setStep('summary');
        }}
        onCancel={() => setShowNoValidatorsDocModal(false)}
      />
    </>
  );
}
