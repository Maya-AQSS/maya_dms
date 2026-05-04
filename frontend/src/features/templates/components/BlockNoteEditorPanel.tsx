import { useEffect, useRef } from 'react';
import { useCreateBlockNote, FormattingToolbar } from '@blocknote/react';
import { BlockNoteView } from '@blocknote/ariakit';
import { repairBlockNoteBlocks } from '../../../utils/blockNoteRepair';
import '@blocknote/ariakit/style.css';
import '../styles/blocknote-panel.css';
import { normalizeBlockContentForEditor } from '../../documents/lib/normalizeBlockContent';

// ---------------------------------------------------------------------------
// Markdown paste heuristic
// ---------------------------------------------------------------------------

// Patterns that are unambiguously Markdown syntax.
const MD_PATTERNS: RegExp[] = [
  /^#{1,6} \S/m,          // headings  (# Title)
  /^\s*[-*+] \S/m,        // bullet list
  /^\s*\d+\. \S/m,        // ordered list
  /^> /m,                 // blockquote
  /^`{3}/m,               // code fence  — distinctive enough to count as 2
  /\*\*[^*\n]+\*\*/,       // bold
  /(?<!\*)\*[^*\s][^*\n]*\*(?!\*)/,  // italic (not bold)
  /\[[^\]]+\]\([^)]+\)/,  // inline link
  /^\|.+\|/m,             // table row
];

export function looksLikeMarkdown(text: string): boolean {
  let matches = 0;
  for (const re of MD_PATTERNS) {
    if (re.test(text)) {
      // Code fences are so distinctive they count double.
      matches += re.source.startsWith('^`{3}') ? 2 : 1;
    }
    if (matches >= 2) return true;
  }
  return false;
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

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

  // ---------------------------------------------------------------------------
  // Markdown paste handler
  // ---------------------------------------------------------------------------
  useEffect(() => {
    if (!editable) return;

    const dom = (editor as any)._tiptapEditor?.view?.dom as HTMLElement | undefined;
    if (!dom) return;

    const handlePaste = (e: ClipboardEvent) => {
      const plain = e.clipboardData?.getData('text/plain') ?? '';
      const html = e.clipboardData?.getData('text/html') ?? '';

      // Empty paste — nothing to do.
      if (!plain.trim()) return;

      // HTML from Word / Google Docs / browser: let BlockNote's built-in
      // HTML paste handler take over (it converts HTML to blocks natively).
      if (html && html.includes('<') && html.length > 80) return;

      // Plain text that does not carry enough Markdown markers — let BlockNote
      // paste it as plain text, no transformation needed.
      if (!looksLikeMarkdown(plain)) return;

      // From here on we own the paste event.
      e.preventDefault();
      e.stopPropagation();

      let parsedBlocks: any[];
      try {
        parsedBlocks = (editor as any).tryParseMarkdownToBlocks(plain);
      } catch {
        // Malformed input: abort silently. User can re-paste normally.
        return;
      }

      if (!parsedBlocks || parsedBlocks.length === 0) return;

      try {
        const cursor = (editor as any).getTextCursorPosition();
        const cursorBlock = cursor?.block;

        if (!cursorBlock) return;

        // If the cursor sits inside an empty paragraph, replace that block so
        // the pasted content lands in-place instead of after an empty line.
        const content = cursorBlock.content;
        const isEmpty =
          cursorBlock.type === 'paragraph' &&
          (!content ||
            content.length === 0 ||
            (content.length === 1 && content[0]?.type === 'text' && !content[0]?.text));

        if (isEmpty) {
          (editor as any).replaceBlocks([cursorBlock], parsedBlocks);
        } else {
          (editor as any).insertBlocks(parsedBlocks, cursorBlock, 'after');
        }

        // Move cursor to the end of the last inserted block.
        const lastBlock = parsedBlocks[parsedBlocks.length - 1];
        if (lastBlock?.id) {
          (editor as any).setTextCursorPosition(lastBlock.id, 'end');
        }

        // Trigger onChange so autosave picks up the pasted content.
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
          onChange?.((editor as any).document);
        }, 200);
      } catch {
        // Insertion failed — the editor state is unchanged, no data is lost.
      }
    };

    dom.addEventListener('paste', handlePaste);
    return () => dom.removeEventListener('paste', handlePaste);
  }, [editor, editable, onChange]);

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
