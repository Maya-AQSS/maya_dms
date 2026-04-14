import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import type { Template, TemplateVisibilityLevel } from '../../../types/templates';
import { updateTemplate as apiUpdateTemplate } from '../../../api/templates';
import { fetchGroups } from '../../../api/groups';
import type { Group } from '../../../types/groups';
import { useHierarchy } from '../../../features/hierarchy';
import { VISIBILITY_OPTIONS, STATUS_OPTIONS } from '../constants';
import {
  templateEditIsDirty,
  isoToDatetimeLocal,
  datetimeLocalToIso,
  type TemplateEditFields,
} from '../templateFormUtils';
import { Button, FieldLabel, Select, TextArea, TextInput } from '../../../ui';
import { TemplateBlockEditor } from './TemplateBlockEditor';

// ── Stepper ───────────────────────────────────────────────────────────────────

type StepperProps = {
  step: 'properties' | 'blocks';
  step1Done: boolean;
  onGoToStep1: () => void;
  onGoToStep2: () => void;
};

function Stepper({ step, step1Done, onGoToStep1, onGoToStep2 }: StepperProps) {
  const isStep1Active = step === 'properties';
  const isStep2Active = step === 'blocks';

  const step1CircleCls = isStep1Active
    ? 'bg-odoo-purple dark:bg-odoo-dark-purple text-white'
    : step1Done
      ? 'bg-odoo-teal dark:bg-odoo-dark-teal text-white'
      : 'border-2 border-ui-border dark:border-ui-dark-border text-text-muted dark:text-text-dark-muted';

  const step1LabelCls = isStep1Active
    ? 'text-odoo-purple dark:text-odoo-dark-purple'
    : step1Done
      ? 'text-odoo-teal dark:text-odoo-dark-teal'
      : 'text-text-muted dark:text-text-dark-muted';

  const step2CircleCls = isStep2Active
    ? 'bg-odoo-purple dark:bg-odoo-dark-purple text-white'
    : 'border-2 border-ui-border dark:border-ui-dark-border text-text-muted dark:text-text-dark-muted';

  const step2LabelCls = isStep2Active
    ? 'text-odoo-purple dark:text-odoo-dark-purple'
    : 'text-text-muted dark:text-text-dark-muted';

  const connectorCls = step1Done
    ? 'bg-odoo-teal dark:bg-odoo-dark-teal'
    : 'bg-ui-border dark:bg-ui-dark-border';

  return (
    <div className="flex items-center px-6 py-4 bg-ui-card dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border shrink-0">
      {/* Step 1 */}
      <button
        type="button"
        className="flex items-center gap-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-odoo-purple/35 rounded"
        onClick={onGoToStep1}
      >
        <span
          className={[
            'flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold shrink-0',
            step1CircleCls,
          ].join(' ')}
        >
          {step1Done && !isStep1Active ? '✓' : '1'}
        </span>
        <span className="text-left">
          <span className={['block text-sm font-semibold', step1LabelCls].join(' ')}>
            Propiedades
          </span>
          <span className="block text-xs text-text-muted dark:text-text-dark-muted">
            Nombre, descripción…
          </span>
        </span>
      </button>

      {/* Connector */}
      <div className={['flex-1 h-0.5 mx-4', connectorCls].join(' ')} />

      {/* Step 2 */}
      <button
        type="button"
        className="flex items-center gap-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-odoo-purple/35 rounded"
        onClick={onGoToStep2}
      >
        <span
          className={[
            'flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold shrink-0',
            step2CircleCls,
          ].join(' ')}
        >
          2
        </span>
        <span className="text-left">
          <span className={['block text-sm font-semibold', step2LabelCls].join(' ')}>
            Bloques
          </span>
          <span className="block text-xs text-text-muted dark:text-text-dark-muted">
            Estructura del documento
          </span>
        </span>
      </button>
    </div>
  );
}

// ── TemplateWizard ────────────────────────────────────────────────────────────

