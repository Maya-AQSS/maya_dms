import { useMemo } from 'react';
import {
  MayaEditor,
  convertBlockNoteToTiptap,
  type TiptapDoc,
} from '@ceedcv-maya/shared-editor-react';

/**
 * DMS template/document editor panel.
 *
 * Replaces the legacy BlockNote-backed `BlockNoteEditorPanel`. Accepts the
 * same prop shape so consumers don't need to change; the `initialContent`
 * may still be a legacy BlockNote JSON array until the data migration
 * (`php artisan blocknote:migrate-to-tiptap`) has run — in which case it
 * is converted on the fly at mount time.
 *
 * `onChange` now receives the TipTap doc (ProseMirror JSON) rather than a
 * BlockNote block array. Callers persist whatever object they receive
 * back to `content` / `default_content` — the schema accepts both shapes.
 */
interface Props {
  initialContent: unknown;
  editable: boolean;
  isDark: boolean;
  onChange?: (content: unknown) => void;
  onFullscreenChange?: (isFullscreen: boolean) => void;
  uploadFile?: (file: File) => Promise<string>;
}

function looksLikeTiptapDoc(value: unknown): value is TiptapDoc {
  return (
    !!value &&
    typeof value === 'object' &&
    !Array.isArray(value) &&
    (value as { type?: unknown }).type === 'doc' &&
    Array.isArray((value as { content?: unknown }).content)
  );
}

function looksLikeBlockNote(value: unknown): value is unknown[] {
  return (
    Array.isArray(value) &&
    value.length > 0 &&
    typeof value[0] === 'object' &&
    !!value[0] &&
    'type' in (value[0] as object)
  );
}

export function MayaEditorPanel({
  initialContent,
  editable,
  isDark,
  onChange,
  onFullscreenChange,
  uploadFile,
}: Props) {
  const initialDoc = useMemo<TiptapDoc | string | undefined>(() => {
    if (initialContent == null) return undefined;
    if (typeof initialContent === 'string') return initialContent;
    if (looksLikeTiptapDoc(initialContent)) return initialContent;
    if (looksLikeBlockNote(initialContent)) {
      return convertBlockNoteToTiptap(initialContent as Parameters<typeof convertBlockNoteToTiptap>[0]);
    }
    return undefined;
  }, [initialContent]);

  return (
    <MayaEditor
      mode="full"
      initialContent={initialDoc}
      editable={editable}
      isDark={isDark}
      onChange={onChange ? (html) => onChange(html) : undefined}
      onFullscreenChange={onFullscreenChange}
      uploadFile={uploadFile}
    />
  );
}
