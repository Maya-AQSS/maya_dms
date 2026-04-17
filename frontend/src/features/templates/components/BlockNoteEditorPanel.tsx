import { useRef } from 'react';
import { useCreateBlockNote } from '@blocknote/react';
import { BlockNoteView } from '@blocknote/react';
import '@blocknote/react/style.css';

interface Props {
  initialContent: unknown;
  editable: boolean;
  isDark: boolean;
  onChange: (content: unknown) => void;
}

// key={activeBlockId} on the parent <Suspense> forces remount when block changes,
// so initialContent always reflects the correct block.
export default function BlockNoteEditorPanel({ initialContent, editable, isDark, onChange }: Props) {
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const safeContent =
    Array.isArray(initialContent) && initialContent.length > 0
      ? // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (initialContent as any)
      : undefined;

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const editor = useCreateBlockNote({ initialContent: safeContent } as any);

  return (
    <div className="flex-1 flex flex-col overflow-y-auto min-h-0">
      {!editable && (
        <div className="mx-4 mt-3 px-3 py-2 text-xs text-text-muted dark:text-text-dark-muted bg-ui-body dark:bg-ui-dark-bg rounded border border-ui-border dark:border-ui-dark-border">
          Este bloque está bloqueado y no puede editarse.
        </div>
      )}
      <div className="flex-1">
        <BlockNoteView
          editor={editor}
          editable={editable}
          theme={isDark ? 'dark' : 'light'}
          onChange={() => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
            debounceRef.current = setTimeout(() => {
              onChange(editor.document);
            }, 500);
          }}
        />
      </div>
    </div>
  );
}
