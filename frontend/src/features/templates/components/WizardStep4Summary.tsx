import { useState, useEffect } from 'react';
import type { TemplateBlock } from '../../../types/blocks';
import type { Template } from '../../../types/templates';
import { visibilityLabel } from '../constants';
import type { ValidatorEntry } from './WizardStep3Users';
import { BLOCK_UI_STATE_CONFIG, blockToUiState } from '../blockUiState';
import { useTemplateBlocks } from '../hooks/useTemplateBlocks';
import { TemplatePreviewModal } from './TemplatePreviewModal';
import { BlockContentHtml } from './BlockContentHtml';

// ── Types ────────────────────────────────────────────────────────────────────

type Props = {
  template: Template;
  validators: ValidatorEntry[];
  validationType: 'libre' | 'ordenada';
  documentValidators?: ValidatorEntry[];
  documentValidationType?: 'libre' | 'ordenada';
  onBlocksCountChange?: (count: number) => void;
  onBlocksLoadingChange?: (loading: boolean) => void;
  onBlocksChange?: (blocks: TemplateBlock[]) => void;
};

type PreviewTab = 'Contenido' | 'Descripción';

// ── SummaryRow helper ─────────────────────────────────────────────────────────

function SummaryRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex flex-col py-1.5 border-b border-ui-border dark:border-ui-dark-border/30 last:border-0">
      <dt className="text-xs font-bold uppercase tracking-wider text-text-secondary dark:text-text-dark-secondary">
        {label}
      </dt>
      <dd className="mt-0.5 text-xs font-medium text-text-primary dark:text-text-dark-primary break-words min-w-0">
        {value || <span className="text-text-muted italic">—</span>}
      </dd>
    </div>
  );
}

// ── Main component ────────────────────────────────────────────────────────────

