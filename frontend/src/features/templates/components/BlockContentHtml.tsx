/**
 * Read-only renderer for stored editor content.
 *
 * Thin wrapper over `EditorContentJson` from `@ceedcv-maya/shared-editor-react`,
 * which renders TipTap/ProseMirror JSON via TipTap's static renderer (single
 * source of truth with the editor schema) and sanitises with DOMPurify. This
 * replaces the previous hand-rolled `jsonDocToHtml`, which drifted from the
 * editor and the server-side `TiptapHtmlRenderer`.
 *
 * Accepts a TipTap doc (`{ type: 'doc', content }`) or a bare content array —
 * normalisation is handled inside `EditorContentJson`.
 */
import { EditorContentJson } from '@ceedcv-maya/shared-editor-react';

export function BlockContentHtml({ content }: { content: unknown[] | unknown }) {
  return (
    <EditorContentJson
      content={content}
      className={[
        'bn-doc-content maya-editor-content text-sm leading-relaxed text-text-primary dark:text-text-dark-primary',
        '[&_p]:my-2 [&_p:first-child]:mt-0 [&_p:last-child]:mb-0',
        '[&_ul]:list-disc [&_ul]:pl-6 [&_ul]:my-2 [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:my-2 [&_li]:my-0.5',
        '[&_table]:w-full [&_table]:border-collapse [&_table]:my-3 [&_table]:text-sm',
        '[&_th]:border [&_td]:border [&_th]:border-ui-border [&_td]:border-ui-border',
        '[&_th]:px-3 [&_td]:px-3 [&_th]:py-2 [&_td]:py-2 [&_th]:text-left [&_th]:font-semibold',
        '[&_th]:bg-black/[0.04] dark:[&_th]:bg-white/[0.06] [&_td]:align-top',
        '[&_img]:max-w-full [&_img]:h-auto [&_img]:rounded',
        '[&_blockquote]:border-l-4 [&_blockquote]:border-ui-border [&_blockquote]:pl-4 [&_blockquote]:my-2',
      ].join(' ')}
    />
  );
}
