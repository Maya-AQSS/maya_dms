import { useRef, useEffect } from 'react';
import { useCreateBlockNote, FormattingToolbar } from '@blocknote/react';
import { BlockNoteView } from '@blocknote/ariakit';
import { repairBlockNoteBlocks } from '../../../utils/blockNoteRepair';
import '@blocknote/ariakit/style.css';
import { normalizeBlockContentForEditor } from '../../documents/lib/normalizeBlockContent';

interface Props {
  initialContent: unknown;
  editable: boolean;
  isDark: boolean;
  onChange?: (content: unknown) => void;
}

const PANEL_STYLES_ID = 'maya-bn-panel-styles';
const PANEL_STYLES_VERSION = '9';
function ensurePanelStyles() {
  if (typeof document === 'undefined') return;
  const existing = document.getElementById(PANEL_STYLES_ID) as HTMLStyleElement | null;
  if (existing && existing.dataset.version === PANEL_STYLES_VERSION) return;
  const el = existing ?? document.createElement('style');
  el.id = PANEL_STYLES_ID;
  el.dataset.version = PANEL_STYLES_VERSION;
  el.textContent = `
    /* Toolbar: wrap on narrow widths, no horizontal scroll arrows */
    .maya-bn-panel .bn-toolbar,
    .maya-bn-panel .bn-ak-toolbar,
    .maya-bn-panel [role="toolbar"] {
      flex-wrap: wrap !important;
      overflow: visible !important;
      max-width: 100% !important;
      gap: 2px;
    }
    .maya-bn-panel .bn-ak-toolbar > .ariakit-toolbar-scroll-button,
    .maya-bn-panel .bn-ak-toolbar > [class*="scroll-button"],
    .maya-bn-panel [class*="ScrollArrow"] {
      display: none !important;
    }
    /* DOM real: .bn-root y .bn-container son el mismo elemento (al igual
       que .ProseMirror y .bn-editor). bn-root usa min-height:100% para
       llenar el wrapper cuando el contenido es corto, pero puede crecer y
       hacer scroll cuando el contenido excede; ProseMirror toma el resto
       del alto con flex:1. */
    .maya-bn-panel .bn-root {
      min-height: 100%;
      display: flex;
      flex-direction: column;
      cursor: text;
      box-sizing: border-box;
    }
    .maya-bn-panel .ProseMirror {
      flex: 1 1 auto;
      min-height: 0;
    }
    /* Bring editor typography up to the rest of the app (was a touch smaller). */
    .maya-bn-panel .ProseMirror,
    .maya-bn-panel .bn-block-content {
      font-size: 0.95rem;
      line-height: 1.6;
    }
    /* HEAD styles integrated */
    .bn-container, .bn-editor {
      background-color: transparent !important;
    }
    .bn-editor {
      padding-inline: 42px !important;
    }
    .dark .bn-editor {
      color: var(--color-text-dark-primary) !important;
    }
  `;
  if (!existing) document.head.appendChild(el);
}

export function BlockNoteEditorPanel({ initialContent, editable, isDark, onChange }: Props) {
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const containerRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => { ensurePanelStyles(); }, []);

  const normalized = normalizeBlockContentForEditor(initialContent);
  const safeContent =
    normalized.length > 0
      ? // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (normalized as any)
      : undefined;

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const editor = useCreateBlockNote({
    initialContent: safeContent ? repairBlockNoteBlocks(safeContent) : undefined,
  } as any);

  // Click anywhere in the empty editor area focuses the editor at the end.
  const handleAreaClick = (e: React.MouseEvent<HTMLDivElement>) => {
    if (!editable) return;
    const target = e.target as HTMLElement;
    if (target.closest('.bn-toolbar') || target.closest('[role="toolbar"]')) return;
    if (target.closest('.ProseMirror') !== null) return;
    editor.focus();
  };

  return (
    <div
      ref={containerRef}
      className="maya-bn-panel flex-1 flex flex-col min-h-0 bg-white dark:bg-ui-dark-card overflow-hidden"
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
            <div className="order-first sticky top-0 z-10 w-full border-b border-ui-border dark:border-ui-dark-border bg-ui-card/95 dark:bg-ui-dark-card/95 backdrop-blur-md px-2 py-1 shadow-sm">
              <FormattingToolbar />
            </div>
          )}
        </BlockNoteView>
      </div>
    </div>
  );
}
