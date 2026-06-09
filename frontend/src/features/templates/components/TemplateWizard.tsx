import { useState, useMemo, useRef, useEffect } from 'react';
import { FormProvider, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useNavigate, useBlocker } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { WizardShell, type WizardStepDef } from '../../../components/wizard/WizardShell';
import type { Template } from '../../../types/templates';
import type { ReviewMode } from '../../../types/templates';
import { blockTypeRequiresContent, type TemplateBlock } from '../../../types/blocks';
import {
  updateTemplate as apiUpdateTemplate,
  createTemplate as apiCreateTemplate,
  submitTemplateForReview as apiSubmitTemplateForReview,
  syncTemplateValidators,
  syncDocumentReviewers,
} from '../../../api/templates';
import { ApiHttpError } from '../../../api/http';
import { VersionChangelogModal } from '../../../components/VersionChangelogModal';
import { Button, ConfirmDialog } from '@ceedcv-maya/shared-ui-react';
import { useProcessesQuery } from '../../../hooks/useProcesses';
import {
  templateCommentsKey,
  type TemplateCommentsResponse,
} from '../hooks/useTemplateComments';
import { useUserProfile } from '../../../features/user-profile';
import { WizardStep1Properties } from './WizardStep1Properties';
import { WizardStep2Blocks, type WizardStep2BlocksHandle } from './WizardStep2Blocks';
import { WizardStep3Users, type ValidatorEntry } from './WizardStep3Users';
import type { BlockComment } from './BlockCommentsCard';
import { WizardStep4Summary } from './WizardStep4Summary';
import {
  templateStep1Schema,
  emptyTemplateStep1,
  type TemplateStep1Input,
} from '../schemas/templateStep1';

type Step = 'properties' | 'blocks' | 'users' | 'summary';

type Props = {
  template?: Template | null;
  initialTemplate?: Template | null;
  /** Proceso al que se asocia la plantilla cuando se crea desde el contexto de un proceso. */
  processId?: string;
};

