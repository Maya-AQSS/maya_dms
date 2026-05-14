import { useState, useMemo, useRef, useEffect } from 'react';
import { FormProvider, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useNavigate, useBlocker } from 'react-router-dom';
import { WizardShell, type WizardStepDef } from '../../../components/wizard/WizardShell';
import { fetchProcesses } from '../../../api/processes';
import type { Template } from '../../../types/templates';
import type { ReviewMode } from '../../../types/templates';
import type { TemplateBlock } from '../../../types/blocks';
import {
  updateTemplate as apiUpdateTemplate,
  createTemplate as apiCreateTemplate,
  publishTemplate as apiPublishTemplate,
  fetchTemplateVersionSummaries,
  submitTemplateForReview as apiSubmitTemplateForReview,
  syncTemplateValidators,
  syncDocumentReviewers,
} from '../../../api/templates';
import { ApiHttpError, apiFetchJson } from '../../../api/http';
import { Button, ConfirmDialog } from '@maya/shared-ui-react';
import { WizardStep1Properties } from './WizardStep1Properties';
import { WizardStep2Blocks, type WizardStep2BlocksHandle } from './WizardStep2Blocks';
import { WizardStep3Users, type ValidatorEntry } from './WizardStep3Users';
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
  const navigate = useNavigate();
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
  const [invalidBlocksModal, setInvalidBlocksModal] = useState<{ onProceed: (remaining: TemplateBlock[]) => void } | null>(null);
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
    initial?.document_reviewer_users?.map((r) => ({ userId: r.user_id, name: r.user_name ?? '—' })) ?? [],
  );
  const [validationType, setValidationType] = useState<'libre' | 'ordenada'>(
    initial?.review_mode === 'sequential' ? 'ordenada' : 'libre',
  );
  const [documentValidationType, setDocumentValidationType] = useState<'libre' | 'ordenada'>('libre');
  // Dirty flag: only sync reviewers to API if the user actually changed them in Step 3
  const [usersDirty, setUsersDirty] = useState(false);

  // UI state
  const [saving, setSaving] = useState(false);
  const blocksRef = useRef<WizardStep2BlocksHandle>(null);
  const [permissionError, setPermissionError] = useState<string | null>(null);
  const [showValidationModal, setShowValidationModal] = useState(false);
  const [showPublishModal, setShowPublishModal] = useState(false);
  const [publishChangelog, setPublishChangelog] = useState('');
  const [publishModalError, setPublishModalError] = useState<string | null>(null);
  const [publishedVersionCount, setPublishedVersionCount] = useState(0);
  const [comments, setComments] = useState<any[]>([]);
  const [blocksCount, setBlocksCount] = useState(0);
  const [blocksLoading, setBlocksLoading] = useState(true);
  const [wizardBlocks, setWizardBlocks] = useState<TemplateBlock[]>([]);
  const [processSubtitle, setProcessSubtitle] = useState<string | null>(null);

  useEffect(() => {
    if (initial?.id) {
      void apiFetchJson<{ data: any[] }>(`templates/${initial.id}/comments`)
        .then(res => setComments(res.data))
        .catch(console.error);
    }
  }, [initial?.id]);

  useEffect(() => {
    if (!template?.id) {
      setPublishedVersionCount(0);
      return;
    }
    let cancelled = false;
    void fetchTemplateVersionSummaries(template.id)
      .then((rows) => {
        if (!cancelled) setPublishedVersionCount(rows.length);
      })
      .catch(() => {
        if (!cancelled) setPublishedVersionCount(0);
      });
    return () => {
      cancelled = true;
    };
  }, [template?.id]);

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

  useEffect(() => {
    const effectiveProcessId = processId ?? template?.process_id ?? initial?.process_id ?? null;
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
  }, [initial?.process_id, processId, template?.process_id]);

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

  const handleResolveComment = async (commentId: string) => {
    try {
      await apiResolveComment(commentId);
      setComments(prev => prev.map(c => c.id === commentId ? { ...c, resolved: true } : c));
    } catch (e) {
      console.error('Error resolving comment:', e);
    }
  };

  const handleCommentAdded = (comment: any) => {
    setComments(prev => [...prev, comment]);
  };

  // Dirty check
  const isDirty = step === 'properties' && step1Methods.formState.isDirty;

  const validateBlocksInvariants = (blocksList: TemplateBlock[]): string | null => {
    const hasEditable = blocksList.some(b => b.block_state === 'editable' || b.block_state === 'modifiable');
    if (!hasEditable) return 'La plantilla debe tener al menos un bloque editable o modificable.';
    const isEmpty = (content: unknown) =>
      content === null || (Array.isArray(content) && content.length === 0);
    const hasEmptyModifiable = blocksList.some(b =>
      b.block_state === 'modifiable' && isEmpty(b.default_content)
    );
    if (hasEmptyModifiable) return 'Los bloques modificables no pueden estar vacíos: el contenido predeterminado es obligatorio.';
    const hasEmptyLocked = blocksList.some(b =>
      b.block_state === 'locked' && isEmpty(b.default_content)
    );
    if (hasEmptyLocked) return 'Los bloques bloqueados no pueden estar vacíos.';
    return null;
  };

  const handleBackArrow = () => {
    if (isDirty) {
      setLeaveGuard(true);
      return;
    }
    if (step === 'blocks' && hasInvalidBlocks) {
      setInvalidBlocksModal({ onProceed: (_remaining) => setStep('properties') });
      return;
    }
    if (step === 'properties') {
      navigate(processBackTo);
      return;
    }
    const order: Step[] = ['properties', 'blocks', 'users', 'summary'];
    const idx = order.indexOf(step);
    if (idx > 0) setStep(order[idx - 1]!);
    else navigate(processBackTo);
  };

  const saveProperties = step1Methods.handleSubmit(async (values) => {
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
  });
  const handlePublish = async (changelog?: string | null) => {
    if (!template?.id) return;
    setSaving(true);
    try {
      await apiPublishTemplate(template.id, changelog);
      setShowPublishModal(false);
      setPublishModalError(null);
      setPublishChangelog('');
      navigate(processBackTo);
    } catch (e) {
      const message = e instanceof Error ? e.message : 'Error al publicar la plantilla';
      if (showPublishModal) {
        setPublishModalError(message);
      } else {
        setErrors({ api: message });
      }
    } finally {
      setSaving(false);
    }
  };

  const requiresPublishChangelog = publishedVersionCount > 0;

  const handlePublishClick = () => {
    const blockInvariantErr = validateBlocksInvariants(wizardBlocks);
    if (blockInvariantErr) {
      setBlockInvariantModal(blockInvariantErr);
      return;
    }
    if (requiresPublishChangelog) {
      setPublishModalError(null);
      setShowPublishModal(true);
      return;
    }
    void handlePublish(null);
  };

  const handleSubmitForReviewClick = () => {
    const blockInvariantErr = validateBlocksInvariants(wizardBlocks);
    if (blockInvariantErr) {
      setBlockInvariantModal(blockInvariantErr);
      return;
    }
    setShowValidationModal(true);
  };

  const handleConfirmPublish = () => {
    if (requiresPublishChangelog && publishChangelog.trim() === '') {
      setPublishModalError('El changelog es obligatorio a partir de la segunda versión.');
      return;
    }
    void handlePublish(publishChangelog.trim());
  };

  const handleSubmitForReview = async () => {
    if (!template?.id) return;
    setSaving(true);
    setErrors({});
    try {
      const res = await apiSubmitTemplateForReview(template.id);
      setTemplate(res.data);
      setShowValidationModal(false);
      navigate(processBackTo);
    } catch (e) {
      setErrors({ api: e instanceof Error ? e.message : 'Error al enviar la plantilla a validación' });
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

    setSaving(true);
    setErrors({});
    try {
      if (usersDirty) {
        const reviewMode: ReviewMode = validationType === 'ordenada' ? 'sequential' : 'parallel';
        // In sequential mode each reviewer occupies exactly one stage, so review_stages
        // must equal the reviewer count before syncing — otherwise the backend rejects
        // the sync with a 422 when the count exceeds the previous review_stages value.
        await apiUpdateTemplate(template.id, {
          review_mode: reviewMode,
          ...(reviewMode === 'sequential' ? { review_stages: validators.length } : {}),
        });
        await syncTemplateValidators(template.id, validators.map((v) => v.userId));
        await syncDocumentReviewers(template.id, documentValidators.map((v) => v.userId));
      }

      setCompletedSteps((prev: Step[]) => Array.from(new Set([...prev, 'users'])) as Step[]);
      setStep('summary');
    } catch (e) {
      console.error('[saveUsers]', e);
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

  const handleContinue = async () => {
    if (step === 'properties') {
      void saveProperties();
    } else if (step === 'blocks') {
      if (blocksLoading) return;
      if (blocksCount < 1) {
        setErrors({ blocks: 'Añade al menos un bloque antes de continuar.' });
        return;
      }
      if (hasInvalidBlocks) {
        setInvalidBlocksModal({
          onProceed: (remaining) => {
            if (remaining.length === 0) {
              setErrors({ blocks: 'Añade al menos un bloque antes de continuar.' });
              return;
            }
            const err = validateBlocksInvariants(remaining);
            if (err) { setBlockInvariantModal(err); return; }
            setSaving(true);
            void blocksRef.current?.saveIfPending().then(() => {
              setCompletedSteps((prev) => Array.from(new Set([...prev, 'blocks'])) as Step[]);
              setStep('users');
            }).finally(() => setSaving(false));
          },
        });
        return;
      }
      const blockInvariantErr = validateBlocksInvariants(wizardBlocks);
      if (blockInvariantErr) {
        setBlockInvariantModal(blockInvariantErr);
        return;
      }
      setErrors((prev) => ({ ...prev, blocks: undefined }));
      setSaving(true);
      try {
        await blocksRef.current?.saveIfPending();
        setCompletedSteps((prev: Step[]) => Array.from(new Set([...prev, 'blocks'])) as Step[]);
        setStep('users');
      } catch {
        // block save failed; error already shown in the panel
      } finally {
        setSaving(false);
      }
    } else if (step === 'users') {
      void saveUsers();
    } else if (step === 'summary') {
      navigate(processBackTo);
    }
  };

  const handleGoToStep = (s: Step) => {
    if (s === 'properties') {
      if (step === 'blocks' && hasInvalidBlocks) {
        setInvalidBlocksModal({ onProceed: (_remaining) => setStep('properties') });
        return;
      }
      setStep(s);
      return;
    }
    if (s === 'blocks' && completedSteps.includes('properties')) {
      setStep(s);
      return;
    }
    if (s === 'users' && completedSteps.includes('blocks')) {
      if (blocksLoading || blocksCount < 1) {
        setErrors({ blocks: 'Añade al menos un bloque antes de continuar.' });
        return;
      }
      if (hasInvalidBlocks) {
        setInvalidBlocksModal({
          onProceed: (remaining) => {
            if (remaining.length === 0) {
              setErrors({ blocks: 'Añade al menos un bloque antes de continuar.' });
              return;
            }
            const err = validateBlocksInvariants(remaining);
            if (err) { setBlockInvariantModal(err); return; }
            setStep('users');
          },
        });
        return;
      }
      const blockInvariantErr = validateBlocksInvariants(wizardBlocks);
      if (blockInvariantErr) {
        setBlockInvariantModal(blockInvariantErr);
        return;
      }
      setStep(s);
      return;
    }
    if (s === 'summary' && completedSteps.includes('users')) {
      if (blocksLoading || blocksCount < 1) {
        setErrors({ api: 'Añade al menos un bloque antes de publicar o enviar a validación.' });
        return;
      }
      setStep(s);
    }
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
      {step === 'summary' && (validators.length > 0 || documentValidators.length > 0) && (
        <Button
          variant="primary"
          size="sm"
          disabled={blocksGateActive}
          onClick={handleSubmitForReviewClick}
          className="text-xs font-black uppercase tracking-widest px-6 rounded-full shadow-sm"
        >
          Enviar a validar
        </Button>
      )}
      {step === 'summary' && validators.length === 0 && documentValidators.length === 0 && (
        <Button
          variant="primary"
          size="sm"
          loading={saving}
          disabled={blocksGateActive}
          onClick={handlePublishClick}
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
            aria-label="Cerrar"
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
            aria-label="Cerrar"
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
            aria-label="Cerrar"
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
        title={template ? template.name : 'Nueva plantilla'}
        subtitle={processSubtitle ?? (template ? 'Editar plantilla' : undefined)}
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
              <WizardStep1Properties errors={errors} templateStatus={template?.status} />
            </FormProvider>
          )}
          {step === 'blocks' && template && (
            <WizardStep2Blocks
              ref={blocksRef}
              template={template}
              reviewComments={comments}
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
              validators={validators}
              onValidatorsChange={(v) => { setValidators(v); setUsersDirty(true); }}
              validationType={validationType}
              onValidationTypeChange={(t) => {
                setValidationType(t);
                setUsersDirty(true);
                if (template?.id) {
                  const reviewMode: ReviewMode = t === 'ordenada' ? 'sequential' : 'parallel';
                  void apiUpdateTemplate(template.id, { review_mode: reviewMode }).catch(() => {/* non-blocking */ });
                }
              }}
              documentValidators={documentValidators}
              onDocumentValidatorsChange={(v) => { setDocumentValidators(v); setUsersDirty(true); }}
              documentValidationType={documentValidationType}
              onDocumentValidationTypeChange={(t) => { setDocumentValidationType(t); setUsersDirty(true); }}
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
        title="Bloques sin título"
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
        title="Estructura de bloques inválida"
        description={blockInvariantModal ?? ''}
        confirmLabel="Entendido"
        variant="danger"
        onConfirm={() => setBlockInvariantModal(null)}
        onCancel={() => setBlockInvariantModal(null)}
      />

      {/* Validation modal */}
      <ConfirmDialog
        open={showValidationModal}
        title="Enviar a validación"
        icon="✉️"
        description={
          <div className="space-y-4">
            <p className="text-xs text-text-muted">Se notificará a los validadores asignados. Una vez enviada, la plantilla no podrá editarse hasta ser aprobada o rechazada.</p>
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
                        {v.role && <p className="text-xs text-text-secondary uppercase tracking-tight">{v.role}</p>}
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
                        {v.role && <p className="text-xs text-text-secondary uppercase tracking-tight">{v.role}</p>}
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        }
        confirmLabel={saving ? 'Enviando…' : 'Confirmar y salir →'}
        loading={saving}
        onCancel={() => setShowValidationModal(false)}
        onConfirm={handleSubmitForReview}
      />
      <ConfirmDialog
        open={showPublishModal}
        title="Publicar plantilla"
        description={
          <div className="space-y-2">
            <p className="text-xs text-text-muted">
              Añade un changelog para esta publicación.
            </p>
            <textarea
              value={publishChangelog}
              onChange={(e) => {
                setPublishChangelog(e.target.value);
                if (publishModalError) setPublishModalError(null);
              }}
              placeholder="Describe los cambios de esta versión..."
              className="w-full min-h-24 rounded-md border border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-bg px-3 py-2 text-sm"
            />
          </div>
        }
        confirmLabel={saving ? 'Publicando…' : 'Publicar'}
        loading={saving}
        error={publishModalError}
        onCancel={() => {
          setShowPublishModal(false);
          setPublishModalError(null);
        }}
        onConfirm={handleConfirmPublish}
      />
    </>
  );
}
