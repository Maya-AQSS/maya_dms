import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { Template } from '../../../types/templates';
import type { TemplateBlock } from '../../../types/blocks';
import { fetchProcesses } from '../../../api/processes';
import { visibilityLabel } from '../constants';
import { normalizeBlockContentForEditor } from '../../documents/lib/normalizeBlockContent';
import { PaperPreviewLayout } from '../../documents/components/PaperPreviewLayout';
import { formatCalendarDateForBrowser } from '../../../utils/formatCalendarDate';
import { ViewCardHeader } from './BlockCommentsCard';
import { BlockContentHtml } from './BlockContentHtml';

interface Props {
  template: Template;
  blocks: TemplateBlock[];
  onClose: () => void;
}

/**
 * Previsualización de plantilla — usa el mismo `PaperPreviewLayout` y
 * `PaperBlocksArticle` que `DocumentPreviewPage` para garantizar paridad
 * visual exacta. Se monta como overlay (`asOverlay`) porque se abre desde el
 * wizard sin cambiar de ruta.
 */
export function TemplatePreviewModal({ template, blocks, onClose }: Props) {
  const { t } = useTranslation('templates');
  const [processLabel, setProcessLabel] = useState<string | null>(null);
  const [activeView, setActiveView] = useState<{ blockId: string; mode: 'comments' | 'info' } | null>(null);

  useEffect(() => {
    if (!template.process_id) {
      setProcessLabel(null);
      return;
    }
    let cancelled = false;
    void fetchProcesses()
      .then((res) => {
        if (cancelled) return;
        const process = res.data.find((p) => p.id === template.process_id) ?? null;
        if (!process) {
          setProcessLabel(null);
          return;
        }
        setProcessLabel(`Proceso: ${process.code} — ${process.name}`);
      })
      .catch(() => {
        if (!cancelled) setProcessLabel(null);
      });
    return () => {
      cancelled = true;
    };
  }, [template.process_id]);

  const headerMetaInfo = (
    <p className="text-xs text-text-muted dark:text-text-dark-muted text-center">
      {template.author_name ?? 'Autor desconocido'}
      {' · '}
      {visibilityLabel(template.visibility_level)}
      {' · '}
      {blocks.length} {blocks.length === 1 ? 'bloque' : 'bloques'}
      {template.delivery_deadline ? (
        <>{' · '}Fecha límite: {formatCalendarDateForBrowser(template.delivery_deadline)}</>
      ) : null}
    </p>
  );

  const headerActions = (
    <>
      <span className="text-xs font-medium px-2 py-0.5 rounded-full bg-warning-light text-warning-dark dark:bg-warning-dark/30 dark:text-warning-light">
        Vista previa
      </span>
      {template.version != null && (
        <span className="text-xs font-mono bg-ui-body dark:bg-ui-dark-bg border border-ui-border dark:border-ui-dark-border px-2 py-0.5 rounded-full text-text-secondary dark:text-text-dark-secondary">
          v{template.version}
        </span>
      )}
    </>
  );

  return (
    <PaperPreviewLayout
      title={template.name}
      subtitle={processLabel}
      onBack={onClose}
      backLabel="Cerrar previsualización"
      viewMode="default"
      metaInfo={headerMetaInfo}
      actions={headerActions}
      asOverlay
      sidebar={activeView && (() => {
        const block = blocks.find(b => b.id === activeView.blockId);
        if (!block) return null;
        return (
          <div className="bg-ui-card dark:bg-ui-dark-card shadow-xl rounded-sm flex flex-col overflow-hidden h-full animate-in fade-in slide-in-from-right-4 duration-300">
            <ViewCardHeader
              blockSortOrder={(blocks.findIndex(b => b.id === block.id) + 1) || '?'}
              title={t('review.blockDescriptionTitle')}
              onClose={() => setActiveView(null)}
            />
            <div className="flex-1 overflow-y-auto" style={{ padding: '40px 60px' }}>
              {block.description ? (
                <BlockContentHtml content={normalizeBlockContentForEditor(block.description)} />
              ) : (
                <p className="text-sm text-text-muted italic">Este bloque no tiene descripción.</p>
              )}
            </div>
          </div>
        );
      })()}
    >
      <div className="space-y-10">
        {blocks.length === 0 ? (
          <p className="text-sm text-text-muted dark:text-text-dark-muted italic">
            Esta plantilla no tiene bloques.
          </p>
        ) : (
          blocks.map((block) => {
            const isSelected = activeView?.blockId === block.id;
            const infoActive = isSelected && activeView?.mode === 'info';
            const nodes = normalizeBlockContentForEditor(block.default_content);
            const hasDescription = !!block.description;

            const btnBase = 'shrink-0 px-3 py-1.5 rounded-full border flex items-center gap-1.5 transition-all cursor-pointer text-xs font-black uppercase tracking-wider';
            const btnActive = 'border-odoo-purple text-odoo-purple bg-odoo-purple/10 shadow-sm';
            const btnIdle = 'border-ui-border dark:border-ui-dark-border text-text-muted bg-ui-body/30 hover:text-odoo-purple hover:border-odoo-purple/50 hover:bg-odoo-purple/5';

            return (
              <section
                key={block.id}
                className={[
                  'relative group rounded-lg transition-all duration-200 cursor-pointer',
                  isSelected
                    ? 'ring-2 ring-odoo-purple ring-offset-8 dark:ring-offset-ui-dark-card shadow-sm'
                    : 'hover:ring-1 hover:ring-ui-border dark:hover:ring-ui-dark-border hover:ring-offset-4 dark:hover:ring-offset-ui-dark-card',
                ].join(' ')}
                onClick={() => {
                  if (hasDescription) setActiveView(infoActive ? null : { blockId: block.id, mode: 'info' });
                }}
              >
                <div className="flex items-center gap-3 mb-4">
                  <h4 className="flex-1 text-sm font-bold text-text-secondary dark:text-text-dark-secondary">
                    {block.title || 'Bloque sin título'}
                  </h4>
                  <div className="flex items-center gap-2">
                    {hasDescription && (
                      <button
                        type="button"
                        onClick={(e) => {
                          e.stopPropagation();
                          setActiveView(infoActive ? null : { blockId: block.id, mode: 'info' });
                        }}
                        className={`${btnBase} ${infoActive ? btnActive : btnIdle}`}
                      >
                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Info</span>
                      </button>
                    )}
                  </div>
                </div>
                {nodes.length > 0 ? (
                  <BlockContentHtml content={nodes} />
                ) : (
                  <p className="text-sm text-text-muted dark:text-text-dark-muted italic">
                    Sin contenido.
                  </p>
                )}
              </section>
            );
          })
        )}
      </div>
    </PaperPreviewLayout>
  );
}