export function TemplateWizard({ template: templateProp, initialTemplate, processId }: Props) {
  const { t } = useTranslation(['templates', 'common']);
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { profile } = useUserProfile();
  const initial = templateProp || initialTemplate;
  const processBackTo = useMemo(() => {
    const effectiveProcessId = processId ?? templateProp?.process_id ?? initialTemplate?.process_id ?? null;
    return effectiveProcessId ? `/procesos/${effectiveProcessId}` : '/dashboard';
  }, [initialTemplate?.process_id, processId, templateProp?.process_id]);

  // Rejected templates start on the blocks step so the creator sees comment badges immediately.
  const [step, setStep] = useState<Step>(
    initial?.id && initial?.has_review_comments ? 'blocks' : 'properties',
  );
  const [completedSteps, setCompletedSteps] = useState<Step[]>(
    initial?.id ? (['properties', 'blocks', 'users'] as Step[]) : [],
  );
  const [leaveGuard, setLeaveGuard] = useState(false);
  const [hasInvalidBlocks, setHasInvalidBlocks] = useState(false);
  const [invalidBlocksModal, setInvalidBlocksModal] = useState<{ onProceed?: (remaining: TemplateBlock[]) => void } | null>(null);
  const [blockInvariantModal, setBlockInvariantModal] = useState<string | null>(null);

  // Template state (synchronized with API)
  const [template, setTemplate] = useState<Template | null>(initial || null);

  // Step 1: RHF + Zod
  const step1Methods = useForm<TemplateStep1Input>({
    defaultValues: {
      ...emptyTemplateStep1,
      name: initial?.name || '',
      description: initial?.description || '',
      visibility: initial?.visibility_level || 'personal',
      deliveryDeadline: initial?.delivery_deadline ? initial.delivery_deadline.split('T')[0] : '',
      studyTypeId: initial?.study_type_id || '',
      studyId: initial?.study_id || '',
      moduleId: initial?.module_id || '',
      teamId: initial?.team_id || '',
      themeId: initial?.theme_id ?? '',
    },
    resolver: zodResolver(templateStep1Schema),
    mode: 'onChange',
  });

  // Wizard-level transient errors (blocks gating, cross-step API failures)
  const [errors, setErrors] = useState<{ blocks?: string; api?: string }>({});

  // Step 3: Users state — load existing reviewers when editing
  const [validators, setValidators] = useState<ValidatorEntry[]>(
    initial?.reviewers?.map((r) => ({ userId: r.user_id, name: r.user_name ?? '—' })) ?? [],
  );
  const [documentValidators, setDocumentValidators] = useState<ValidatorEntry[]>(
    initial?.document_reviewer_users
      ?.slice()
      .sort((a, b) => (a.stage ?? 0) - (b.stage ?? 0))
      .map((r) => ({ userId: r.user_id, name: r.user_name ?? '—' })) ?? [],
  );
  const reviewModeLabel = (mode?: string): 'libre' | 'ordenada' =>
    mode === 'sequential' ? 'ordenada' : 'libre';
  const [validationType, setValidationType] = useState<'libre' | 'ordenada'>(
    () => reviewModeLabel(initial?.review_mode),
  );
  const [documentValidationType, setDocumentValidationType] = useState<'libre' | 'ordenada'>(
    () => reviewModeLabel(initial?.document_review_mode ?? initial?.review_mode),
  );

  const handleTemplateValidationModeChange = (mode: 'libre' | 'ordenada') => {
    setValidationType(mode);
    setUsersDirty(true);
    if (template?.id) {
      const reviewMode: ReviewMode = mode === 'ordenada' ? 'sequential' : 'parallel';
      void apiUpdateTemplate(template.id, { review_mode: reviewMode }).catch(() => {/* non-blocking */ });
    }
  };

  const handleDocumentValidationModeChange = (mode: 'libre' | 'ordenada') => {
    setDocumentValidationType(mode);
    setUsersDirty(true);
    if (template?.id) {
      const documentReviewMode: ReviewMode = mode === 'ordenada' ? 'sequential' : 'parallel';
      void apiUpdateTemplate(template.id, { document_review_mode: documentReviewMode }).catch(() => {/* non-blocking */ });
    }
  };

  // Dirty flag: only sync reviewers to API if the user actually changed them in Step 3
  const [usersDirty, setUsersDirty] = useState(false);

  // UI state
  const [saving, setSaving] = useState(false);
  const blocksRef = useRef<WizardStep2BlocksHandle>(null);
  const [permissionError, setPermissionError] = useState<string | null>(null);
  const [showChangelogModal, setShowChangelogModal] = useState(false);
  const [changelogModalMode, setChangelogModalMode] = useState<'submit' | 'publish'>('submit');
  const [changelogModalError, setChangelogModalError] = useState<string | null>(null);
  const [showNoValidatorsModal, setShowNoValidatorsModal] = useState(false);
  const [noValidatorsModalMessage, setNoValidatorsModalMessage] = useState('');
  const [blocksCount, setBlocksCount] = useState(0);
  const [blocksLoading, setBlocksLoading] = useState(true);
  const [wizardBlocks, setWizardBlocks] = useState<TemplateBlock[]>([]);

  const effectiveProcessId =
    processId ?? template?.process_id ?? initial?.process_id ?? null;
  const processesQuery = useProcessesQuery(undefined, {
    enabled: !!effectiveProcessId,
  });
  const processSubtitle = (() => {
    if (!effectiveProcessId) return null;
    const selectedProcess =
      processesQuery.data?.data.find((p) => p.id === effectiveProcessId) ?? null;
    if (!selectedProcess) return null;
    return `Proceso: ${selectedProcess.code} — ${selectedProcess.name}`;
  })();

  useEffect(() => {
    if (step !== 'blocks') return;
    if (blocksLoading || blocksCount < 1) return;
    setErrors((prev) => {
      if (!prev.blocks) return prev;
      const { blocks: _blocks, ...rest } = prev;
      return rest;
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [blocksCount, blocksLoading, step]);

  const blocker = useBlocker(step === 'blocks' && hasInvalidBlocks);
  useEffect(() => {
    if (blocker.state === 'blocked') {
      setInvalidBlocksModal({ onProceed: (_remaining) => blocker.proceed() });
    }
  }, [blocker.state]); // eslint-disable-line react-hooks/exhaustive-deps

  // Clear permission error when user changes visibility selection.
  useEffect(() => {
    const subscription = step1Methods.watch((_value, info) => {
      if (info.name === 'visibility') setPermissionError(null);
    });
    return () => subscription.unsubscribe();
  }, [step1Methods]);

  const handleCommentAdded = (comment: BlockComment) => {
    if (!initial?.id) return;
    queryClient.setQueryData<TemplateCommentsResponse>(
      templateCommentsKey(initial.id),
      (current) => ({ data: [...(current?.data ?? []), comment] }),
    );
  };

  const validateBlocksInvariants = (blocksList: TemplateBlock[]): string | null => {
    const hasEditable = blocksList.some(b => b.block_state === 'editable' || b.block_state === 'modifiable');
    if (!hasEditable) return 'La plantilla debe tener al menos un bloque editable o modificable.';
    const isEmpty = (content: unknown) =>
      content === null || 
      (Array.isArray(content) && content.length === 0);
    const hasEmptyModifiable = blocksList.some(b =>
      b.block_state === 'modifiable' && isEmpty(b.default_content) && blockTypeRequiresContent(b.block_type)
    );
    if (hasEmptyModifiable) return 'Los bloques modificables no pueden estar vacíos: el contenido predeterminado es obligatorio.';
    const hasEmptyLocked = blocksList.some(b =>
      b.block_state === 'locked' && isEmpty(b.default_content) && blockTypeRequiresContent(b.block_type)
    );
    if (hasEmptyLocked) return 'Los bloques bloqueados no pueden estar vacíos.';
    return null;
  };

  // Leído en render para activar la suscripción del proxy de RHF; si solo se
  // accediera dentro del handler, `isDirty` quedaría obsoleto (false).
  const isStep1Dirty = step1Methods.formState.isDirty;

  const handleBackArrow = async () => {
    const order: Step[] = ['properties', 'blocks', 'users', 'summary'];
    const idx = order.indexOf(step);
    if (idx > 0) {
      if (step === 'blocks'){
        await saveBlocks()
      }
      setStep(order[idx - 1]!);
    } else {
      // Primer paso: salir del asistente. Si hay cambios sin guardar en el
      // formulario de propiedades, pedir confirmación antes de abandonar.
      if (isStep1Dirty) {
        setLeaveGuard(true);
        return;
      }
      if (window.history.length <= 1) {
        navigate("/dashboard");
      } else {
        navigate(-1);
      }
    }
  };

  const saveProperties = async (): Promise<boolean> => {
    let success = false;
    await step1Methods.handleSubmit(async (values) => {
    setSaving(true);
    setPermissionError(null);
    try {
      const isUpdate = !!template?.id;
      const visibilityChanged = !isUpdate || values.visibility !== template.visibility_level;

      const payload = {
        name: values.name.trim(),
        description: values.description.trim() || null,
        ...(visibilityChanged ? { visibility_level: values.visibility } : {}),
        delivery_deadline: values.deliveryDeadline ? `${values.deliveryDeadline}T00:00:00Z` : null,
        study_type_id: values.studyTypeId || null,
        study_id: values.studyId || null,
        module_id: values.moduleId || null,
        team_id: values.teamId || null,
        theme_id: values.themeId || null,
        // created_by is transferred explicitly via handleTransferOwnership on the summary step
      };

      let res;
      if (isUpdate) {
        res = await apiUpdateTemplate(template.id, payload);
      } else {
        const effectiveProcessId = processId ?? initial?.process_id ?? undefined;
        if (!effectiveProcessId) {
          throw new Error(
            'Falta el proceso de la plantilla. Crea una plantilla desde un proceso del aside.',
          );
        }
        res = await apiCreateTemplate({
          ...payload,
          visibility_level: values.visibility,
          process_id: effectiveProcessId,
        });
      }
      setTemplate(res.data);
      step1Methods.reset(values);
      setCompletedSteps((prev: Step[]) => Array.from(new Set([...prev, 'properties'])) as Step[]);
      setStep('blocks');
      success = true;
    } catch (e) {
      if (e instanceof ApiHttpError && (e.status === 401 || e.status === 403)) {
        setPermissionError(
          'No tienes permisos para guardar una plantilla con esta visibilidad. ' +
          'Contacta con un coordinador o selecciona una visibilidad diferente.'
        );
      } else {
        setErrors({ api: e instanceof Error ? e.message : 'Error al guardar' });
      }
    } finally {
      setSaving(false);
    }
    })();
    return success;
  };
  
  const openChangelogModal = (mode: 'submit' | 'publish') => {
    const blockInvariantErr = validateBlocksInvariants(wizardBlocks);
    if (blockInvariantErr) {
      setBlockInvariantModal(blockInvariantErr);
      return;
    }
    setChangelogModalError(null);
    setChangelogModalMode(mode);
    setShowChangelogModal(true);
  };

  const handleConfirmChangelogSubmit = async (changelog: string) => {
    if (!template?.id) return false;
    setSaving(true);
    setErrors({});
    setChangelogModalError(null);
    try {
      const res = await apiSubmitTemplateForReview(template.id, changelog);
      setTemplate(res.data);
      setShowChangelogModal(false);
      navigate(processBackTo);
    } catch (e) {
      const message = e instanceof Error ? e.message : 'Error al enviar la plantilla a validación';
      setChangelogModalError(message);
      return false;
    } finally {
      setSaving(false);
    }
  };

  const changelogModalIntro =
    changelogModalMode === 'submit' ? (
      <div className="space-y-4">
        <p className="text-xs text-text-muted">
          Se notificará a los validadores asignados. Una vez enviada, la plantilla no podrá editarse hasta ser aprobada o rechazada.
        </p>
        {validators.length > 0 && (
          <div className="space-y-2">
            <p className="text-xs font-bold uppercase tracking-widest text-text-secondary">
              Validadores de plantilla ({validationType}) — {validators.length}
            </p>
            {validators.map((v, i) => {
              const initials = v.name.split(' ').filter(Boolean).slice(0, 2).map((w) => w[0]?.toUpperCase() ?? '').join('');
              return (
                <div key={v.userId} className="flex items-center gap-2.5">
                  {validationType === 'ordenada' && (
                    <span className="shrink-0 w-5 h-5 rounded-full bg-odoo-purple text-text-inverse text-xs font-bold flex items-center justify-center">
                      {i + 1}
                    </span>
                  )}
                  <span className="shrink-0 w-7 h-7 rounded-full bg-odoo-purple/10 text-odoo-purple text-xs font-black border border-odoo-purple/20 flex items-center justify-center">
                    {initials}
                  </span>
                  <div className="min-w-0">
                    <p className="text-xs font-bold text-text-primary dark:text-text-dark-primary truncate">{v.name}</p>
                    {v.role && <p className="text-xs text-text-secondary uppercase tracking-wider">{v.role}</p>}
                  </div>
                </div>
              );
            })}
          </div>
        )}
        {documentValidators.length > 0 && (
          <div className="space-y-2 pt-3 border-t border-ui-border dark:border-ui-dark-border">
            <p className="text-xs font-bold uppercase tracking-widest text-odoo-teal">
              Validadores de documento ({documentValidationType}) — {documentValidators.length}
            </p>
            {documentValidators.map((v, i) => {
              const initials = v.name.split(' ').filter(Boolean).slice(0, 2).map((w) => w[0]?.toUpperCase() ?? '').join('');
              return (
                <div key={v.userId} className="flex items-center gap-2.5">
                  {documentValidationType === 'ordenada' && (
                    <span className="shrink-0 w-5 h-5 rounded-full bg-odoo-teal text-text-inverse text-xs font-bold flex items-center justify-center">
                      {i + 1}
                    </span>
                  )}
                  <span className="shrink-0 w-7 h-7 rounded-full bg-odoo-teal/10 text-odoo-teal text-xs font-black border border-odoo-teal/20 flex items-center justify-center">
                    {initials}
                  </span>
                  <div className="min-w-0">
                    <p className="text-xs font-bold text-text-primary dark:text-text-dark-primary truncate">{v.name}</p>
                    {v.role && <p className="text-xs text-text-secondary uppercase tracking-wider">{v.role}</p>}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    ) : (
      <p className="text-xs text-text-muted">
        No hay validadores configurados. La plantilla se publicará directamente al confirmar.
      </p>
    );
  const pendingOwnerTransfer = step1Methods.watch('createdBy');

  const handleTransferOwnership = async () => {
    if (!template?.id || !pendingOwnerTransfer) return;
    setSaving(true);
    setErrors({});
    try {
      await apiUpdateTemplate(template.id, { created_by: pendingOwnerTransfer });
      navigate(processBackTo);
    } catch (e) {
      const detail =
        e instanceof ApiHttpError
          ? e.message
          : e instanceof Error
            ? e.message
            : 'Error al transferir la propiedad';
      setErrors({ api: detail });
    } finally {
      setSaving(false);
    }
  };

  const saveUsers = async () => {
    if (!template?.id) return;
    if (blocksLoading || blocksCount < 1) {
      setErrors({ api: 'Añade al menos un bloque antes de continuar.' });
      return;
    }

    const currentVisibility = step1Methods.getValues('visibility');
    if (currentVisibility !== 'personal') {
      const missingTemplate = validators.length === 0;
      const missingDocument = documentValidators.length === 0;
      if (missingTemplate || missingDocument) {
        const msg =
          missingTemplate && missingDocument
            ? 'Las plantillas con visibilidad no personal requieren al menos un validador de plantilla y al menos un validador de documento asignados. Añade los validadores requeridos antes de continuar.'
            : missingTemplate
              ? 'Las plantillas con visibilidad no personal requieren al menos un validador de plantilla asignado. Añade un validador antes de continuar.'
              : 'Las plantillas con visibilidad no personal requieren al menos un validador de documento asignado. Añade un validador de documento antes de continuar.';
        setNoValidatorsModalMessage(msg);
        setShowNoValidatorsModal(true);
        return;
      }
    }

    setSaving(true);
    setErrors({});
    try {
      if (usersDirty) {
        const reviewMode: ReviewMode = validationType === 'ordenada' ? 'sequential' : 'parallel';
        const documentReviewMode: ReviewMode = documentValidationType === 'ordenada' ? 'sequential' : 'parallel';
        await apiUpdateTemplate(template.id, {
          review_mode: reviewMode,
          document_review_mode: documentReviewMode,
          ...(reviewMode === 'sequential' ? { review_stages: validators.length } : {}),
        });
        await syncTemplateValidators(template.id, validators.map((v) => v.userId));
        await syncDocumentReviewers(template.id, documentValidators.map((v) => v.userId));
      }

      setCompletedSteps((prev: Step[]) => Array.from(new Set([...prev, 'users'])) as Step[]);
      setStep('summary');
    } catch (e) {
      // TODO: send to error tracker
      const detail =
        e instanceof ApiHttpError
          ? e.message
          : e instanceof Error
            ? e.message
            : 'Error al guardar los validadores';
      setErrors({ api: detail || 'Error al guardar los validadores' });
    } finally {
      setSaving(false);
    }
  };

  const validateBlocksStep = async (onInvalid?: (remaining: TemplateBlock[]) => void) => {
    if (blocksLoading || blocksCount < 1) {
      setErrors({ blocks: 'Añade al menos un bloque antes de continuar.' });
      return false;
    }

    if (hasInvalidBlocks) {
      setInvalidBlocksModal({ onProceed: onInvalid });
      return false;
    }

    const err = validateBlocksInvariants(wizardBlocks);
    if (err) {
      setBlockInvariantModal(err);
      return false;
    }

    return true;
  };

  const saveBlocks = async () => {
    setSaving(true);
    try {
      await blocksRef.current?.saveIfPending();
    } finally {
      setSaving(false);
    }
  };

  const handleContinue = async () => {
    if (step === 'properties') {
      const ok = await saveProperties();
      if (!ok) return;
    } else if (step === 'blocks') {
      await saveBlocks()
      if (!(await validateBlocksStep())) return;
      setCompletedSteps(prev =>
        Array.from(new Set([...prev, 'blocks'])) as Step[]
      );
      setStep('users');

    }else if (step === 'users') {
      void saveUsers();

    } else if (step === 'summary') {
      navigate(processBackTo);
    }
  };

  const handleGoToStep = async (s: Step) => {
    if (step === 'properties') {
      const ok = await saveProperties();
      if (!ok) return;
    }else if (step === 'blocks') {
      await saveBlocks()
      if (!(await validateBlocksStep())) return;
    }
    if (s === 'users' && completedSteps.includes('blocks')) {
      if (!(await validateBlocksStep())) return;
    }

    setStep(s);
  };

  // ── Render Helpers ─────────────────────────────────────────────────────────

  const stepsData: WizardStepDef<Step>[] = [
    { id: 'properties', label: 'Propiedades', sub: 'Nombre, descripción…' },
    { id: 'blocks', label: 'Bloques', sub: 'Estructura del documento' },
    { id: 'users', label: 'Usuarios', sub: 'Validadores' },
    { id: 'summary', label: 'Resumen', sub: 'Revisión final' },
  ];

  const blocksGateActive = blocksLoading || blocksCount < 1;

  const headerActions = (
    <>
      {step === 'summary' && (
        <Button
          variant="outline"
          size="sm"
          onClick={() => navigate(processBackTo)}
          className="border-odoo-teal text-odoo-teal hover:bg-odoo-teal/10 dark:border-odoo-dark-teal dark:text-odoo-dark-teal dark:hover:bg-odoo-dark-teal/10"
        >
          Guardar y salir
        </Button>
      )}

      {step !== 'summary' && (
        <Button
          variant="primary"
          size="sm"
          loading={saving}
          disabled={step === 'blocks' && blocksGateActive}
          onClick={() => void handleContinue()}
          className="text-xs font-black uppercase tracking-widest px-6 rounded-full shadow-sm"
        >
          Guardar y continuar →
        </Button>
      )}
      {step === 'summary' && !!pendingOwnerTransfer && (
        <Button
          variant="primary"
          size="sm"
          loading={saving}
          onClick={() => void handleTransferOwnership()}
          className="text-xs font-black uppercase tracking-widest px-6 rounded-full shadow-sm"
        >
          Cambiar propiedad
        </Button>
      )}
      {step === 'summary' && !pendingOwnerTransfer && (validators.length > 0 || documentValidators.length > 0) && (
        <Button
          variant="primary"
          size="sm"
          disabled={blocksGateActive}
          onClick={() => openChangelogModal('submit')}
          className="text-xs font-black uppercase tracking-widest px-6 rounded-full shadow-sm"
        >
          Enviar a validar
        </Button>
      )}
      {step === 'summary' && !pendingOwnerTransfer && validators.length === 0 && documentValidators.length === 0 && (
        <Button
          variant="primary"
          size="sm"
          loading={saving}
          disabled={blocksGateActive}
          onClick={() => openChangelogModal('publish')}
          className="text-xs font-black uppercase tracking-widest px-6 rounded-full shadow-sm"
        >
          Publicar plantilla
        </Button>
      )}
    </>
  );

  const banner = (
    <>
      {leaveGuard && (
        <div className="flex items-center gap-4 px-6 py-3 border-b border-warning/30 bg-warning-light/40 animate-in slide-in-from-top-1">
          <span className="flex-1 text-xs font-bold text-warning-dark">
            ⚠️ Tienes cambios sin guardar en este paso. ¿Seguro que quieres salir?
          </span>
          <div className="flex gap-2">
            <Button variant="outlineWarning" size="xs" onClick={() => navigate(processBackTo)}>
              Salir sin guardar
            </Button>
            <Button variant="secondary" size="xs" onClick={() => setLeaveGuard(false)}>
              Cancelar
            </Button>
          </div>
        </div>
      )}
      {step === 'properties' && permissionError && (
        <div className="flex items-center gap-4 px-6 py-3 border-b border-danger-dark/30 bg-danger/10 dark:border-danger/30 animate-in slide-in-from-top-1">
          <span className="flex-1 text-xs font-bold text-danger-dark dark:text-danger">{permissionError}</span>
          <Button
            variant="ghost"
            size="xs"
            onClick={() => setPermissionError(null)}
            aria-label={t('common:actions.close')}
            className="shrink-0 !text-sm leading-none opacity-70 hover:opacity-100"
          >
            ✕
          </Button>
        </div>
      )}
      {errors.blocks && step === 'blocks' && (
        <div className="flex items-center gap-4 px-6 py-3 border-b border-danger-dark/30 bg-danger/10 dark:border-danger/30 animate-in slide-in-from-top-1">
          <span className="flex-1 text-xs font-bold text-danger-dark dark:text-danger">
            ⚠️ {errors.blocks}
          </span>
          <Button
            variant="ghost"
            size="xs"
            onClick={() => setErrors((prev) => {
              const { blocks: _b, ...rest } = prev;
              return rest;
            })}
            aria-label={t('common:actions.close')}
            className="shrink-0 !text-sm leading-none opacity-70 hover:opacity-100"
          >
            ✕
          </Button>
        </div>
      )}
      {errors.api && step !== 'properties' && (
        <div className="flex items-center gap-4 px-6 py-3 border-b border-danger-dark/30 bg-danger/10 dark:border-danger/30 animate-in slide-in-from-top-1">
          <span className="flex-1 text-xs font-bold text-danger-dark dark:text-danger">
            ⚠️ {errors.api}. Inténtalo de nuevo.
          </span>
          <Button
            variant="ghost"
            size="xs"
            onClick={() => setErrors({})}
            aria-label={t('common:actions.close')}
            className="shrink-0 !text-sm leading-none opacity-70 hover:opacity-100"
          >
            ✕
          </Button>
        </div>
      )}
    </>
  );

  return (
    <>
      <WizardShell<Step>
        title={template ? template.name : t('templates:newTitle')}
        subtitle={processSubtitle ?? (template ? t('templates:editTitle') : undefined)}
        onBack={handleBackArrow}
        actions={headerActions}
        steps={stepsData}
        currentStep={step}
        completedSteps={completedSteps}
        onGoToStep={handleGoToStep}
        banner={banner}
      >
        <>
          {step === 'properties' && (
            <FormProvider {...step1Methods}>
              <WizardStep1Properties
                errors={errors}
                templateStatus={template?.status}
                isCreator={!!template?.id && !!profile?.id && profile.id === template.created_by}
                currentAuthorName={template?.author_name ?? null}
              />
            </FormProvider>
          )}
          {step === 'blocks' && template && (
            <WizardStep2Blocks
              ref={blocksRef}
              template={template}
              onBlocksCountChange={setBlocksCount}
              onBlocksLoadingChange={setBlocksLoading}
              onBlocksChange={setWizardBlocks}
              onContinue={() => void handleContinue()}
              onInvalidBlocksChange={setHasInvalidBlocks}
              onCommentAdded={handleCommentAdded}
            />
          )}
          {step === 'users' && (
            <WizardStep3Users
              visibilityLevel={step1Methods.watch('visibility')}
              studyTypeId={step1Methods.watch('studyTypeId') || undefined}
              studyId={step1Methods.watch('studyId') || undefined}
              moduleId={step1Methods.watch('moduleId') || undefined}
              teamId={step1Methods.watch('teamId') || undefined}
              validators={validators}
              onValidatorsChange={(v) => { setValidators(v); setUsersDirty(true); }}
              validationType={validationType}
              onValidationTypeChange={handleTemplateValidationModeChange}
              documentValidators={documentValidators}
              onDocumentValidatorsChange={(v) => { setDocumentValidators(v); setUsersDirty(true); }}
              documentValidationType={documentValidationType}
              onDocumentValidationTypeChange={handleDocumentValidationModeChange}
            />
          )}
          {step === 'summary' && template && (
            <WizardStep4Summary
              template={template}
              validators={validators}
              validationType={validationType}
              documentValidators={documentValidators}
              documentValidationType={documentValidationType}
              onBlocksCountChange={setBlocksCount}
              onBlocksLoadingChange={setBlocksLoading}
              onBlocksChange={setWizardBlocks}
            />
          )}
        </>
      </WizardShell>

      {/* Invalid blocks navigation guard */}
      <ConfirmDialog
        open={!!invalidBlocksModal}
        title={t('templates:modals.unnamedBlocks')}
        description="Hay bloques sin título. Debes completarlos antes de salir, o puedes descartarlos."
        confirmLabel="Descartar bloques sin título"
        variant="danger"
        onConfirm={async () => {
          const cb = invalidBlocksModal?.onProceed;
          const remaining = await blocksRef.current?.discardInvalidBlocks() ?? [];
          setInvalidBlocksModal(null);
          cb?.(remaining);
        }}
        onCancel={() => {
          if (blocker.state === 'blocked') blocker.reset();
          setInvalidBlocksModal(null);
        }}
      />

      {/* Block invariant error modal */}
      <ConfirmDialog
        open={!!blockInvariantModal}
        title={t('templates:modals.invalidStructure')}
        description={blockInvariantModal ?? ''}
        confirmLabel="Entendido"
        variant="danger"
        onConfirm={() => setBlockInvariantModal(null)}
        onCancel={() => setBlockInvariantModal(null)}
      />

      {/* No validators warning modal */}
      <ConfirmDialog
        open={showNoValidatorsModal}
        title={t('templates:modals.validatorRequired')}
        description={noValidatorsModalMessage}
        confirmLabel="Entendido"
        onConfirm={() => setShowNoValidatorsModal(false)}
        onCancel={() => setShowNoValidatorsModal(false)}
      />

      <VersionChangelogModal
        open={showChangelogModal}
        title={changelogModalMode === 'submit' ? t('templates:modals.sendValidation') : t('templates:modals.publish')}
        intro={changelogModalIntro}
        initialValue={template?.submission_changelog}
        confirmLabel={
          saving
            ? changelogModalMode === 'submit'
              ? 'Enviando…'
              : 'Publicando…'
            : changelogModalMode === 'submit'
              ? 'Confirmar y salir →'
              : 'Publicar'
        }
        loading={saving}
        error={changelogModalError}
        onCancel={() => {
          setShowChangelogModal(false);
          setChangelogModalError(null);
        }}
        onConfirm={handleConfirmChangelogSubmit}
      />
    </>
  );
}
