import { useEffect, useState } from 'react';
import { BlockNoteEditor } from '@blocknote/core';
import type { PartialBlock } from '@blocknote/core';
import type { TemplateBlock } from '../../../types/blocks';
import type { Template } from '../../../types/templates';

// Headless editor singleton — created on first use, no DOM attachment.
let _headlessEditor: BlockNoteEditor | null = null;
function getHeadlessEditor(): BlockNoteEditor {
  if (!_headlessEditor) _headlessEditor = BlockNoteEditor.create();
  return _headlessEditor;
}

// ── Block content → clean HTML ────────────────────────────────────────────────

function BlockContentHtml({ content }: { content: unknown[] }) {
  const [html, setHtml] = useState<string | null>(null);

  useEffect(() => {
    getHeadlessEditor()
      .blocksToHTMLLossy(content as PartialBlock[])
      .then(setHtml)
      .catch(() => setHtml('<p><em>Error al renderizar el contenido.</em></p>'));
  }, [content]);

  if (html === null) {
    return <div className="h-6 bg-gray-100 animate-pulse rounded" />;
  }

  return (
    <div
      className="bn-doc-content"
      dangerouslySetInnerHTML={{ __html: html }}
    />
  );
}

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
      {/* Document preview styles scoped to .bn-doc-content */}
      <style>{`
        .bn-doc-content { font-family: system-ui, -apple-system, sans-serif; font-size: 15px; line-height: 1.75; color: #1a1a1a; }
        .bn-doc-content h1 { font-size: 1.75em; font-weight: 700; margin: 1em 0 0.35em; line-height: 1.25; }
        .bn-doc-content h2 { font-size: 1.35em; font-weight: 600; margin: 1em 0 0.3em; line-height: 1.3; }
        .bn-doc-content h3 { font-size: 1.1em; font-weight: 600; margin: 0.9em 0 0.25em; }
        .bn-doc-content p { margin: 0.5em 0; }
        .bn-doc-content ul, .bn-doc-content ol { padding-left: 1.5em; margin: 0.5em 0; }
        .bn-doc-content li { margin: 0.2em 0; }
        .bn-doc-content strong { font-weight: 700; }
        .bn-doc-content em { font-style: italic; }
        .bn-doc-content u { text-decoration: underline; }
        .bn-doc-content s { text-decoration: line-through; }
        .bn-doc-content a { color: #6941c6; text-decoration: underline; }
        .bn-doc-content code { font-family: monospace; background: #f3f4f6; padding: 0.1em 0.35em; border-radius: 3px; font-size: 0.88em; }
        .bn-doc-content pre { background: #f3f4f6; padding: 1em 1.25em; border-radius: 6px; overflow-x: auto; margin: 0.75em 0; }
        .bn-doc-content blockquote { border-left: 3px solid #d1d5db; margin: 0.75em 0; padding-left: 1em; color: #6b7280; }
        .bn-doc-content table { border-collapse: collapse; width: 100%; margin: 1em 0; font-size: 0.9em; }
        .bn-doc-content th { background: #f9fafb; font-weight: 600; text-align: left; }
        .bn-doc-content th, .bn-doc-content td { border: 1px solid #e5e7eb; padding: 0.5em 0.75em; }
        .bn-doc-content tr:nth-child(even) td { background: #fafafa; }
        .bn-doc-content img { max-width: 100%; height: auto; margin: 0.75em auto; display: block; border-radius: 4px; }
      `}</style>

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
              {blocks.map((block, i) => {
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
