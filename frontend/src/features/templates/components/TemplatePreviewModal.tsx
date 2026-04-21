import { useEffect } from 'react';
import { createPortal } from 'react-dom';
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
    <div className="fixed inset-0 overflow-y-auto bg-[#ddd9d3]" style={{ zIndex: 9999 }}>

      {/* Sticky nav bar */}
      <header className="sticky top-0 bg-white border-b border-gray-200 flex items-center gap-4 px-6 h-[52px]" style={{ zIndex: 100 }}>
        <button
          type="button"
          onClick={onClose}
          className="shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold text-gray-700 bg-gray-100 hover:bg-gray-200 transition-colors cursor-pointer"
        >
          ← Volver al resumen
        </button>
        <span className="flex-1 text-xs font-semibold text-gray-500 truncate">
          {template.name} — Previsualización
        </span>
      </header>

      {/* Paper */}
      <article
        className="mx-auto bg-white shadow-xl preview-content"
        style={{ maxWidth: '960px', minHeight: 'calc(100vh - 52px)', padding: '64px 80px' }}
      >

        {/* Document title */}
        <h1 className="text-3xl font-bold text-gray-900 pb-5 mb-8 border-b border-gray-200">
          {template.name}
        </h1>

        {/* Blocks */}
        {blocks.length === 0 ? (
          <p className="text-sm text-gray-400 italic">Esta plantilla no tiene bloques.</p>
        ) : (
          <div className="space-y-10">
            {blocks.map((block) => {
              const hasContent =
                Array.isArray(block.default_content) &&
                (block.default_content as unknown[]).length > 0;

              return (
                <section key={block.id}>
                  {hasContent ? (
                    <BlockContentHtml content={block.default_content as unknown[]} />
                  ) : (
                    <p className="text-sm text-gray-400 italic">
                      Este bloque no tiene contenido predeterminado.
                    </p>
                  )}
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
