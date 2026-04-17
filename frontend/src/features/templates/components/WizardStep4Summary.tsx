import type { ReactNode } from 'react';
import { Button } from '../../../ui';
import type { Template } from '../../../types/templates';
import { visibilityLabel } from '../constants';
import type { ValidatorEntry } from './WizardStep3Users';
import { BLOCK_UI_STATE_CONFIG, blockToUiState } from '../blockUiState';
import { useTemplateBlocks } from '../hooks/useTemplateBlocks';

// ── Types ────────────────────────────────────────────────────────────────────

type WizardStep = 'properties' | 'blocks' | 'users' | 'summary';

type Props = {
  template: Template;
  validators: ValidatorEntry[];
  validationType: 'libre' | 'ordenada';
  onGoToStep: (step: WizardStep) => void;
};

// ── Summary card ─────────────────────────────────────────────────────────────

function SummaryCard({
  title,
  children,
  onEdit,
}: {
  title: string;
  children: ReactNode;
  onEdit: () => void;
}) {
  return (
    <div className="bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm overflow-hidden animate-in fade-in slide-in-from-top-1">
      <div className="px-5 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between bg-ui-card/50 dark:bg-ui-dark-card/50">
        <span className="text-[10px] font-bold uppercase tracking-widest text-text-secondary">
          {title}
        </span>
        <Button 
          type="button" 
          variant="secondary" 
          size="xs" 
          onClick={onEdit}
          className="hover:text-odoo-purple"
        >
          Editar →
        </Button>
      </div>
      <div className="p-5">{children}</div>
    </div>
  );
}

function SummaryRow({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex flex-col sm:flex-row sm:gap-4 py-2 border-b border-ui-border dark:border-ui-dark-border/30 last:border-0">
      <dt className="sm:w-32 shrink-0 text-[10px] font-bold uppercase tracking-wider text-text-secondary dark:text-text-dark-secondary mt-0.5">
        {label}
      </dt>
      <dd className="flex-1 text-sm font-medium text-text-primary dark:text-text-dark-primary">
        {value || <span className="text-text-muted italic">—</span>}
      </dd>
    </div>
  );
}

// ── Main component ────────────────────────────────────────────────────────────

export function WizardStep4Summary({ template, validators, validationType, onGoToStep }: Props) {
  const { blocks } = useTemplateBlocks(template.id);

  const hierarchyFields = [
    { label: 'Tipo de Estudio', value: template.study_type_id },
    { label: 'Estudio', value: template.study_id },
    { label: 'Módulo', value: template.module_id },
    { label: 'Equipo', value: template.team_id },
  ].filter((f) => f.value);

  return (
    <div className="flex-1 overflow-y-auto px-8 py-6 bg-ui-body/30">
      <div className="space-y-4">

        <p className="text-xs text-text-muted text-center animate-in fade-in">
          Revisa todos los datos antes de publicar la plantilla. Puedes editar cualquier sección volviendo al paso correspondiente.
        </p>

        {/* Sección 1: Propiedades */}
        <SummaryCard title="Propiedades" onEdit={() => onGoToStep('properties')}>
          <dl className="divide-y divide-ui-border/20">
            <SummaryRow label="Nombre" value={template.name} />
            <SummaryRow label="Descripción" value={template.description} />
            <SummaryRow label="Visibilidad" value={visibilityLabel(template.visibility_level)} />
            {hierarchyFields.map((f) => (
              <SummaryRow key={f.label} label={f.label} value={f.value} />
            ))}
            <SummaryRow 
              label="Plazo de entrega" 
              value={template.delivery_deadline ? new Date(template.delivery_deadline).toLocaleDateString() : null} 
            />
          </dl>
        </SummaryCard>

        {/* Sección 2: Bloques */}
        <SummaryCard title={`Bloques (${blocks.length})`} onEdit={() => onGoToStep('blocks')}>
          {blocks.length === 0 ? (
            <p className="text-xs text-warning-dark italic">Aún no se han añadido bloques.</p>
          ) : (
            <div className="space-y-3">
              {blocks.map((block, i) => {
                const cfg = BLOCK_UI_STATE_CONFIG[blockToUiState(block)];
                return (
                  <div key={block.id} className="flex items-center gap-3 p-2 rounded border border-ui-border dark:border-ui-dark-border/50 bg-white dark:bg-ui-dark-card shadow-sm">
                    <span className="text-[10px] font-bold text-text-secondary w-5 text-right">{i + 1}.</span>
                    <span className="flex-1 text-xs font-medium text-text-primary dark:text-text-dark-primary truncate">{block.title}</span>
                    <span className={`shrink-0 px-2 py-0.5 rounded text-[10px] font-bold uppercase ${cfg.badgeCls}`}>
                      {cfg.label}
                    </span>
                  </div>
                );
              })}
            </div>
          )}
        </SummaryCard>

        {/* Sección 3: Usuarios y validación */}
        <SummaryCard title="Usuarios y validación" onEdit={() => onGoToStep('users')}>
          <div className="space-y-6">
            <div className="bg-ui-body/30 dark:bg-ui-dark-bg/50 border border-ui-border dark:border-ui-dark-border rounded-lg p-3">
              <span className="text-[10px] font-bold uppercase text-text-secondary">Tipo de validación:</span>
              <span className="ml-2 text-xs font-bold text-text-primary dark:text-text-dark-primary capitalize">{validationType}</span>
            </div>

            {validators.length === 0 ? (
              <p className="text-xs text-text-muted italic">Sin validadores asignados.</p>
            ) : (
              <div className="space-y-2">
                {validators.map((v, i) => {
                  const initials = v.name
                    .split(' ')
                    .filter(Boolean)
                    .slice(0, 2)
                    .map((w) => w[0]?.toUpperCase() ?? '')
                    .join('');
                  return (
                    <div key={v.userId} className="flex items-center gap-3 p-2 rounded border border-ui-border dark:border-ui-dark-border/50 bg-white dark:bg-ui-dark-card shadow-sm">
                      {validationType === 'ordenada' && (
                        <span className="shrink-0 flex items-center justify-center w-5 h-5 rounded-full bg-odoo-purple text-white text-[10px] font-bold">
                          {i + 1}
                        </span>
                      )}
                      <span className="shrink-0 flex items-center justify-center w-8 h-8 rounded-full bg-odoo-purple/10 text-odoo-purple dark:text-odoo-dark-purple text-[10px] font-black border border-odoo-purple/20">
                        {initials}
                      </span>
                      <div className="flex-1 min-w-0">
                        <span className="text-xs font-bold text-text-primary dark:text-text-dark-primary block truncate">{v.name}</span>
                        {v.role && <span className="text-[10px] text-text-secondary dark:text-text-dark-secondary uppercase tracking-tight">{v.role}</span>}
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </SummaryCard>
      </div>
    </div>
  );
}
