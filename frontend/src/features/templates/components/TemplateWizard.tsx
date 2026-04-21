import { useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import type { Template, TemplateVisibilityLevel } from '../../../types/templates';
import {
  updateTemplate as apiUpdateTemplate,
  createTemplate as apiCreateTemplate,
  publishTemplate as apiPublishTemplate,
  syncTemplateValidators,
} from '../../../api/templates';
import { ApiHttpError } from '../../../api/http';
import { Button } from '../../../ui';
import { WizardStep1Properties } from './WizardStep1Properties';
import { WizardStep2Blocks } from './WizardStep2Blocks';
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

  // Step 2: Blocks state


  // Step 3: Users state
  const [validators, setValidators] = useState<ValidatorEntry[]>([]);
  const [documentValidators, setDocumentValidators] = useState<ValidatorEntry[]>([]);
  const [validationType, setValidationType] = useState<'libre' | 'ordenada'>(
    initial?.review_mode === 'sequential' ? 'ordenada' : 'libre',
  );
  const [documentValidationType, setDocumentValidationType] = useState<'libre' | 'ordenada'>('libre');

  // UI state
  const [saving, setSaving] = useState(false);
  const [permissionError, setPermissionError] = useState<string | null>(null);
  const [showValidationModal, setShowValidationModal] = useState(false);

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
    navigate('/templates');
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
      navigate('/templates');
    } catch (e) {
      setErrors({ api: e instanceof Error ? e.message : 'Error al publicar la plantilla' });
    } finally {
      setSaving(false);
    }
  };
  const saveUsers = async () => {
    if (!template?.id) return;
    setSaving(true);
    setErrors({});
    try {
      // 1. Guardar modo de revisión (libre/ordenada -> parallel/sequential)
      const reviewMode = validationType === 'ordenada' ? 'sequential' : 'parallel';
      await apiUpdateTemplate(template.id, { review_mode: reviewMode as any });

      // 2. Sincronizar validadores
      const userIds = validators.map((v: ValidatorEntry) => v.userId);
      await syncTemplateValidators(template.id, userIds);

      setCompletedSteps((prev: Step[]) => Array.from(new Set([...prev, 'users'])) as Step[]);
      setStep('summary');
    } catch (e) {
      setErrors({ api: e instanceof Error ? e.message : 'Error al guardar los validadores' });
    } finally {
      setSaving(false);
    }
  };

  const handleContinue = () => {
    if (step === 'properties') {
      void saveProperties();
    } else if (step === 'blocks') {
      setCompletedSteps((prev: Step[]) => Array.from(new Set([...prev, 'blocks'])) as Step[]);
      setStep('users');
    } else if (step === 'users') {
      void saveUsers();
    } else if (step === 'summary') {
      navigate('/templates');
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
            ? 'bg-odoo-purple text-white'
            : isDone
              ? 'bg-success text-white'
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
                  <span className={`block text-[10px] font-black uppercase tracking-widest ${labelCls}`}>
                    {s.label}
                  </span>
                  <span className="block text-[10px] text-text-muted">
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
    <div className="flex flex-col h-full bg-ui-body dark:bg-ui-dark-bg">
      {/* Top bar */}
      <div className="shrink-0 flex items-center justify-between gap-3 px-4 py-3 bg-white dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border shadow-sm z-10">
        <div className="flex items-center gap-3 min-w-0">
          <button
            type="button"
            onClick={handleBackArrow}
            className="w-9 h-9 rounded-full text-text-secondary hover:bg-ui-body dark:hover:bg-ui-dark-bg transition-all flex items-center justify-center border border-transparent hover:border-ui-border active:scale-95 shrink-0"
            aria-label="Volver"
          >
            ←
          </button>
          <span className="text-sm text-text-secondary truncate">
            Plantillas /{' '}
            <span className="font-bold text-text-primary dark:text-text-dark-primary">
              {template ? `Editando «${template.name}»` : 'Nueva plantilla'}
            </span>
          </span>
        </div>

        {/* Topbar actions */}
        <div className="flex items-center gap-2 shrink-0">
          {step === 'summary' && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => navigate('/templates')}
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
              onClick={handleContinue}
              className="text-[10px] font-black uppercase tracking-widest px-6 rounded-full shadow-sm"
            >
              Guardar y continuar →
            </Button>
          )}
          {step === 'summary' && validators.length > 0 && (
            <Button
              variant="primary"
              size="sm"
              onClick={() => setShowValidationModal(true)}
              className="text-[10px] font-black uppercase tracking-widest px-6 rounded-full shadow-sm"
            >
              Enviar a validar
            </Button>
          )}
          {step === 'summary' && validators.length === 0 && (
            <Button
              variant="primary"
              size="sm"
              loading={saving}
              onClick={() => void handlePublish()}
              className="text-[10px] font-black uppercase tracking-widest px-6 rounded-full shadow-sm"
            >
              Enviar a validar
            </Button>
          )}
        </div>
      </div>

      {/* Leave guard confirmation */}
      {leaveGuard && (
        <div className="shrink-0 flex items-center gap-4 px-6 py-3 border-b border-warning/30 bg-warning-light/40 animate-in slide-in-from-top-1">
          <span className="flex-1 text-xs font-bold text-warning-dark">
            ⚠️ Tienes cambios sin guardar en este paso. ¿Seguro que quieres salir?
          </span>
          <div className="flex gap-2">
            <button
               type="button"
              className="bg-warning-dark text-white px-4 py-1.5 rounded font-bold text-[10px] uppercase tracking-wider shadow-sm active:scale-95 transition-transform"
              onClick={() => navigate('/templates')}
            >
              Salir sin guardar
            </button>
            <button
              type="button"
              className="bg-white border border-ui-border px-4 py-1.5 rounded font-bold text-[10px] uppercase tracking-wider text-text-secondary active:scale-95 transition-transform"
              onClick={() => setLeaveGuard(false)}
            >
              Cancelar
            </button>
          </div>
        </div>
      )}

      {/* Permission / API error banner */}
      {step === 'properties' && permissionError && (
        <div className="shrink-0 flex items-center gap-4 px-6 py-3 border-b border-danger-dark/30 bg-danger/10 dark:bg-danger/10 dark:border-danger/30 animate-in slide-in-from-top-1">
          <span className="flex-1 text-xs font-bold text-danger-dark dark:text-danger">{permissionError}</span>
          <button
            type="button"
            onClick={() => setPermissionError(null)}
            className="shrink-0 text-danger-dark dark:text-danger font-bold text-sm leading-none opacity-70 hover:opacity-100 transition-opacity"
            aria-label="Cerrar"
          >
            ✕
          </button>
        </div>
      )}

      {/* Stepper */}
      {renderStepper()}

      {/* Body — only this element scrolls */}
      <div className="flex-1 overflow-hidden flex flex-col min-h-0 bg-ui-body/30 dark:bg-ui-dark-bg">
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
            template={template}
          />
        )}
        {step === 'users' && (
          <WizardStep3Users
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
          />
        )}
      </div>

      {/* Validation modal */}
      {showValidationModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 animate-in fade-in">
          <div className="bg-white dark:bg-ui-dark-card rounded-xl shadow-xl border border-ui-border dark:border-ui-dark-border w-full max-w-md mx-4 animate-in zoom-in-95">
            <div className="px-6 py-5 border-b border-ui-border dark:border-ui-dark-border flex items-center gap-3">
              <span className="text-2xl">✉️</span>
              <div>
                <p className="text-sm font-bold text-text-primary dark:text-text-dark-primary">Enviar a validación</p>
                <p className="text-xs text-text-muted">Se notificará a los validadores asignados.</p>
              </div>
            </div>
            <div className="px-6 py-4 space-y-2">
              <p className="text-[10px] font-bold uppercase tracking-widest text-text-secondary mb-2">
                Validadores ({validators.length})
              </p>
              {validators.map((v, i) => {
                const initials = v.name.split(' ').filter(Boolean).slice(0, 2).map((w) => w[0]?.toUpperCase() ?? '').join('');
                return (
                  <div key={v.userId} className="flex items-center gap-2.5">
                    {validationType === 'ordenada' && (
                      <span className="shrink-0 w-5 h-5 rounded-full bg-odoo-purple text-white text-[10px] font-bold flex items-center justify-center">
                        {i + 1}
                      </span>
                    )}
                    <span className="shrink-0 w-8 h-8 rounded-full bg-odoo-purple/10 text-odoo-purple text-[10px] font-black border border-odoo-purple/20 flex items-center justify-center">
                      {initials}
                    </span>
                    <div className="min-w-0">
                      <p className="text-xs font-bold text-text-primary dark:text-text-dark-primary truncate">{v.name}</p>
                      {v.role && <p className="text-[10px] text-text-secondary uppercase tracking-tight">{v.role}</p>}
                    </div>
                  </div>
                );
              })}
            </div>
            <div className="px-6 py-4 border-t border-ui-border dark:border-ui-dark-border flex items-center justify-end gap-2">
              <button
                type="button"
                className="px-4 py-1.5 rounded border border-ui-border text-[10px] font-black uppercase tracking-wider text-text-secondary hover:bg-ui-body transition-colors"
                onClick={() => setShowValidationModal(false)}
              >
                Cancelar
              </button>
              <button
                type="button"
                className="px-4 py-1.5 rounded bg-odoo-purple text-white text-[10px] font-black uppercase tracking-wider shadow-sm hover:bg-odoo-purple/90 transition-colors"
                onClick={() => navigate('/templates')}
              >
                Confirmar y salir →
              </button>
            </div>
          </div>
        </div>
      )}

    </div>
  );
}
