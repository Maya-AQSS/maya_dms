import { useEffect, useRef, useState } from 'react';
import { BlockNoteEditor } from '@blocknote/core';
import { FormattingToolbar } from '@blocknote/react';
import { BlockNoteView } from '@blocknote/ariakit';
import { repairBlockNoteBlocks } from '../../../utils/blockNoteRepair';
import '@blocknote/ariakit/style.css';
import '../styles/blocknote-panel.css';
import { normalizeBlockContentForEditor } from '../../documents/lib/normalizeBlockContent';

interface Props {
  initialContent: unknown;
  editable: boolean;
  isDark: boolean;
  onChange?: (content: unknown) => void;
  onFullscreenChange?: (isFullscreen: boolean) => void;
}

function FullscreenIcon({ expanded }: { expanded: boolean }) {
  return expanded ? (
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M8 3v3a2 2 0 0 1-2 2H3" />
      <path d="M21 8h-3a2 2 0 0 1-2-2V3" />
      <path d="M3 16h3a2 2 0 0 1 2 2v3" />
      <path d="M16 21v-3a2 2 0 0 1 2-2h3" />
    </svg>
  ) : (
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M8 3H5a2 2 0 0 0-2 2v3" />
      <path d="M21 8V5a2 2 0 0 0-2-2h-3" />
      <path d="M3 16v3a2 2 0 0 0 2 2h3" />
      <path d="M16 21h3a2 2 0 0 0 2-2v-3" />
    </svg>
  );
}

function cleanHtmlForPaste(html: string): string {
  // Replace newlines and multiple spaces between tags with a single space.
  // This prevents ProseMirror from collapsing newlines between inline tags into "nothing".
  return html
    .replace(/\r?\n/g, ' ')
    .replace(/>\s+</g, '> <')
    .replace(/\s+/g, ' ');
}

