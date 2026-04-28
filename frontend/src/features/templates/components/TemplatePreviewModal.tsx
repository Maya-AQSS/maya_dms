import { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { Button } from '../../../ui';
import { BlockContentHtml } from './BlockContentHtml';
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
    <div className="fixed inset-0 overflow-y-auto bg-ui-preview-bg" style={{ zIndex: 9999 }}>

      {/* Sticky nav bar */}
      <header className="sticky top-0 bg-ui-card border-b border-ui-border flex items-center gap-4 px-6 h-[52px]" style={{ zIndex: 100 }}>
        <Button variant="secondary" size="xs" onClick={onClose} className="shrink-0">
          ← Volver al resumen
        </Button>
        <span className="flex-1 text-xs font-semibold text-text-muted truncate">
          {template.name} — Previsualización
        </span>
      </header>

      {/* Paper */}
      <article
        className="mx-auto bg-ui-card shadow-xl preview-content"
        style={{ maxWidth: '960px', minHeight: 'calc(100vh - 52px)', padding: '64px 80px' }}
      >

        {/* Document title */}
        <h1 className="text-3xl font-bold text-text-primary pb-5 mb-8 border-b border-ui-border">
          {template.name}
        </h1>

        {/* Blocks */}
        {blocks.length === 0 ? (
          <p className="text-sm text-text-muted italic">Esta plantilla no tiene bloques.</p>
        ) : (
          <div className="space-y-10">
            {blocks.map((block) => {
              return (
                <section key={block.id}>
                  {(() => {
                    const content = block.default_content;
                    if (!content) return <p className="text-sm text-text-muted italic">Este bloque no tiene contenido predeterminado.</p>;
                    
                    let parsed: unknown[] | null = null;
                    if (Array.isArray(content)) {
                      if (content.length > 0) parsed = content;
                    } else if (typeof content === 'string') {
                      try {
                        const p = JSON.parse(content);
                        if (Array.isArray(p) && p.length > 0) parsed = p;
                      } catch { /* fallback */ }
                    }

                    if (parsed) return <BlockContentHtml content={parsed} />;
                    if (typeof content === 'string') return <p className="text-sm text-text-secondary leading-relaxed">{content}</p>;
                    
                    return <p className="text-sm text-text-muted italic">Este bloque no tiene contenido predeterminado.</p>;
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
