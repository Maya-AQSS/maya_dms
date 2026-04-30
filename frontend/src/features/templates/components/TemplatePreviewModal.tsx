import { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { BlockContentHtml } from './BlockContentHtml';
import { normalizeBlockContentForEditor } from '../../documents/lib/normalizeBlockContent';
import type { TemplateBlock } from '../../../types/blocks';
import type { Template } from '../../../types/templates';

// ── Types ─────────────────────────────────────────────────────────────────────

type Props = {
  template: Template;
  blocks: TemplateBlock[];
  onClose: () => void;
};

// ── Modal ─────────────────────────────────────────────────────────────────────

export function TemplatePreviewModal({ template, blocks, onClose }: Props) {
  useEffect(() => {
    const handler = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [onClose]);

  return createPortal(
    <div className="fixed inset-0 overflow-y-auto bg-white dark:bg-ui-dark-bg opacity-100" style={{ zIndex: 9999 }}>

      {/* Sticky nav bar */}
      <header className="sticky top-0 bg-ui-card dark:bg-ui-dark-card border-b border-ui-border dark:border-ui-dark-border flex items-center gap-4 px-6 h-[52px]" style={{ zIndex: 100 }}>
        <button
          type="button"
          onClick={onClose}
          className="shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold text-text-secondary dark:text-text-dark-secondary bg-ui-body dark:bg-ui-dark-bg hover:bg-ui-border dark:hover:bg-ui-dark-border transition-colors cursor-pointer"
        >
          ← Volver al resumen
        </button>
        <span className="flex-1 text-xs font-semibold text-text-muted dark:text-text-dark-muted truncate">
          {template.name} — Previsualización
        </span>
      </header>

      {/* Paper */}
      <article
        className="mx-auto bg-white dark:bg-ui-dark-card shadow-xl preview-content"
        style={{ 
          maxWidth: '760px', 
          margin: '32px auto',
          minHeight: 'calc(100vh - 116px)', 
          padding: '56px 72px' 
        }}
      >

        {/* Document title */}
        <h1 className="text-2xl font-bold text-text-primary dark:text-text-dark-primary pb-4 mb-6 border-b border-ui-border dark:border-ui-dark-border">
          {template.name}
        </h1>

        {/* Blocks */}
        {blocks.length === 0 ? (
          <p className="text-sm text-text-muted dark:text-text-dark-muted italic">Esta plantilla no tiene bloques.</p>
        ) : (
          <div className="space-y-10">
            {blocks.map((block) => {
              const isLocked = block.block_state === 'locked';
              
              return (
                <section 
                  key={block.id}
                  style={isLocked ? { opacity: 0.45, pointerEvents: 'none' } : undefined}
                >
                  <div className="flex flex-wrap items-baseline gap-2 mb-2">
                    {block.title && (
                      <h4 className="text-sm font-bold text-text-secondary dark:text-text-dark-secondary">
                        {block.title}
                      </h4>
                    )}
                    {block.mandatory && (
                      <span className="text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                        Obligatorio
                      </span>
                    )}
                    {isLocked && (
                      <span className="text-[10px] font-medium uppercase tracking-wide px-1.5 py-0.5 rounded bg-ui-border/60 dark:bg-ui-dark-border text-text-muted dark:text-text-dark-muted">
                        Bloqueado
                      </span>
                    )}
                  </div>

                  {(() => {
                    const nodes = normalizeBlockContentForEditor(block.default_content);
                    if (nodes.length > 0) return <BlockContentHtml content={nodes} />;
                    
                    if (typeof block.default_content === 'string' && block.default_content.trim()) {
                      return <p className="text-sm text-text-secondary dark:text-text-dark-secondary leading-relaxed">{block.default_content}</p>;
                    }
                    
                    return <p className="text-sm text-text-muted dark:text-text-dark-muted italic">Sin contenido.</p>;
                  })()}
                </section>
              );
            })}
          </div>
        )}
      </article>
    </div>,
    document.body,
  );
}
