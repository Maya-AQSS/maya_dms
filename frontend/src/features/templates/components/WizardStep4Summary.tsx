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
    <div className="bg-ui-card dark:bg-ui-dark-card rounded-lg border border-ui-border dark:border-ui-dark-border shadow-card overflow-hidden">
      <div className="px-5 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between bg-ui-card/50 dark:bg-ui-dark-card/50">
        <span className="text-[10px] font-bold uppercase tracking-wider text-text-secondary dark:text-text-dark-secondary">
          {title}
        </span>
        <Button type="button" variant="outline" size="xs" onClick={onEdit}>
          Editar →
        </Button>
      </div>
      <div className="p-5">{children}</div>
    </div>
  );
}

function SummaryRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex gap-3">
      <dt className="w-32 shrink-0 text-xs text-text-muted dark:text-text-dark-muted">{label}</dt>
      <dd className="flex-1 text-xs text-text-primary dark:text-text-dark-primary">{value}</dd>
    </div>
  );
}

// ── Main component ────────────────────────────────────────────────────────────

export function WizardStep4Summary({ template, validators, validationType, onGoToStep }: Props) {
  const { blocks } = useTemplateBlocks(template.id);

  const hierarchyFields: { label: string; value: string | null }[] = [
    { label: 'Tipo de Estudio', value: template.study_type_id },
    { label: 'Estudio', value: template.study_id },
    { label: 'Módulo', value: template.module_id },
    { label: 'Grupo', value: template.group_id },
  ].filter((f) => f.value);

  return (
    <div className="flex-1 overflow-auto p-6">
      <p className="text-xs text-text-muted dark:text-text-dark-muted italic mb-6 max-w-2xl mx-auto">
        Revisa todos los datos antes de publicar la plantilla. Puedes editar cualquier sección
        volviendo al paso correspondiente.
      </p>

      <div className="max-w-2xl mx-auto space-y-4">
        {/* Propiedades */}
        <SummaryCard title="Propiedades" onEdit={() => onGoToStep('properties')}>
          <dl className="space-y-3">
            <SummaryRow label="Nombre" value={template.name} />
            {template.description && (
              <SummaryRow label="Descripción" value={template.description} />
            )}
            <SummaryRow label="Visibilidad" value={visibilityLabel(template.visibility_level)} />
            {hierarchyFields.map((f) => (
              <SummaryRow key={f.label} label={f.label} value={f.value!} />
            ))}
            {template.delivery_deadline && (
              <SummaryRow
                label="Plazo de entrega"
                value={new Date(template.delivery_deadline).toLocaleString()}
              />
            )}
          </dl>
        </SummaryCard>

        {/* Bloques */}
        <SummaryCard title={`Bloques (${blocks.length})`} onEdit={() => onGoToStep('blocks')}>
          {blocks.length === 0 ? (
            <p className="text-xs text-text-muted dark:text-text-dark-muted">
              Sin bloques. Vuelve al paso anterior para añadir al menos uno.
            </p>
          ) : (
            <div className="space-y-2">
              {blocks.map((block, i) => {
                const cfg = BLOCK_UI_STATE_CONFIG[blockToUiState(block)];
                return (
                  <div key={block.id} className="flex items-center gap-2">
                    <span className="text-xs text-text-muted dark:text-text-dark-muted w-5 shrink-0 text-right">
                      {i + 1}.
                    </span>
                    <span className="flex-1 text-xs text-text-primary dark:text-text-dark-primary truncate">
                      {block.title || 'Bloque sin nombre'}
                    </span>
                    <span
                      className={[
                        'shrink-0 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-tight',
                        cfg.badgeCls,
                      ].join(' ')}
                    >
                      {cfg.label}
                    </span>
                  </div>
                );
              })}
            </div>
          )}
        </SummaryCard>

        {/* Usuarios y validación */}
        <SummaryCard title="Usuarios y validación" onEdit={() => onGoToStep('users')}>
          <dl className="space-y-3 mb-3">
            <SummaryRow
              label="Tipo de validación"
              value={validationType === 'ordenada' ? 'Ordenada' : 'Libre'}
            />
          </dl>

          {validators.length === 0 ? (
            <p className="text-xs text-text-muted dark:text-text-dark-muted">
              Sin validadores asignados.
            </p>
          ) : (
            <div className="space-y-2">
              {validators.map((v, i) => {
                const initials = v.name
                  .split(' ')
                  .slice(0, 2)
                  .map((w) => w[0]?.toUpperCase() ?? '')
                  .join('');
                return (
                  <div key={v.userId} className="flex items-center gap-2">
                    {validationType === 'ordenada' && (
                      <span className="shrink-0 flex items-center justify-center w-5 h-5 rounded-full bg-odoo-purple/15 text-odoo-purple dark:bg-odoo-dark-purple/20 dark:text-odoo-dark-purple text-[10px] font-bold">
                        {i + 1}
                      </span>
                    )}
                    <span className="shrink-0 flex items-center justify-center w-6 h-6 rounded-full bg-odoo-purple/15 dark:bg-odoo-dark-purple/25 text-odoo-purple dark:text-odoo-dark-purple text-xs font-bold">
                      {initials}
                    </span>
                    <span className="text-xs text-text-primary dark:text-text-dark-primary">
                      {v.name}
                    </span>
                    {v.role && (
                      <span className="text-[10px] text-text-muted dark:text-text-dark-muted ml-auto">
                        {v.role}
                      </span>
                    )}
                  </div>
                );
              })}
            </div>
          )}
        </SummaryCard>
      </div>
    </div>
  );
}
