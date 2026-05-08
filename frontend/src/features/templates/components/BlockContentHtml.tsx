import { useMemo } from 'react';
import DOMPurify from 'dompurify';
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
    .bn-doc-content { font-family: system-ui, -apple-system, sans-serif; font-size: 16px; line-height: 1.8; color: var(--color-text-primary); }
    .dark .bn-doc-content { color: var(--color-text-dark-primary); }
    
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
    
    .bn-doc-content a { color: var(--color-odoo-purple); text-decoration: underline; }
    .dark .bn-doc-content a { color: var(--color-odoo-dark-purple); }
    
    .bn-doc-content code { font-family: monospace; background: var(--color-ui-body); padding: 0.1em 0.35em; border-radius: 3px; font-size: 0.88em; }
    .dark .bn-doc-content code { background: var(--color-ui-dark-border); }
    
    .bn-doc-content pre { background: var(--color-ui-body); padding: 1em 1.25em; border-radius: 6px; overflow-x: auto; margin: 0.75em 0; }
    .dark .bn-doc-content pre { background: var(--color-ui-dark-border); }
    
    .bn-doc-content blockquote { border-left: 3px solid var(--color-ui-border); margin: 0.75em 0; padding-left: 1em; color: var(--color-text-secondary); }
    .dark .bn-doc-content blockquote { border-left-color: var(--color-ui-dark-border); color: var(--color-text-dark-secondary); }
    
    .bn-doc-content table { border-collapse: collapse; width: 100%; margin: 1em 0; font-size: 0.9em; }
    .bn-doc-content th { background: var(--color-ui-body); font-weight: 600; text-align: left; }
    .dark .bn-doc-content th { background: var(--color-ui-dark-border); }
    
    .bn-doc-content th, .bn-doc-content td { border: 1px solid var(--color-ui-border); padding: 0.5em 0.75em; }
    .dark .bn-doc-content th, .dark .bn-doc-content td { border-color: var(--color-ui-dark-border); }
    
    .bn-doc-content tr:nth-child(even) td { background: var(--color-ui-body); }
    .dark .bn-doc-content tr:nth-child(even) td { background: var(--color-ui-dark-border-l); }
    
    .bn-doc-content img { max-width: 100%; height: auto; margin: 0.75em auto; display: block; border-radius: 4px; }
    .preview-content ul { list-style: disc; padding-left: 1.5em; margin: 0.5em 0; }
    .preview-content ol { list-style: decimal; padding-left: 1.5em; margin: 0.5em 0; }
    .preview-content li { margin: 0.2em 0; display: list-item; }
    .dark .bn-doc-content { color: var(--color-text-dark-primary); }
    .dark .bn-doc-content a { color: var(--color-text-dark-link); }
    .dark .bn-doc-content code { background: var(--color-ui-dark-bg); }
    .dark .bn-doc-content pre { background: var(--color-ui-dark-bg); }
    .dark .bn-doc-content blockquote { border-left-color: var(--color-ui-dark-border); color: var(--color-text-dark-secondary); }
    .dark .bn-doc-content th { background: var(--color-ui-dark-bg); }
    .dark .bn-doc-content th, .dark .bn-doc-content td { border-color: var(--color-ui-dark-border); }
    .dark .bn-doc-content tr:nth-child(even) td { background: var(--color-ui-dark-bg); }
  `;
  document.head.appendChild(el);
}

/**
 * Converts BlockNote JSON content to clean HTML and renders it.
 * Uses a headless BlockNoteEditor (no React component mounted).
 */
export function BlockContentHtml({ content }: { content: unknown[] }) {
  const html = useMemo(() => {
    ensureStyles();
    const repaired = repairBlockNoteBlocks(Array.isArray(content) ? content : []);
    const isEmpty =
      repaired.length === 0 ||
      repaired.every(
        (b: any) =>
          !Array.isArray(b.content) ||
          b.content.length === 0 ||
          b.content.every((c: any) => typeof c.text !== 'string' || !c.text.trim()),
      );
    if (isEmpty) return '';
    try {
      const raw = getHeadlessEditor().blocksToHTMLLossy(repaired as PartialBlock[]);
      return DOMPurify.sanitize(raw, {
        // Allow safe URL schemes. data:image/* is required for base64-pasted images
        // when no server upload handler is configured in BlockNote.
        ALLOWED_URI_REGEXP: /^(https?|mailto|tel):|^data:image\//i,
      });
    } catch {
      return '<p><em>Error al renderizar el contenido.</em></p>';
    }
  }, [content]);

  if (!html) {
    return null;
  }

  return (
    <div
      className="bn-doc-content"
      dangerouslySetInnerHTML={{ __html: html }}
    />
  );
}
