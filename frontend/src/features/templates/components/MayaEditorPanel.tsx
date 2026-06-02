import { useMemo } from 'react';
import {
  MayaEditor,
  convertBlockNoteToTiptap,
  type TiptapDoc,
} from '@ceedcv-maya/shared-editor-react';

/**
 * DMS template/document editor panel.
 *
 * Replaces the legacy BlockNote-backed `BlockNoteEditorPanel` with TipTap
 * while keeping the storage shape callers expect:
 *
 *   - `initialContent`  may arrive as:
 *       · legacy BlockNote block array `[{type, props, content, children}, …]`
 *       · TipTap doc `{ type: 'doc', content: […] }`
 *       · TipTap content array (the `content` field of a doc, what we emit)
 *       · raw HTML string
 *     The component normalises to a TipTap doc internally.
 *
 *   - `onChange` emits the **content array** (the `content` field of the
 *     TipTap doc) — same JSON shape (array of nodes) that the backend
 *     stored under BlockNote. The validation rule `array` on
 *     `template_blocks.content` / `template_blocks.description` keeps
 *     working without backend changes, and the `blocknote:migrate-to-tiptap`
 *     command's parity check still operates on equivalent structures.
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
  if (!Array.isArray(value) || value.length === 0) return false;
  const first = value[0] as { type?: unknown; props?: unknown; children?: unknown };
  if (!first || typeof first !== 'object') return false;
  // BlockNote blocks always carry `type` and usually `props`/`children`.
  // ProseMirror node arrays use `type` too but lack `props`.
  return 'type' in first && ('props' in first || 'children' in first);
}

function isTiptapContentArray(value: unknown): value is TiptapDoc['content'] {
  return Array.isArray(value) && value.every((n) => !!n && typeof n === 'object');
}

function normaliseToDoc(value: unknown): TiptapDoc | string | undefined {
  if (value == null) return undefined;
  if (typeof value === 'string') return value;
  if (looksLikeTiptapDoc(value)) return value;
  if (looksLikeBlockNote(value)) {
    return convertBlockNoteToTiptap(value as Parameters<typeof convertBlockNoteToTiptap>[0]);
  }
  if (isTiptapContentArray(value)) {
    return { type: 'doc', content: value as TiptapDoc['content'] };
  }
  return undefined;
}

export function MayaEditorPanel({
  initialContent,
  editable,
  isDark,
  onChange,
  onFullscreenChange,
  uploadFile,
}: Props) {
  const initialDoc = useMemo(() => normaliseToDoc(initialContent), [initialContent]);

  return (
    <MayaEditor
      mode="full"
      output="json"
      initialContent={initialDoc}
      editable={editable}
      isDark={isDark}
      onChange={
        onChange
          ? (payload) => {
              // payload is a TipTap doc when output='json'. Flatten to its
              // `content` array so the wire shape matches what the backend
              // already validates (legacy BlockNote was an array).
              if (typeof payload === 'string') {
                onChange(payload);
                return;
              }
              const doc = payload as TiptapDoc;
              onChange(doc.content);
            }
          : undefined
      }
      onFullscreenChange={onFullscreenChange}
      uploadFile={uploadFile}
    />
  );
}
