import { useRef } from 'react';
import { useCreateBlockNote, FormattingToolbar } from '@blocknote/react';
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
}

export function BlockNoteEditorPanel({ initialContent, editable, isDark, onChange }: Props) {
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const containerRef = useRef<HTMLDivElement | null>(null);

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
