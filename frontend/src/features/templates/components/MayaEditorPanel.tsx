import { useMemo } from 'react';
import {
  MayaEditor,
  normalizeTiptapContentForCompare,
  type TiptapDoc,
  type CommentHoverData,
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
  /**
   * Optional anchored-comment hook. Receives `{from, to, text}` and is
   * expected to POST to /api/v1/{template|document}/{id}/anchored-comments
   * and return the new commentId so MayaEditor can apply the mark.
   */
  onCreateComment?: (range: {
    from: number;
    to: number;
    text: string;
  }) => Promise<string | number | null | undefined>;
  /** Optional .docx export action — usually fetches the export endpoint. */
  onExportDocx?: () => void;
  /** Optional comments dictionary keyed by id for hover-preview popovers. */
  commentsById?: Record<string, CommentHoverData>;
  /** Flush autoguardado (p. ej. `forceSave`) al perder foco o cambiar modo HTML/MD. */
  onFlush?: () => void;
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

function isTiptapContentArray(value: unknown): value is TiptapDoc['content'] {
  return Array.isArray(value) && value.every((n) => !!n && typeof n === 'object');
}

function normaliseToDoc(value: unknown): TiptapDoc | string | undefined {
  if (value == null) return undefined;
  if (typeof value === 'string') return value;
  if (looksLikeTiptapDoc(value)) return value;
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
  onCreateComment,
  onExportDocx,
  commentsById,
  onFlush,
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
              onChange(normalizeTiptapContentForCompare(doc.content));
            }
          : undefined
      }
      onFullscreenChange={onFullscreenChange}
      uploadFile={uploadFile}
      onCreateComment={onCreateComment}
      onExportDocx={onExportDocx}
      commentsById={commentsById}
      onFlush={onFlush}
    />
  );
}
