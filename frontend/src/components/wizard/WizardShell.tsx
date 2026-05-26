import type { ReactNode } from 'react';
import { PageTitle } from '@ceedcv-maya/shared-ui-react';

export type WizardStepDef<Id extends string = string> = {
  id: Id;
  label: string;
  sub?: string;
};

type Props<Id extends string> = {
  title: ReactNode;
  subtitle?: ReactNode;
  onBack: () => void;
  backLabel?: string;
  actions?: ReactNode;
  steps: WizardStepDef<Id>[];
  currentStep: Id;
  completedSteps: Id[];
  onGoToStep?: (id: Id) => void;
  /** Banner opcional (leave guard, error API) entre header y stepper. */
  banner?: ReactNode;
  /** Si false, no se renderiza el stepper (modo validación, etc.). */
  showStepper?: boolean;
  /** Reemplaza el stepper por contenido custom (modo validación). */
  stepperOverride?: ReactNode;
  children: ReactNode;
};

/**
 * Cabecera + stepper + cuerpo común a los wizards de plantillas y documentos.
 *
 * Contrato de altura:
 *   - Root con altura fija `100vh - 4rem` (descontando AppLayout topbar).
 *   - Header / banner / stepper son `shrink-0`.
 *   - El `children` recibe un wrapper `flex-1 overflow-hidden flex flex-col min-h-0`,
 *     por lo que sus hijos directos pueden usar `flex-1 min-h-0` para llenar el alto
 *     restante (necesario para editores tipo BlockNote).
 */
export function WizardShell<Id extends string>({
  title,
  subtitle,
  onBack,
  backLabel = 'Volver',
  actions,
  steps,
  currentStep,
  completedSteps,
  onGoToStep,
  banner,
  showStepper = true,
  stepperOverride,
  children,
}: Props<Id>) {
  return (
    <div className="flex flex-col h-[calc(100vh-4rem)] bg-transparent">
      <div className="shrink-0">
        <PageTitle
          title={title}
          subtitle={subtitle}
          onBack={onBack}
          backLabel={backLabel}
          className="!mb-2"
          actions={actions}
        />
      </div>

      {banner ? <div className="shrink-0">{banner}</div> : null}

      {stepperOverride ? (
        <div className="shrink-0">{stepperOverride}</div>
      ) : showStepper ? (
        <WizardStepper
          steps={steps}
          currentStep={currentStep}
          completedSteps={completedSteps}
          onGoToStep={onGoToStep}
        />
      ) : null}

      <div className="flex-1 overflow-visible flex flex-col min-h-0">{children}</div>
    </div>
  );
}

type StepperProps<Id extends string> = {
  steps: WizardStepDef<Id>[];
  currentStep: Id;
  completedSteps: Id[];
  onGoToStep?: (id: Id) => void;
};

function WizardStepper<Id extends string>({
  steps,
  currentStep,
  completedSteps,
  onGoToStep,
}: StepperProps<Id>) {
  return (
    <div className="flex items-center px-6 py-4 bg-white dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border shrink-0">
      {steps.map((s, i) => {
        const isActive = currentStep === s.id;
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

        const goToStep = onGoToStep && !isPending ? () => onGoToStep(s.id) : undefined;

        return (
          <div key={s.id} className="flex flex-1 items-center last:flex-none">
            <button
              type="button"
              onClick={goToStep}
              className={`flex items-center gap-3 focus:outline-none transition-all group ${
                isPending ? 'opacity-50 cursor-default' : 'cursor-pointer hover:scale-105'
              }`}
              disabled={isPending}
            >
              <span
                className={`flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold shrink-0 transition-colors shadow-sm ${circleCls}`}
              >
                {isDone && !isActive ? '✓' : i + 1}
              </span>
              <span className="text-left hidden lg:block">
                <span className={`block text-xs font-black uppercase tracking-widest ${labelCls}`}>
                  {s.label}
                </span>
                {s.sub ? (
                  <span className="block text-xs text-text-muted">{s.sub}</span>
                ) : null}
              </span>
            </button>
            {i < steps.length - 1 && (
              <div
                className={`flex-1 h-0.5 mx-4 rounded-full ${
                  completedSteps.includes(s.id) ? 'bg-success' : 'bg-ui-border'
                }`}
              />
            )}
          </div>
        );
      })}
    </div>
  );
}
