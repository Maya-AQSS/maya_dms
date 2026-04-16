import { useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import type { Template, TemplateVisibilityLevel } from '../../../types/templates';
import { updateTemplate as apiUpdateTemplate, createTemplate as apiCreateTemplate } from '../../../api/templates';
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
  const [completedSteps, setCompletedSteps] = useState<Step[]>([]);
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
  const [groupId, setGroupId] = useState(initial?.group_id || '');
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Step 2: Blocks state
  const [blocksCount, setBlocksCount] = useState(0);

  // Step 3: Users state
  const [validators, setValidators] = useState<ValidatorEntry[]>([]);
  const [validationType, setValidationType] = useState<'libre' | 'ordenada'>('libre');

  // UI state
  const [saving, setSaving] = useState(false);

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
    
    const needsAcademic = visibility !== 'personal' && visibility !== 'global';
    if (needsAcademic) {
      if (visibility === 'study_type' && !studyTypeId) newErrors.studyTypeId = 'Obligatorio';
      if (visibility === 'study' && !studyId) newErrors.studyId = 'Obligatorio';
      if (visibility === 'module' && !moduleId) newErrors.moduleId = 'Obligatorio';
      if (visibility === 'group' && !groupId) newErrors.groupId = 'Obligatorio';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const saveProperties = async () => {
    if (!validateStep1()) return;
    setSaving(true);
    try {
      const payload = {
        name: name.trim(),
        description: description.trim() || null,
        visibility_level: visibility,
        delivery_deadline: deliveryDeadline ? `${deliveryDeadline}T00:00:00Z` : null,
        study_type_id: studyTypeId || null,
        study_id: studyId || null,
        module_id: moduleId || null,
        group_id: groupId || null,
      };

      let res;
      if (template?.id) {
        res = await apiUpdateTemplate(template.id, payload);
      } else {
        res = await apiCreateTemplate(payload);
      }
      setTemplate(res.data);
      setCompletedSteps(prev => Array.from(new Set([...prev, 'properties'])) as Step[]);
      setStep('blocks');
    } catch (e) {
      setErrors({ api: e instanceof Error ? e.message : 'Error al guardar' });
    } finally {
      setSaving(false);
    }
  };

  const handleContinue = () => {
    if (step === 'properties') {
      void saveProperties();
    } else if (step === 'blocks') {
      if (blocksCount > 0) {
        setCompletedSteps(prev => Array.from(new Set([...prev, 'blocks'])) as Step[]);
        setStep('users');
      }
    } else if (step === 'users') {
      setCompletedSteps(prev => Array.from(new Set([...prev, 'users'])) as Step[]);
      setStep('summary');
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
      <div className="shrink-0 flex items-center gap-3 px-4 py-3 bg-white dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border shadow-sm z-10">
        <button
          type="button"
          onClick={handleBackArrow}
          className="w-9 h-9 rounded-full text-text-secondary hover:bg-ui-body dark:hover:bg-ui-dark-bg transition-all flex items-center justify-center border border-transparent hover:border-ui-border active:scale-95"
          aria-label="Volver"
        >
          ←
        </button>
        <span className="text-sm text-text-secondary">
          Plantillas /{' '}
          <span className="font-bold text-text-primary dark:text-text-dark-primary">
            {template ? `Editando «${template.name}»` : 'Nueva plantilla'}
          </span>
        </span>
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

      {/* Stepper */}
      {renderStepper()}

      {/* Body — only this element scrolls */}
      <div className="flex-1 overflow-hidden flex flex-col min-h-0 bg-ui-body/30 dark:bg-ui-dark-bg">
        {step === 'properties' && (
          <WizardStep1Properties
            name={name} setName={setName}
            description={description} setDescription={setDescription}
            visibility={visibility} setVisibility={setVisibility}
            deliveryDeadline={deliveryDeadline} setDeliveryDeadline={setDeliveryDeadline}
            studyTypeId={studyTypeId} setStudyTypeId={setStudyTypeId}
            studyId={studyId} setStudyId={setStudyId}
            moduleId={moduleId} setModuleId={setModuleId}
            groupId={groupId} setGroupId={setGroupId}
            errors={errors}
          />
        )}
        {step === 'blocks' && template && (
          <WizardStep2Blocks
            template={template}
            onBlocksCountChange={setBlocksCount}
          />
        )}
        {step === 'users' && (
          <WizardStep3Users
            validators={validators}
            onValidatorsChange={setValidators}
            validationType={validationType}
            onValidationTypeChange={setValidationType}
          />
        )}
        {step === 'summary' && template && (
          <WizardStep4Summary
            template={template}
            validators={validators}
            validationType={validationType}
            onGoToStep={handleGoToStep}
          />
        )}
      </div>

      {/* Footer — outside the scroll area, always visible */}
      <div className="shrink-0 border-t border-ui-border dark:border-ui-dark-border bg-white dark:bg-ui-dark-card px-6 py-4 flex items-center justify-between gap-4 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
          <div className="flex-1">
            {step === 'properties' && (
              <span className="text-[10px] text-text-muted italic opacity-70">
                Los cambios no se guardan hasta pulsar «Guardar y continuar»
              </span>
            )}
            {step === 'blocks' && (
              <Button variant="ghost" size="sm" onClick={() => setStep('properties')} className="text-odoo-purple font-bold">
                ← Volver a Propiedades
              </Button>
            )}
            {step === 'users' && (
              <Button variant="ghost" size="sm" onClick={() => setStep('blocks')} className="text-odoo-purple font-bold">
                ← Volver a Bloques
              </Button>
            )}
            {step === 'summary' && (
              <Button variant="ghost" size="sm" onClick={() => setStep('users')} className="text-odoo-purple font-bold">
                ← Volver a Usuarios
              </Button>
            )}
          </div>

          <div className="flex items-end gap-3">
            <Button
              variant="secondary"
              size="md"
              onClick={handleBackArrow}
              className="text-[10px] font-black uppercase tracking-widest"
            >
              Cancelar
            </Button>
            <div className="flex flex-col items-end gap-1">
              <Button
                variant="primary"
                size={step === 'summary' ? 'lg' : 'md'}
                loading={saving}
                disabled={step === 'blocks' && blocksCount === 0}
                onClick={handleContinue}
                className={`text-[10px] font-black uppercase tracking-widest px-8 shadow-sm ${step === 'summary' ? 'bg-success border-success hover:bg-success-dark' : ''}`}
              >
                {step === 'summary' ? 'Publicar plantilla ✓' : 'Guardar y continuar →'}
              </Button>
              {step === 'blocks' && blocksCount === 0 && (
                <span className="text-[10px] text-text-muted italic">
                  Añade al menos un bloque para continuar
                </span>
              )}
            </div>
          </div>
        </div>
    </div>
  );
}
