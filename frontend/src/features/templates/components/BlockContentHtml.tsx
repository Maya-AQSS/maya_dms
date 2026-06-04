/**
 * Read-only renderer for stored editor content.
 *
 * Accepts a TipTap doc (object with `type: 'doc'`) or a bare TipTap
 * content array (the shape MayaEditorPanel emits).
 *
 * Sanitisation is delegated to `@ceedcv-maya/shared-editor-react` whose
 * DOMPurify config matches the server-side `TiptapHtmlRenderer` output.
 */
import { useMemo } from 'react';
import {
  EditorContentHtml,
  type TiptapDoc,
} from '@ceedcv-maya/shared-editor-react';

function looksLikeTiptapDoc(value: unknown): value is TiptapDoc {
  return (
    !!value &&
    typeof value === 'object' &&
    !Array.isArray(value) &&
    (value as { type?: unknown }).type === 'doc' &&
    Array.isArray((value as { content?: unknown }).content)
  );
}


// A bare TipTap content array — the `content` field of a doc, which is the
// wire shape MayaEditorPanel emits (`onChange(doc.content)`) and the backend
// stores in `template_blocks.default_content`.
function looksLikeTiptapContentArray(value: unknown): value is TiptapDoc['content'] {
  return Array.isArray(value) && value.length > 0 && value.every((n) => !!n && typeof n === 'object');
}

export function BlockContentHtml({ content }: { content: unknown[] | unknown }) {
  const html = useMemo(() => {
    if (content == null) return '';

    try {
      // ProseMirror → HTML via a lightweight client conversion. We don't ship
      // the schema here; emit a permissive HTML serialisation. Accept both the
      // wrapped doc and the bare content array (the shape MayaEditorPanel emits
      // and the backend persists for TipTap blocks).
      if (looksLikeTiptapDoc(content)) {
        return jsonDocToHtml(content);
      }

      if (looksLikeTiptapContentArray(content)) {
        return jsonDocToHtml({ type: 'doc', content });
      }

      return '';
    } catch {
      return '<p><em>Error al renderizar el contenido.</em></p>';
    }
  }, [content]);

  if (!html) return null;
  return (
    <EditorContentHtml
      html={html}
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

/**
 * Minimal client-side ProseMirror → HTML emitter (used when content has
 * already been migrated to TipTap). Mirrors what `TiptapHtmlRenderer.php`
 * produces, so SSR and CSR previews agree.
 */
function jsonDocToHtml(doc: TiptapDoc): string {
  const renderInline = (nodes: TiptapDoc['content']): string =>
    nodes
      .map((n: TiptapDoc['content'][number]) => {
        if (n.type === 'text') {
          let t = escapeHtml(n.text ?? '');
          for (const mark of n.marks ?? []) {
            switch (mark.type) {
              case 'bold': t = `<strong>${t}</strong>`; break;
              case 'italic': t = `<em>${t}</em>`; break;
              case 'underline': t = `<u>${t}</u>`; break;
              case 'strike': t = `<s>${t}</s>`; break;
              case 'code': t = `<code>${t}</code>`; break;
              case 'link': {
                const href = String((mark.attrs?.href as string) ?? '#');
                t = `<a href="${escapeHtml(href)}">${t}</a>`;
                break;
              }
            }
          }
          return t;
        }
        if (n.type === 'hardBreak') return '<br>';
        return '';
      })
      .join('');

  const renderNodes = (nodes: TiptapDoc['content']): string =>
    nodes.map((child) => renderNode(child)).join('');

  const renderTable = (node: TiptapDoc['content'][number]): string => {
    const rows = (node.content ?? []).filter((r) => r.type === 'tableRow');
    if (rows.length === 0) return '';
    let html = '<table><tbody>';
    let isHeaderRow = true;
    for (const row of rows) {
      html += '<tr>';
      for (const cell of row.content ?? []) {
        if (cell.type !== 'tableCell' && cell.type !== 'tableHeader') continue;
        const tag = cell.type === 'tableHeader' || isHeaderRow ? 'th' : 'td';
        html += `<${tag}>${renderNodes((cell.content ?? []) as TiptapDoc['content'])}</${tag}>`;
      }
      html += '</tr>';
      isHeaderRow = false;
    }
    return `${html}</tbody></table>`;
  };

  const renderNode = (node: TiptapDoc['content'][number]): string => {
    const inner = renderInline(node.content ?? []);
    switch (node.type) {
      case 'table':
        return renderTable(node);
      case 'paragraph': return `<p>${inner}</p>`;
      case 'heading': {
        const lvl = Math.max(1, Math.min(6, Number(node.attrs?.level ?? 2) || 2));
        return `<h${lvl}>${inner}</h${lvl}>`;
      }
      case 'bulletList': return `<ul>${(node.content ?? []).map(renderNode).join('')}</ul>`;
      case 'orderedList': return `<ol>${(node.content ?? []).map(renderNode).join('')}</ol>`;
      case 'listItem': return `<li>${(node.content ?? []).map(renderNode).join('')}</li>`;
      case 'taskList': return `<ul class="checklist">${(node.content ?? []).map(renderNode).join('')}</ul>`;
      case 'taskItem': {
        const checked = node.attrs?.checked ? ' checked' : '';
        return `<li><input type="checkbox" disabled${checked}> ${(node.content ?? []).map(renderNode).join('')}</li>`;
      }
      case 'blockquote': return `<blockquote>${(node.content ?? []).map(renderNode).join('')}</blockquote>`;
      case 'codeBlock': return `<pre><code>${inner}</code></pre>`;
      case 'horizontalRule': return '<hr>';
      case 'image': {
        const src = escapeHtml(String(node.attrs?.src ?? ''));
        const alt = escapeHtml(String(node.attrs?.alt ?? ''));
        return src ? `<img src="${src}" alt="${alt}">` : '';
      }
      default: return inner;
    }
  };

  return doc.content.map(renderNode).join('');
}

function escapeHtml(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
