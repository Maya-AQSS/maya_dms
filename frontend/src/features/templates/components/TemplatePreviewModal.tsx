import { useEffect } from 'react';
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

  return (
    <>
      {/* Overlay */}
      <div className="fixed inset-0 z-50 overflow-y-auto bg-[#ddd9d3]">

        {/* Sticky close bar */}
        <div className="sticky top-0 z-10 bg-[#ddd9d3]/95 backdrop-blur-sm border-b border-black/10 flex items-center justify-between px-6 py-2.5">
          <button
            type="button"
            onClick={onClose}
            className="shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold text-gray-600 hover:bg-black/10 transition-colors"
          >
            ← Volver al resumen
          </button>
          <span className="text-xs font-semibold text-gray-600 truncate px-4">{template.name}</span>
          <button
            type="button"
            onClick={onClose}
            className="shrink-0 w-7 h-7 flex items-center justify-center rounded-full text-gray-500 hover:bg-black/10 transition-colors text-sm"
            aria-label="Cerrar"
          >
            ✕
          </button>
        </div>

        {/* Paper */}
        <div className="max-w-200 mx-auto my-8 mb-16 bg-white rounded shadow-xl" style={{ padding: '48px 64px' }}>

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
                    {/* Block content */}
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
        </div>
      </div>
    </>
  );
}