export function WizardStep4Summary({
  template,
  validators,
  validationType,
  documentValidators = [],
  documentValidationType = 'libre',
  onBlocksCountChange,
  onBlocksLoadingChange,
  onBlocksChange,
}: Props) {
  const { blocks, loading } = useTemplateBlocks(template.id);

  useEffect(() => {
    onBlocksLoadingChange?.(loading);
  }, [loading, onBlocksLoadingChange]);

  useEffect(() => {
    if (!loading) {
      onBlocksCountChange?.(blocks.length);
    }
  }, [blocks.length, loading, onBlocksCountChange]);

  useEffect(() => {
    if (!loading) {
      onBlocksChange?.(blocks);
    }
  }, [blocks, loading, onBlocksChange]);

  const [selectedBlock, setSelectedBlock] = useState<TemplateBlock | null>(null);
  const [activeTab, setActiveTab] = useState<PreviewTab>('Contenido');
  const [showPreview, setShowPreview] = useState(false);

  useEffect(() => {
    if (blocks.length > 0 && !selectedBlock) {
      setSelectedBlock(blocks[0]);
    }
  }, [blocks, selectedBlock]);

  const hierarchyFields = [
    { label: 'Tipo de Estudio', value: template.study_type_id },
    { label: 'Estudio', value: template.study_id },
    { label: 'Módulo', value: template.module_id },
    { label: 'Equipo', value: template.team_id },
  ].filter((f) => f.value);

  return (
    <div className="flex-1 min-h-0 flex flex-col px-6 py-5 space-y-4 overflow-hidden">

      <p className="text-xs text-text-muted text-center shrink-0">
        Revisa todos los datos antes de publicar. Usa el stepper para volver a cualquier paso.
      </p>

      {/* ── Fila superior: Propiedades + Usuarios ──────────────────────────── */}
      <div className="bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm overflow-hidden grid grid-cols-1 md:grid-cols-2 animate-in fade-in slide-in-from-top-1">

        {/* Columna izquierda — Propiedades */}
        <div className="px-5 py-4 border-b md:border-b-0 md:border-r border-ui-border dark:border-ui-dark-border min-w-0">
          <p className="text-xs font-bold uppercase tracking-widest text-text-secondary mb-3">
            Propiedades
          </p>
          <dl className="grid grid-cols-2 gap-x-4 gap-y-0">
            <SummaryRow label="Nombre" value={template.name} />
            <SummaryRow label="Visibilidad" value={visibilityLabel(template.visibility_level)} />
            {hierarchyFields.map((f) => (
              <SummaryRow key={f.label} label={f.label} value={f.value} />
            ))}
            <SummaryRow
              label="Plazo de entrega"
              value={template.delivery_deadline ? new Date(template.delivery_deadline).toLocaleDateString() : null}
            />
            <div className="col-span-2">
              <SummaryRow label="Descripción" value={template.description} />
            </div>
          </dl>
        </div>

        {/* Columna derecha — Usuarios y validación */}
        <div className="px-5 py-4 space-y-4">
          {/* Validadores de la plantilla */}
          <div>
            <div className="flex items-center gap-2 mb-2">
              <p className="text-xs font-bold uppercase tracking-widest text-text-secondary">
                Validadores de la plantilla
              </p>
              <span className="text-xs font-bold text-odoo-purple capitalize">({validationType})</span>
            </div>
            {validators.length === 0 ? (
              <p className="text-xs text-text-muted italic">Sin validadores asignados.</p>
            ) : (
              <div className="space-y-2 overflow-y-auto max-h-36">
                {validators.map((v, i) => {
                  const initials = v.name.split(' ').filter(Boolean).slice(0, 2).map((w) => w[0]?.toUpperCase() ?? '').join('');
                  return (
                    <div key={v.userId} className="flex items-center gap-2.5">
                      {validationType === 'ordenada' && (
                        <span className="shrink-0 w-5 h-5 rounded-full bg-odoo-purple text-text-inverse text-xs font-bold flex items-center justify-center">{i + 1}</span>
                      )}
                      <span className="shrink-0 w-7 h-7 rounded-full bg-odoo-purple/10 text-odoo-purple text-xs font-black border border-odoo-purple/20 flex items-center justify-center">{initials}</span>
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

          {/* Validadores del documento */}
          <div className="pt-3 border-t border-ui-border dark:border-ui-dark-border">
            <div className="flex items-center gap-2 mb-2">
              <p className="text-xs font-bold uppercase tracking-widest text-text-secondary">
                Validadores del documento
              </p>
              <span className="text-xs font-bold text-odoo-teal capitalize">({documentValidationType})</span>
            </div>
            {documentValidators.length === 0 ? (
              <p className="text-xs text-text-muted italic">Sin validadores asignados.</p>
            ) : (
              <div className="space-y-2 overflow-y-auto max-h-36">
                {documentValidators.map((v, i) => {
                  const initials = v.name.split(' ').filter(Boolean).slice(0, 2).map((w) => w[0]?.toUpperCase() ?? '').join('');
                  return (
                    <div key={v.userId} className="flex items-center gap-2.5">
                      {documentValidationType === 'ordenada' && (
                        <span className="shrink-0 w-5 h-5 rounded-full bg-odoo-teal text-text-inverse text-xs font-bold flex items-center justify-center">{i + 1}</span>
                      )}
                      <span className="shrink-0 w-7 h-7 rounded-full bg-odoo-teal/10 text-odoo-teal text-xs font-black border border-odoo-teal/20 flex items-center justify-center">{initials}</span>
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
        </div>
      </div>

      {/* ── Fila inferior: Plantilla (bloques + preview) ──────────────────── */}
      <div className="flex-1 min-h-0 flex flex-col bg-white dark:bg-ui-dark-card rounded-xl border border-ui-border dark:border-ui-dark-border shadow-sm overflow-hidden animate-in fade-in slide-in-from-top-1">

        {/* Cabecera */}
        <div className="px-5 py-3 border-b border-ui-border dark:border-ui-dark-border flex items-center justify-between">
          <span className="text-xs font-bold uppercase tracking-widest text-text-secondary">
            Plantilla — {blocks.length} bloque{blocks.length !== 1 ? 's' : ''}
          </span>
          <button
            type="button"
            onClick={() => setShowPreview(true)}
            className="px-3 py-1 text-xs font-bold uppercase tracking-wider rounded border border-ui-border dark:border-ui-dark-border text-text-secondary hover:border-odoo-purple/50 hover:text-odoo-purple transition-colors"
          >
            Previsualizar
          </button>
        </div>

        {blocks.length === 0 ? (
          <div className="p-5">
            <p className="text-xs text-warning-dark italic">Aún no se han añadido bloques.</p>
          </div>
        ) : (
          <div className="flex-1 min-h-0 grid grid-cols-1 sm:grid-cols-[minmax(160px,200px)_1fr]">

            {/* Lista de bloques */}
            <div className="border-b sm:border-b-0 sm:border-r border-ui-border dark:border-ui-dark-border p-3 overflow-y-auto min-h-0">
              <p className="text-xs font-bold uppercase tracking-widest text-text-muted mb-2">
                Bloques ({blocks.length})
              </p>
              <div className="space-y-1">
                {blocks.map((block, i) => {
                  const cfg = BLOCK_UI_STATE_CONFIG[blockToUiState(block)];
                  const isSelected = selectedBlock?.id === block.id;
                  return (
                    <button
                      key={block.id}
                      type="button"
                      onClick={() => setSelectedBlock(block)}
                      className={[
                        'w-full text-left flex items-center gap-2 px-2.5 py-2 rounded-lg border transition-all',
                        isSelected
                          ? 'bg-odoo-purple/10 border-odoo-purple/30 dark:bg-odoo-dark-purple/15'
                          : 'bg-transparent border-ui-border dark:border-ui-dark-border/50 hover:bg-ui-body dark:hover:bg-ui-dark-bg hover:border-ui-border',
                      ].join(' ')}
                    >
                      <span className="shrink-0 text-xs font-bold text-text-muted w-4 text-right">{i + 1}</span>
                      <span className="flex-1 min-w-0 text-xs font-medium text-text-primary dark:text-text-dark-primary truncate">
                        {block.title || 'Sin nombre'}
                      </span>
                      <span className={`shrink-0 px-1.5 py-0.5 rounded text-xs font-bold uppercase ${cfg.badgeCls}`}>
                        {cfg.label}
                      </span>
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Panel de preview con tabs */}
            <div className="flex flex-col min-w-0 min-h-0">
              {/* Tabs */}
              <div className="flex border-b border-ui-border dark:border-ui-dark-border shrink-0">
                {(['Contenido', 'Descripción'] as PreviewTab[]).map((tab) => (
                  <button
                    key={tab}
                    type="button"
                    onClick={() => setActiveTab(tab)}
                    className={[
                      'px-4 py-2.5 text-xs border-b-2 -mb-px transition-all',
                      activeTab === tab
                        ? 'border-odoo-purple text-odoo-purple font-medium cursor-default'
                        : 'border-transparent text-text-muted hover:text-text-primary cursor-pointer',
                    ].join(' ')}
                  >
                    {tab}
                  </button>
                ))}
              </div>

              {/* Contenido del tab */}
              <div className="flex-1 min-h-0 p-4 overflow-y-auto">
                {(() => {
                  const content = activeTab === 'Descripción' ? selectedBlock?.description : selectedBlock?.default_content;
                  if (!content) return <span className="text-xs text-text-muted italic">{activeTab === 'Descripción' ? 'Sin descripción.' : 'Este bloque no tiene contenido.'}</span>;

                  let parsed: unknown[] | null = null;
                  if (Array.isArray(content)) {
                    if (content.length > 0) parsed = content;
                  } else if (typeof content === 'string') {
                    try {
                      const p = JSON.parse(content);
                      if (Array.isArray(p) && p.length > 0) parsed = p;
                    } catch { /* fallback to plain text */ }
                  }

                  if (parsed) return <BlockContentHtml content={parsed} />;
                  if (typeof content === 'string') return <p className="text-xs text-text-secondary dark:text-text-dark-secondary leading-relaxed">{content}</p>;

                  return <span className="text-xs text-text-muted italic">{activeTab === 'Descripción' ? 'Sin descripción.' : 'Este bloque no tiene contenido.'}</span>;
                })()}
              </div>
            </div>

          </div>
        )}
      </div>

      {showPreview && (
        <TemplatePreviewModal
          template={template}
          blocks={blocks}
          onClose={() => setShowPreview(false)}
        />
      )}

    </div>
  );
}
