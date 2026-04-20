import { lazy, Suspense, useEffect } from 'react';
import { useDarkMode } from '../../../hooks/useDarkMode';
import type { TemplateBlock } from '../../../types/blocks';
import type { Template } from '../../../types/templates';
import { BLOCK_UI_STATE_CONFIG, blockToUiState } from '../blockUiState';

const BlockNoteEditorPanel = lazy(() => import('./BlockNoteEditorPanel'));

type Props = {
  template: Template;
  blocks: TemplateBlock[];
  onClose: () => void;
};

export function TemplatePreviewModal({ template, blocks, onClose }: Props) {
  const { isDark } = useDarkMode();

  useEffect(() => {
    const handler = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [onClose]);

  return (
    <div
      className="fixed inset-0 z-50 flex items-start justify-center bg-black/60 overflow-y-auto py-10 px-4"
      onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}
    >
      <div className="bg-white dark:bg-ui-dark-card rounded-xl shadow-2xl w-full max-w-3xl animate-in fade-in zoom-in-95">

        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-ui-border dark:border-ui-dark-border sticky top-0 bg-white dark:bg-ui-dark-card z-10">
          <div>
            <p className="text-[10px] font-bold uppercase tracking-widest text-text-muted mb-0.5">Previsualización</p>
            <h2 className="text-base font-bold text-text-primary dark:text-text-dark-primary">{template.name}</h2>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="w-8 h-8 flex items-center justify-center rounded-full hover:bg-ui-body dark:hover:bg-ui-dark-bg text-text-muted hover:text-text-primary transition-colors text-sm"
          >
            ✕
          </button>
        </div>

        {/* Blocks */}
        <div className="divide-y divide-ui-border dark:divide-ui-dark-border">
          {blocks.length === 0 ? (
            <div className="px-6 py-10 text-center">
              <p className="text-sm text-text-muted italic">Esta plantilla no tiene bloques.</p>
            </div>
          ) : (
            blocks.map((block, i) => {
              const cfg = BLOCK_UI_STATE_CONFIG[blockToUiState(block)];
              const hasContent =
                Array.isArray(block.default_content) &&
                (block.default_content as unknown[]).length > 0;
              return (
                <div key={block.id} className="px-6 py-5">
                  <div className="flex items-center gap-3 mb-3">
                    <span className="shrink-0 w-6 h-6 rounded-full bg-odoo-purple/10 text-odoo-purple text-[10px] font-bold flex items-center justify-center">
                      {i + 1}
                    </span>
                    <h3 className="flex-1 text-sm font-bold text-text-primary dark:text-text-dark-primary">
                      {block.title || 'Bloque sin nombre'}
                    </h3>
                    <span className={`px-1.5 py-0.5 rounded text-[10px] font-bold uppercase ${cfg.badgeCls}`}>
                      {cfg.label}
                    </span>
                  </div>

                  {hasContent ? (
                    <div className="rounded-lg border border-ui-border dark:border-ui-dark-border overflow-hidden" style={{ minHeight: '80px' }}>
                      <Suspense fallback={<div className="p-4 text-xs text-text-muted">Cargando…</div>}>
                        <BlockNoteEditorPanel
                          key={block.id}
                          initialContent={block.default_content}
                          editable={false}
                          isDark={isDark}
                          onChange={() => {}}
                        />
                      </Suspense>
                    </div>
                  ) : (
                    <div className="rounded-lg border-2 border-dashed border-ui-border dark:border-ui-dark-border px-4 py-6 text-center">
                      <p className="text-xs text-text-muted italic">Este bloque no tiene contenido.</p>
                    </div>
                  )}
                </div>
              );
            })
          )}
        </div>

        {/* Footer */}
        <div className="px-6 py-4 border-t border-ui-border dark:border-ui-dark-border flex justify-end sticky bottom-0 bg-white dark:bg-ui-dark-card">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 text-xs font-bold rounded-lg border border-ui-border dark:border-ui-dark-border text-text-secondary hover:border-odoo-purple/50 hover:text-odoo-purple transition-colors"
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>
  );
}