export function BlockNoteEditorPanel({ initialContent, editable, isDark, onChange, onFullscreenChange }: Props) {
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const containerRef = useRef<HTMLDivElement | null>(null);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const onFullscreenChangeRef = useRef(onFullscreenChange);
  onFullscreenChangeRef.current = onFullscreenChange;

  const applyFullscreen = (v: boolean) => {
    setIsFullscreen(v);
    onFullscreenChangeRef.current?.(v);
  };

  useEffect(() => {
    if (!isFullscreen) return;
    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') applyFullscreen(false);
    };
    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [isFullscreen]); // eslint-disable-line react-hooks/exhaustive-deps

  const normalized = normalizeBlockContentForEditor(initialContent);
  const safeContent =
    normalized.length > 0
      ? // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (normalized as any)
      : undefined;

  // Use a ref for stable editor identity across React StrictMode's double-mount cycle.
  // useCreateBlockNote registers ProseMirror clipboard handlers on mount; without this
  // guard StrictMode's mount→cleanup→remount leaves two handlers → paste fires twice.
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const editorRef = useRef<any>(null);
  if (!editorRef.current) {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    editorRef.current = (BlockNoteEditor as any).create({
      initialContent: safeContent ? repairBlockNoteBlocks(safeContent) : undefined,
    });
  }
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const editor = editorRef.current as any;

  useEffect(() => {
    const dom = (editor as any)._tiptapEditor?.view?.dom as HTMLElement | undefined;
    if (!dom) return;

    const handlePaste = (e: ClipboardEvent) => {
      const plain = e.clipboardData?.getData('text/plain') ?? '';
      const html = e.clipboardData?.getData('text/html') ?? '';

      // Empty paste — nothing to do.
      if (!plain.trim() && !html.trim()) return;

      let parsedBlocks: any[] | undefined;
      let handled = false;

      // 1. Prioritize HTML if it looks like rich content (has tags).
      // We clean it to ensure spaces between tags are preserved.
      if (html && html.includes('<')) {
        const cleaned = cleanHtmlForPaste(html);
        try {
          parsedBlocks = (editor as any).tryParseHTMLToBlocks(cleaned);
          if (parsedBlocks && parsedBlocks.length > 0) {
            handled = true;
          }
        } catch (err) {
          console.warn('Failed to parse pasted HTML:', err);
        }
      }

      // 2. Fallback to plain text if HTML failed or wasn't provided.
      if (!handled && plain) {
        try {
          parsedBlocks = (editor as any).tryParseMarkdownToBlocks(plain);
          if (parsedBlocks && parsedBlocks.length > 0) {
            handled = true;
          }
        } catch (err) {
          console.warn('Failed to parse pasted text/markdown:', err);
        }
      }

      if (handled && parsedBlocks) {
        // Intercepted and parsed — insert blocks at current selection.
        const currentBlocks = editor.document;
        const selection = editor.getTextCursorPosition();
        const blockId = selection.block.id;
        const blockIdx = currentBlocks.findIndex((b: any) => b.id === blockId);

        if (blockIdx !== -1) {
          editor.insertBlocks(parsedBlocks as any[], blockId, 'after');
          // If the current block was empty, remove it so
          // the pasted content lands in-place instead of after an empty line.
          const currentBlock = currentBlocks[blockIdx];
          const isEmpty =
            currentBlock.content.length === 0 ||
            (currentBlock.content.length === 1 &&
              currentBlock.content[0].type === 'text' &&
              !currentBlock.content[0].text);

          if (isEmpty) {
            editor.removeBlocks([blockId]);
          }
        } else {
          editor.insertBlocks(parsedBlocks as any[], currentBlocks[currentBlocks.length - 1].id, 'after');
        }

        // Trigger onChange so autosave picks up the pasted content.
        onChange?.(editor.document);

        e.preventDefault();
        e.stopPropagation();
      }
    };

    // Use capture phase to ensure we intercept before the default handler.
    dom.addEventListener('paste', handlePaste, true);
    return () => dom.removeEventListener('paste', handlePaste, true);
  }, [editor, onChange]);

  // Click anywhere in the empty editor area focuses the editor at the end.
  const handleAreaClick = (e: React.MouseEvent<HTMLDivElement>) => {
    if (!editable) return;
    const target = e.target as HTMLElement;
    if (target.closest('.bn-toolbar') || target.closest('[role="toolbar"]')) return;
    if (target.closest('.bn-fullscreen-btn') !== null) return;
    if (target.closest('.ProseMirror') !== null) return;
    editor.focus();
  };

  const containerCls = isFullscreen
    ? 'maya-bn-panel maya-bn-panel--fullscreen flex-1 flex flex-col min-h-0 bg-white dark:bg-ui-dark-card overflow-hidden'
    : 'maya-bn-panel flex-1 flex flex-col min-h-0 bg-white dark:bg-ui-dark-card overflow-hidden';

  return (
    <div
      ref={containerRef}
      className={containerCls}
      onClick={handleAreaClick}
    >
      {!editable && (
        <div className="mx-4 mt-3 px-3 py-2 text-xs text-text-muted dark:text-text-dark-muted bg-ui-body dark:bg-ui-dark-bg rounded border border-ui-border dark:border-ui-dark-border shrink-0">
          Este bloque está bloqueado y no puede editarse.
        </div>
      )}
      <div className="flex-1 min-h-0 relative overflow-y-auto">
        <BlockNoteView
          editor={editor as any}
          editable={editable}
          theme={isDark ? 'dark' : 'light'}
          formattingToolbar={false}
          onChange={() => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
            debounceRef.current = setTimeout(() => {
              onChange?.(editor.document);
            }, 200);
          }}
        >
          {editable && (
            <div className="order-first sticky top-0 z-10 w-full border-b border-ui-border dark:border-ui-dark-border bg-ui-card/95 dark:bg-ui-dark-card/95 backdrop-blur-md px-2 py-1 shadow-sm flex items-center gap-1">
              <div className="flex-1 min-w-0">
                <FormattingToolbar />
              </div>
              <button
                type="button"
                aria-label={isFullscreen ? 'Salir de pantalla completa' : 'Pantalla completa'}
                aria-pressed={isFullscreen}
                onClick={(e) => { e.stopPropagation(); applyFullscreen(!isFullscreen); }}
                className="bn-fullscreen-btn shrink-0 p-1.5 rounded text-text-muted hover:text-text-primary hover:bg-ui-body dark:hover:bg-ui-dark-border transition-colors focus:outline-none"
              >
                <FullscreenIcon expanded={isFullscreen} />
              </button>
            </div>
          )}
        </BlockNoteView>
      </div>
    </div>
  );
}
