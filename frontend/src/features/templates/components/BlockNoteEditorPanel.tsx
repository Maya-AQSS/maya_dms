import { useRef } from 'react';
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

export default function BlockNoteEditorPanel({ initialContent, editable, isDark, onChange }: Props) {
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

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

  return (
    <div className="flex-1 flex flex-col overflow-y-auto min-h-0 bg-white dark:bg-ui-dark-card">
      {!editable && (
        <div className="mx-4 mt-3 px-3 py-2 text-xs text-text-muted dark:text-text-dark-muted bg-ui-body dark:bg-ui-dark-bg rounded border border-ui-border dark:border-ui-dark-border">
          Este bloque está bloqueado y no puede editarse.
        </div>
      )}
      <div className="flex-1 flex flex-col relative">
        <BlockNoteView
          editor={editor}
          editable={editable}
          theme={isDark ? 'dark' : 'light'}
          formattingToolbar={false}
          className="flex-1 flex flex-col"
          onChange={() => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
            debounceRef.current = setTimeout(() => {
              onChange?.(editor.document);
            }, 200);
          }}
        >
          {editable && (
            <div className="order-first sticky top-0 z-10 w-full border-b border-ui-border dark:border-ui-dark-border bg-ui-card/50 dark:bg-ui-dark-card/50 backdrop-blur-md px-2 py-1 shadow-sm">
              <FormattingToolbar />
            </div>
          )}
        </BlockNoteView>
      </div>
    </div>
  );
}