export function TemplateWizard({ template: initialTemplate }: { template: Template }) {
  const navigate = useNavigate();

  // Step state
  const [step, setStep] = useState<'properties' | 'blocks'>('properties');
  const [step1Done, setStep1Done] = useState(false);
  const [leaveGuard, setLeaveGuard] = useState(false);

  // Template (updated after save)
  const [template, setTemplate] = useState<Template>(initialTemplate);

  // Form fields
  const [name, setName] = useState(initialTemplate.name);
  const [description, setDescription] = useState(initialTemplate.description ?? '');
  const [visibilityLevel, setVisibilityLevel] = useState<TemplateVisibilityLevel>(
    initialTemplate.visibility_level,
  );
  const [deliveryDeadline, setDeliveryDeadline] = useState(
    isoToDatetimeLocal(initialTemplate.delivery_deadline),
  );
  const [studyTypeId, setStudyTypeId] = useState(initialTemplate.study_type_id ?? '');
  const [studyId, setStudyId] = useState(initialTemplate.study_id ?? '');
  const [moduleId, setModuleId] = useState(initialTemplate.module_id ?? '');
  const [groupId, setGroupId] = useState(initialTemplate.group_id ?? '');
  const [status, setStatus] = useState(initialTemplate.status);
  const [reviewStages, setReviewStages] = useState(String(initialTemplate.review_stages));
  const [reviewMode, setReviewMode] = useState(initialTemplate.review_mode);

  // UI state
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  // Groups
  const [groups, setGroups] = useState<Group[]>([]);

  useEffect(() => {
    fetchGroups(200)
      .then((res) => setGroups(res.data))
      .catch(() => undefined);
  }, []);

  // Cascade hierarchy
  const { hierarchy, loading: hierarchyLoading } = useHierarchy();
  const selectedTypeData = hierarchy.find((t) => t.id === studyTypeId);
  const availableStudies = selectedTypeData ? selectedTypeData.studies : [];
  const selectedStudyData = availableStudies.find((s) => s.id === studyId);
  const availableModules = selectedStudyData ? selectedStudyData.course_modules : [];

  // Dirty check
  const editFields: TemplateEditFields = {
    name,
    description,
    visibilityLevel,
    deliveryDeadline,
    studyTypeId,
    studyId,
    moduleId,
    groupId,
    status,
    reviewStages,
    reviewMode,
  };
  const isDirty = step === 'properties' && templateEditIsDirty(template, editFields);

  // Handlers
  const handleBackArrow = () => {
    if (isDirty) {
      setLeaveGuard(true);
      return;
    }
    navigate('/templates');
  };

  const handleSaveAndContinue = async () => {
    if (!name.trim()) return;
    setSaving(true);
    setSaveError(null);
    try {
      const res = await apiUpdateTemplate(template.id, {
        name: name.trim(),
        description: description.trim() === '' ? null : description.trim(),
        visibility_level: visibilityLevel,
        delivery_deadline: datetimeLocalToIso(deliveryDeadline),
        study_type_id: studyTypeId.trim() === '' ? null : studyTypeId.trim(),
        study_id: studyId.trim() === '' ? null : studyId.trim(),
        module_id: moduleId.trim() === '' ? null : moduleId.trim(),
        group_id: groupId.trim() === '' ? null : groupId.trim(),
        status,
        review_stages: parseInt(reviewStages, 10) || 0,
        review_mode: reviewMode,
      });
      setTemplate(res.data);
      setStep1Done(true);
      setStep('blocks');
    } catch (e) {
      setSaveError(e instanceof Error ? e.message : 'Error al guardar la plantilla');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="flex flex-col min-h-full bg-ui-body dark:bg-ui-dark-bg">
      {/* Top bar */}
      <div className="shrink-0 flex items-center gap-3 px-4 py-3 bg-ui-card dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border">
        <button
          type="button"
          onClick={handleBackArrow}
          className="w-8 h-8 rounded text-text-secondary dark:text-text-dark-secondary hover:bg-ui-body dark:hover:bg-ui-dark-bg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-odoo-purple/35 flex items-center justify-center"
          aria-label="Volver a plantillas"
        >
          ←
        </button>
        <span className="text-sm text-text-secondary dark:text-text-dark-secondary">
          Plantillas /{' '}
          <span className="font-medium text-text-primary dark:text-text-dark-primary">
            Editando «{template.name}»
          </span>
        </span>
      </div>

      {/* Leave guard strip */}
      {leaveGuard && (
        <div className="shrink-0 flex items-center gap-3 px-4 py-2.5 border-b border-warning/30 bg-warning-light/50 dark:bg-warning-dark/15 text-sm">
          <span className="flex-1 text-warning-dark dark:text-warning-light">
            Tienes cambios sin guardar. ¿Salir sin guardar?
          </span>
          <button
            type="button"
            className="font-semibold underline text-warning-dark dark:text-warning-light focus:outline-none"
            onClick={() => navigate('/templates')}
          >
            Salir
          </button>
          <button
            type="button"
            className="underline text-text-secondary dark:text-text-dark-secondary focus:outline-none"
            onClick={() => setLeaveGuard(false)}
          >
            Cancelar
          </button>
        </div>
      )}

      {/* Stepper */}
      <Stepper
        step={step}
        step1Done={step1Done}
        onGoToStep1={() => setStep('properties')}
        onGoToStep2={() => {
          if (step1Done) setStep('blocks');
        }}
      />

      {/* Content area */}
      {step === 'properties' ? (
        <div className="flex-1 overflow-auto p-6">
          {saveError && (
            <div className="mb-4 rounded-lg border border-warning/40 bg-warning-light/40 dark:bg-warning-dark/10 px-4 py-3 text-sm text-warning-dark dark:text-warning-light flex justify-between gap-4">
              <span>{saveError}</span>
              <Button
                type="button"
                variant="ghost"
                size="xs"
                onClick={() => setSaveError(null)}
              >
                ✕
              </Button>
            </div>
          )}

          <div className="max-w-3xl mx-auto bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card p-6">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
              {/* Nombre */}
              <div className="lg:col-span-2">
                <FieldLabel>Nombre</FieldLabel>
                <TextInput
                  type="text"
                  fieldSize="comfortable"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="Ej. Acta de Evaluación Final"
                />
              </div>

              {/* Descripción */}
              <div className="lg:col-span-2">
                <FieldLabel>Descripción</FieldLabel>
                <TextArea
                  fieldSize="comfortable"
                  rows={3}
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  placeholder="Propósito de la plantilla…"
                />
              </div>

              {/* Visibilidad */}
              <div>
                <FieldLabel>Visibilidad</FieldLabel>
                <Select
                  fieldSize="comfortable"
                  value={visibilityLevel}
                  onChange={(e) => setVisibilityLevel(e.target.value as TemplateVisibilityLevel)}
                >
                  {VISIBILITY_OPTIONS.map((o) => (
                    <option key={o.value} value={o.value}>
                      {o.label}
                    </option>
                  ))}
                </Select>
              </div>

              {/* Plazo de entrega */}
              <div>
                <FieldLabel>Plazo de entrega</FieldLabel>
                <TextInput
                  type="datetime-local"
                  fieldSize="comfortable"
                  value={deliveryDeadline}
                  onChange={(e) => setDeliveryDeadline(e.target.value)}
                />
              </div>

              {/* Estado */}
              <div>
                <FieldLabel>Estado</FieldLabel>
                <Select
                  fieldSize="comfortable"
                  value={status}
                  onChange={(e) => setStatus(e.target.value as Template['status'])}
                >
                  <option value="draft">Borrador</option>
                  <option value="published">Publicada</option>
                  <option value="archived">Archivada</option>
                </Select>
              </div>

              {/* Sesiones de validación */}
              <div>
                <FieldLabel>Sesiones de validación</FieldLabel>
                <TextInput
                  type="number"
                  min={0}
                  fieldSize="comfortable"
                  value={reviewStages}
                  onChange={(e) => setReviewStages(e.target.value)}
                />
              </div>

              {/* Modo de revisión */}
              <div>
                <FieldLabel>Modo de revisión</FieldLabel>
                <Select
                  fieldSize="comfortable"
                  value={reviewMode}
                  onChange={(e) => setReviewMode(e.target.value as Template['review_mode'])}
                >
                  <option value="sequential">Secuencial</option>
                  <option value="parallel">Paralelo</option>
                </Select>
              </div>

              {/* Vinculación Académica */}
              <div className="lg:col-span-2">
                <div className="border-t border-ui-border dark:border-ui-dark-border pt-4">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-text-secondary dark:text-text-dark-secondary mb-4">
                    Vinculación Académica
                  </h4>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {/* Tipo de Estudio */}
                    <div>
                      <FieldLabel>Tipo de Estudio</FieldLabel>
                      <Select
                        fieldSize="comfortable"
                        value={studyTypeId}
                        disabled={hierarchyLoading}
                        onChange={(e) => {
                          setStudyTypeId(e.target.value);
                          setStudyId('');
                          setModuleId('');
                          setGroupId('');
                        }}
                      >
                        <option value="">— Sin seleccionar —</option>
                        {hierarchy.map((t) => (
                          <option key={t.id} value={t.id}>
                            {t.name}
                          </option>
                        ))}
                      </Select>
                    </div>

                    {/* Estudio */}
                    <div>
                      <FieldLabel>Estudio</FieldLabel>
                      <Select
                        fieldSize="comfortable"
                        value={studyId}
                        disabled={!studyTypeId}
                        onChange={(e) => {
                          setStudyId(e.target.value);
                          setModuleId('');
                          setGroupId('');
                        }}
                      >
                        <option value="">— Sin seleccionar —</option>
                        {availableStudies.map((s) => (
                          <option key={s.id} value={s.id}>
                            {s.name}
                          </option>
                        ))}
                      </Select>
                    </div>

                    {/* Módulo */}
                    <div>
                      <FieldLabel>Módulo</FieldLabel>
                      <Select
                        fieldSize="comfortable"
                        value={moduleId}
                        disabled={!studyId}
                        onChange={(e) => {
                          setModuleId(e.target.value);
                          setGroupId('');
                        }}
                      >
                        <option value="">— Sin seleccionar —</option>
                        {availableModules.map((m) => (
                          <option key={m.id} value={m.id}>
                            {m.name}
                          </option>
                        ))}
                      </Select>
                    </div>

                    {/* Grupo */}
                    <div>
                      <FieldLabel>Grupo</FieldLabel>
                      <Select
                        fieldSize="comfortable"
                        value={groupId}
                        onChange={(e) => setGroupId(e.target.value)}
                      >
                        <option value="">— Sin seleccionar —</option>
                        {groups.map((g) => (
                          <option key={g.id} value={g.id}>
                            {g.name}
                          </option>
                        ))}
                      </Select>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      ) : (
        <div className="flex flex-1 overflow-hidden">
          <TemplateBlockEditor template={template} inline />
        </div>
      )}

      {/* Footer bar */}
      <div className="shrink-0 border-t border-ui-border dark:border-ui-dark-border bg-ui-card dark:bg-ui-dark-card px-6 py-4 flex items-center justify-between gap-4">
        {step === 'properties' ? (
          <>
            <span className="hidden sm:block text-xs text-text-muted dark:text-text-dark-muted italic">
              Los cambios no se guardan hasta pulsar «Guardar y continuar»
            </span>
            <div className="flex items-center gap-3 ml-auto">
              <Button
                type="button"
                variant="secondary"
                size="md"
                disabled={saving}
                onClick={handleBackArrow}
              >
                Cancelar
              </Button>
              <Button
                type="button"
                variant="primary"
                size="md"
                loading={saving}
                disabled={!name.trim()}
                onClick={() => void handleSaveAndContinue()}
              >
                Guardar y continuar →
              </Button>
            </div>
          </>
        ) : (
          <>
            <Button
              type="button"
              variant="outline"
              size="md"
              onClick={() => setStep('properties')}
            >
              ← Volver a Propiedades
            </Button>
            <div className="flex items-center gap-3">
              <Button
                type="button"
                variant="secondary"
                size="md"
                onClick={() => navigate('/templates')}
              >
                Cancelar
              </Button>
              <Button
                type="button"
                variant="primary"
                size="md"
                onClick={() => navigate('/templates')}
              >
                Finalizar plantilla ✓
              </Button>
            </div>
          </>
        )}
      </div>
    </div>
  );
}
