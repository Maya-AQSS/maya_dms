import { useEffect, useState } from 'react';
import { BlockNoteEditor } from '@blocknote/core';
import type { PartialBlock } from '@blocknote/core';
import { repairBlockNoteBlocks } from '../../../utils/blockNoteRepair';

// Headless editor singleton — no DOM attachment, used only for HTML conversion.
let _headlessEditor: BlockNoteEditor | null = null;
function getHeadlessEditor(): BlockNoteEditor {
  if (!_headlessEditor) _headlessEditor = BlockNoteEditor.create();
  return _headlessEditor;
}

// Inject document typography styles once into <head>.
let _stylesInjected = false;
function ensureStyles() {
  if (_stylesInjected || typeof document === 'undefined') return;
  _stylesInjected = true;
  const el = document.createElement('style');
  el.dataset.id = 'bn-doc-content-styles';
  el.textContent = `
    .bn-doc-content { font-family: system-ui, -apple-system, sans-serif; font-size: 16px; line-height: 1.8; color: #1a1a1a; }
    .bn-doc-content h1 { font-size: 2rem; font-weight: 600; margin: 1em 0 0.35em; line-height: 1.25; }
    .bn-doc-content h2 { font-size: 1.5rem; font-weight: 600; margin: 1em 0 0.3em; line-height: 1.3; }
    .bn-doc-content h3 { font-size: 1.25rem; font-weight: 600; margin: 0.9em 0 0.25em; }
    .bn-doc-content p { margin: 0.5em 0; }
    .bn-doc-content ul { list-style: disc; padding-left: 1.5em; margin: 0.5em 0; }
    .bn-doc-content ol { list-style: decimal; padding-left: 1.5em; margin: 0.5em 0; }
    .bn-doc-content ul ul { list-style: circle; }
    .bn-doc-content ul ul ul { list-style: square; }
    .bn-doc-content li { margin: 0.2em 0; display: list-item; }
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
    .preview-content ul { list-style: disc; padding-left: 1.5em; margin: 0.5em 0; }
    .preview-content ol { list-style: decimal; padding-left: 1.5em; margin: 0.5em 0; }
    .preview-content li { margin: 0.2em 0; display: list-item; }
  `;
  document.head.appendChild(el);
}

/**
 * Converts BlockNote JSON content to clean HTML and renders it.
 * Uses a headless BlockNoteEditor (no React component mounted).
 */
export function BlockContentHtml({ content }: { content: unknown[] }) {
  const [html, setHtml] = useState<string | null>(null);

  useEffect(() => {
    ensureStyles();
    getHeadlessEditor()
      .blocksToHTMLLossy(repairBlockNoteBlocks(content) as PartialBlock[])
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
