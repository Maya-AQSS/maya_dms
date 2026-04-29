import { useState, useMemo, useRef, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { PageTitle } from '@maya/shared-ui-react';
import type { Template, TemplateVisibilityLevel } from '../../../types/templates';
import type { ReviewMode } from '../../../types/templates';
import {
  updateTemplate as apiUpdateTemplate,
  createTemplate as apiCreateTemplate,
  publishTemplate as apiPublishTemplate,
  submitTemplateForReview as apiSubmitTemplateForReview,
  syncTemplateValidators,
  syncDocumentReviewers,
  resolveComment as apiResolveComment,
} from '../../../api/templates';
import { ApiHttpError, apiFetchJson } from '../../../api/http';
import { Button, ConfirmDialog } from '../../../ui';
import { WizardStep1Properties } from './WizardStep1Properties';
import { WizardStep2Blocks, type WizardStep2BlocksHandle } from './WizardStep2Blocks';
import { WizardStep3Users, type ValidatorEntry } from './WizardStep3Users';
import { WizardStep4Summary } from './WizardStep4Summary';

type Step = 'properties' | 'blocks' | 'users' | 'summary';

type Props = {
  template?: Template | null;
  initialTemplate?: Template | null;
};

export function TemplateWizard({ template: templateProp, initialTemplate }: Props) {
  const navigate = useNavigate();
  const initial = templateProp || initialTemplate;

  // Step state
  const [step, setStep] = useState<Step>('properties');
  const [completedSteps, setCompletedSteps] = useState<Step[]>(
    initial?.id ? (['properties', 'blocks', 'users'] as Step[]) : [],
  );
  const [leaveGuard, setLeaveGuard] = useState(false);

  // Template state (synchronized with API)
  const [template, setTemplate] = useState<Template | null>(initial || null);

  // Step 1: Properties state
  const [name, setName] = useState(initial?.name || '');
  const [description, setDescription] = useState(initial?.description || '');
  const [visibility, setVisibility] = useState<TemplateVisibilityLevel>(initial?.visibility_level || 'personal');
  const [deliveryDeadline, setDeliveryDeadline] = useState(initial?.delivery_deadline ? initial.delivery_deadline.split('T')[0] : '');
  const [studyTypeId, setStudyTypeId] = useState(initial?.study_type_id || '');
  const [studyId, setStudyId] = useState(initial?.study_id || '');
  const [moduleId, setModuleId] = useState(initial?.module_id || '');
  const [teamId, setTeamId] = useState(initial?.team_id || '');
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Step 3: Users state
  const [validators, setValidators] = useState<ValidatorEntry[]>([]);
  const [documentValidators, setDocumentValidators] = useState<ValidatorEntry[]>([]);
  const [validationType, setValidationType] = useState<'libre' | 'ordenada'>(
    initial?.review_mode === 'sequential' ? 'ordenada' : 'libre'
  );
  const [documentValidationType, setDocumentValidationType] = useState<'libre' | 'ordenada'>('libre');

  // UI state
  const [saving, setSaving] = useState(false);
  const blocksRef = useRef<WizardStep2BlocksHandle>(null);
  const [permissionError, setPermissionError] = useState<string | null>(null);
  const [showValidationModal, setShowValidationModal] = useState(false);
  const [comments, setComments] = useState<any[]>([]);

  useEffect(() => {
    if (initial?.id && initial?.has_review_comments) {
      void apiFetchJson<{ data: any[] }>(`templates/${initial.id}/comments`)
        .then(res => setComments(res.data))
        .catch(console.error);
    }
  }, [initial?.id, initial?.has_review_comments]);

  const handleResolveComment = async (commentId: string) => {
    try {
      await apiResolveComment(commentId);
      setComments(prev => prev.map(c => c.id === commentId ? { ...c, resolved: true } : c));
    } catch (e) {
      console.error('Error resolving comment:', e);
    }
  };

  // Dirty check
  const isDirty = useMemo(() => {
    if (step === 'properties') {
      return name !== (template?.name || '') || 
             description !== (template?.description || '') ||
             visibility !== (template?.visibility_level || 'personal');
    }
    return false;
  }, [step, name, description, visibility, template]);

  const handleBackArrow = () => {
    if (isDirty) {
      setLeaveGuard(true);
      return;
    }
    if (step === 'properties') {
      navigate('/procesos');
      return;
    }
    const order: Step[] = ['properties', 'blocks', 'users', 'summary'];
    const idx = order.indexOf(step);
    if (idx > 0) setStep(order[idx - 1]!);
    else navigate('/procesos');
  };

  const validateStep1 = () => {
    const newErrors: Record<string, string> = {};
    if (!name.trim()) newErrors.name = 'El nombre es obligatorio.';

    if (visibility === 'study_type') {
      if (!studyTypeId) newErrors.studyTypeId = 'Este campo es obligatorio';
    } else if (visibility === 'study') {
      if (!studyTypeId) newErrors.studyTypeId = 'Este campo es obligatorio';
      if (!studyId) newErrors.studyId = 'Este campo es obligatorio';
    } else if (visibility === 'module') {
      if (!studyTypeId) newErrors.studyTypeId = 'Este campo es obligatorio';
      if (!studyId) newErrors.studyId = 'Este campo es obligatorio';
      if (!moduleId) newErrors.moduleId = 'Este campo es obligatorio';
    } else if (visibility === 'team') {
      if (!teamId) newErrors.teamId = 'Este campo es obligatorio';
    }

    if (!deliveryDeadline) {
      newErrors.deliveryDeadline = 'El plazo de entrega es obligatorio.';
    } else {
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const selected = new Date(deliveryDeadline);
      if (selected < today) {
        newErrors.deliveryDeadline = 'La fecha no puede ser anterior a hoy.';
      }
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const saveProperties = async () => {
    if (!validateStep1()) return;
    setSaving(true);
    setPermissionError(null);
    try {
      const isUpdate = !!template?.id;
      const visibilityChanged = !isUpdate || visibility !== template.visibility_level;

      const payload = {
        name: name.trim(),
        description: description.trim() || null,
        ...(visibilityChanged ? { visibility_level: visibility } : {}),
        delivery_deadline: deliveryDeadline ? `${deliveryDeadline}T00:00:00Z` : null,
        study_type_id: studyTypeId || null,
        study_id: studyId || null,
        module_id: moduleId || null,
        team_id: teamId || null,
      };

      let res;
      if (isUpdate) {
        res = await apiUpdateTemplate(template.id, payload);
      } else {
        res = await apiCreateTemplate({ ...payload, visibility_level: visibility });
      }
      setTemplate(res.data);
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
  };
  const handlePublish = async () => {
    if (!template?.id) return;
    setSaving(true);
    try {
      await apiPublishTemplate(template.id);
      navigate('/procesos');
    } catch (e) {
      setErrors({ api: e instanceof Error ? e.message : 'Error al publicar la plantilla' });
    } finally {
      setSaving(false);
    }
  };

  const handleSubmitForReview = async () => {
    if (!template?.id) return;
    setSaving(true);
    setErrors({});
    try {
      const res = await apiSubmitTemplateForReview(template.id);
      setTemplate(res.data);
      setShowValidationModal(false);
      navigate('/procesos');
    } catch (e) {
      setErrors({ api: e instanceof Error ? e.message : 'Error al enviar la plantilla a validación' });
    } finally {
      setSaving(false);
    }
  };
  const saveUsers = async () => {
    if (!template?.id) return;

    const userIds = validators.map((v: ValidatorEntry) => v.userId);

    setSaving(true);
    setErrors({});
    try {
      // 1. Guardar modo de revisión (libre/ordenada -> parallel/sequential)
      const reviewMode: ReviewMode = validationType === 'ordenada' ? 'sequential' : 'parallel';
      await apiUpdateTemplate(template.id, { review_mode: reviewMode });

      // 2. Sincronizar validadores de plantilla y de documento
      const docUserIds = documentValidators.map((v: ValidatorEntry) => v.userId);
      await syncTemplateValidators(template.id, userIds);
      await syncDocumentReviewers(template.id, docUserIds);

      setCompletedSteps((prev: Step[]) => Array.from(new Set([...prev, 'users'])) as Step[]);
      setStep('summary');
    } catch (e) {
      console.error('[saveUsers]', e);
      setErrors({ api: 'Error al guardar los validadores' });
    } finally {
      setSaving(false);
    }
  };

  const handleContinue = async () => {
    if (step === 'properties') {
      void saveProperties();
    } else if (step === 'blocks') {
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
      navigate('/procesos');
    }
  };

  const handleGoToStep = (s: Step) => {
    if (s === 'properties') setStep(s);
    else if (s === 'blocks' && completedSteps.includes('properties')) setStep(s);
    else if (s === 'users' && completedSteps.includes('blocks')) setStep(s);
    else if (s === 'summary' && completedSteps.includes('users')) setStep(s);
  };

  // ── Render Helpers ─────────────────────────────────────────────────────────

  const renderStepper = () => {
    const stepsData: { id: Step; label: string; sub: string }[] = [
      { id: 'properties', label: 'Propiedades', sub: 'Nombre, descripción…' },
      { id: 'blocks', label: 'Bloques', sub: 'Estructura del documento' },
      { id: 'users', label: 'Usuarios', sub: 'Validadores' },
      { id: 'summary', label: 'Resumen', sub: 'Revisión final' },
    ];

    return (
      <div className="flex items-center px-6 py-4 bg-white dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border shrink-0">
        {stepsData.map((s, i) => {
          const isActive = step === s.id;
          const isDone = completedSteps.includes(s.id);
          const isPending = !isActive && !isDone;

          const circleCls = isActive
            ? 'bg-odoo-purple text-text-inverse'
            : isDone
              ? 'bg-success text-text-inverse'
              : 'border border-ui-border text-text-muted';

          const labelCls = isActive
            ? 'text-odoo-purple'
            : isDone
              ? 'text-success'
              : 'text-text-muted';

          return (
            <div key={s.id} className="flex flex-1 items-center last:flex-none">
              <button
                type="button"
                onClick={() => handleGoToStep(s.id)}
                className={`flex items-center gap-3 focus:outline-none transition-all group ${isPending ? 'opacity-50 cursor-default' : 'cursor-pointer hover:scale-105'}`}
                disabled={isPending}
              >
                <span className={`flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold shrink-0 transition-colors shadow-sm ${circleCls}`}>
                  {isDone && !isActive ? '✓' : i + 1}
                </span>
                <span className="text-left hidden lg:block">
                  <span className={`block text-xs font-black uppercase tracking-widest ${labelCls}`}>
                    {s.label}
                  </span>
                  <span className="block text-xs text-text-muted">
                    {s.sub}
                  </span>
                </span>
              </button>
              {i < stepsData.length - 1 && (
                <div className={`flex-1 h-0.5 mx-4 rounded-full ${completedSteps.includes(s.id) ? 'bg-success' : 'bg-ui-border'}`} />
              )}
            </div>
          );
        })}
      </div>
    );
  };

  return (
    <div className="flex flex-col h-[calc(100vh-4rem)] bg-transparent">
      {/* Top bar */}
      <div className="shrink-0">
        <PageTitle
          title={template ? template.name : 'Nueva plantilla'}
          subtitle={template ? 'Editar plantilla' : undefined}
          onBack={handleBackArrow}
          backLabel="Volver"
          className="!mb-2"
          actions={
            <>
              {step === 'summary' && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => navigate('/procesos')}
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
                  onClick={() => void handleContinue()}
                  className="text-xs font-black uppercase tracking-widest px-6 rounded-full shadow-sm"
                >
                  Guardar y continuar →
                </Button>
              )}
              {step === 'summary' && validators.length > 0 && (
                <Button
                  variant="primary"
                  size="sm"
                  onClick={() => setShowValidationModal(true)}
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
                  onClick={() => void handlePublish()}
                  className="text-xs font-black uppercase tracking-widest px-6 rounded-full shadow-sm"
                >
                  Publicar plantilla
                </Button>
              )}
            </>
          }
        />
      </div>

      {/* Leave guard confirmation */}
      {leaveGuard && (
        <div className="shrink-0 flex items-center gap-4 px-6 py-3 border-b border-warning/30 bg-warning-light/40 animate-in slide-in-from-top-1">
          <span className="flex-1 text-xs font-bold text-warning-dark">
            ⚠️ Tienes cambios sin guardar en este paso. ¿Seguro que quieres salir?
          </span>
          <div className="flex gap-2">
            <Button variant="outlineWarning" size="xs" onClick={() => navigate('/procesos')}>
              Salir sin guardar
            </Button>
            <Button variant="secondary" size="xs" onClick={() => setLeaveGuard(false)}>
              Cancelar
            </Button>
          </div>
        </div>
      )}


      {/* Permission / API error banner — step 1 */}
      {step === 'properties' && permissionError && (
        <div className="shrink-0 flex items-center gap-4 px-6 py-3 border-b border-danger-dark/30 bg-danger/10 dark:bg-danger/10 dark:border-danger/30 animate-in slide-in-from-top-1">
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

      {/* API error banner — steps 2, 3, 4 (step 1 shows errors.api inline) */}
      {errors.api && step !== 'properties' && (
        <div className="shrink-0 flex items-center gap-4 px-6 py-3 border-b border-danger-dark/30 bg-danger/10 dark:bg-danger/10 dark:border-danger/30 animate-in slide-in-from-top-1">
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

      {/* Stepper */}
      {renderStepper()}

      {/* Body — only this element scrolls */}
      <div className="flex-1 overflow-hidden flex flex-col min-h-0">
        {step === 'properties' && (
          <WizardStep1Properties
            name={name} setName={setName}
            description={description} setDescription={setDescription}
            visibility={visibility} setVisibility={(v) => { setVisibility(v); setPermissionError(null); }}
            deliveryDeadline={deliveryDeadline} setDeliveryDeadline={setDeliveryDeadline}
            studyTypeId={studyTypeId} setStudyTypeId={setStudyTypeId}
            studyId={studyId} setStudyId={setStudyId}
            moduleId={moduleId} setModuleId={setModuleId}
            teamId={teamId} setTeamId={setTeamId}
            errors={errors}
            templateStatus={template?.status}
          />
        )}
        {step === 'blocks' && template && (
          <WizardStep2Blocks
            ref={blocksRef}
            template={template}
            reviewComments={comments}
            onResolveComment={handleResolveComment}
          />
        )}
        {step === 'users' && (
          <WizardStep3Users
            templateCreatedBy={template?.created_by ?? null}
            validators={validators}
            onValidatorsChange={setValidators}
            validationType={validationType}
            onValidationTypeChange={setValidationType}
            documentValidators={documentValidators}
            onDocumentValidatorsChange={setDocumentValidators}
            documentValidationType={documentValidationType}
            onDocumentValidationTypeChange={setDocumentValidationType}
          />
        )}
        {step === 'summary' && template && (
          <WizardStep4Summary
            template={template}
            validators={validators}
            validationType={validationType}
            documentValidators={documentValidators}
            documentValidationType={documentValidationType}
          />
        )}
      </div>

      {/* Validation modal */}
      <ConfirmDialog
        open={showValidationModal}
        title="Enviar a validación"
        icon="✉️"
        description={
          <div className="space-y-4">
            <p className="text-xs text-text-muted">Se notificará a los validadores asignados.</p>
            <div className="space-y-2">
              <p className="text-xs font-bold uppercase tracking-widest text-text-secondary mb-2">
                Validadores ({validators.length})
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
                    <span className="shrink-0 w-8 h-8 rounded-full bg-odoo-purple/10 text-odoo-purple text-xs font-black border border-odoo-purple/20 flex items-center justify-center">
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
          </div>
        }
        confirmLabel={saving ? 'Enviando…' : 'Confirmar y salir →'}
        loading={saving}
        onCancel={() => setShowValidationModal(false)}
        onConfirm={handleSubmitForReview}
      />

    </div>
  );
}
